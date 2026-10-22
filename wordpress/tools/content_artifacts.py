"""Editorial gates and original, self-contained SVG/CSV process artifacts."""
import collections
import csv
import hashlib
import html
import io
import json
import pathlib
import re
import textwrap
import xml.etree.ElementTree as ET


def visible_text(markdown):
    value = re.sub(r'\[([^\]]+)\]\([^\s)]+\)', r'\1', markdown)
    value = re.sub(r'^```[^\n]*$|^\s*\|(?:\s*:?-+:?\s*\|)+\s*$', '', value, flags=re.M)
    value = re.sub(r'^#{1,6}\s+', '', value, flags=re.M)
    value = value.replace('**', '').replace('`', '').replace('|', '')
    return re.sub(r'\s+', ' ', value).strip()


def tables(markdown):
    lines = markdown.splitlines()
    found = []
    for index, line in enumerate(lines[:-1]):
        if not line.strip().startswith('|') or not re.fullmatch(r'\s*\|(?:\s*:?-+:?\s*\|)+\s*', lines[index + 1]):
            continue
        split = lambda row: [cell.strip() for cell in row.strip().strip('|').split('|')]
        rows = [split(line)]
        for following in lines[index + 2:]:
            if not following.strip().startswith('|'):
                break
            rows.append(split(following))
        if len(rows) < 4 or len(rows[0]) < 3 or any(len(row) != len(rows[0]) for row in rows):
            raise ValueError('A practical table needs at least three columns and three data rows')
        found.append(rows)
    return found


def validate_article(record, raw, *, minimum=10000):
    if not raw.startswith('# ' + record['title'] + '\n'):
        raise ValueError(f'Title mismatch: {record["slug"]}')
    body = raw.split('\n', 1)[1].strip()
    count = len(visible_text(body))
    if count < minimum or len(re.findall(r'^## ', body, re.M)) < 3:
        raise ValueError(f'Incomplete text: {record["slug"]}: {count} visible characters')
    if re.search(r'(?<![а-яё])сказк[а-яё]*|skazka|parkops|AlekseiGunko|BEGIN[^\n]*PRIVATE KEY|gh[pousr]_[a-zA-Z0-9]{20}', raw, re.I):
        raise ValueError(f'Restricted content: {record["slug"]}')
    if not tables(body):
        raise ValueError(f'Missing practical table: {record["slug"]}')
    diagram = record.get('infographic') or {}
    if not 5 <= len(diagram.get('steps', [])) <= 8:
        raise ValueError(f'Expected five to eight process steps: {record["slug"]}')
    fields = [diagram.get('title'), diagram.get('subtitle')]
    fields += [step.get(field) for step in diagram['steps'] for field in ('label', 'detail')]
    fields += [diagram.get('decision', {}).get(field) for field in ('question', 'yes', 'no')]
    fields += [diagram.get('control', {}).get(field) for field in ('owner', 'evidence', 'metric')]
    if any(not isinstance(value, str) or not value.strip() for value in fields):
        raise ValueError(f'Incomplete process artifact: {record["slug"]}')
    return {'visible_characters': count, 'tables': len(tables(body)), 'sha256': hashlib.sha256(raw.encode()).hexdigest()}


def repeated_paragraphs(texts, minimum=240, occurrences=3):
    paragraphs = collections.defaultdict(set)
    for key, text in texts.items():
        for paragraph in re.split(r'\n\s*\n', text):
            normalized = re.sub(r'\s+', ' ', paragraph).strip()
            if len(normalized) >= minimum:
                paragraphs[normalized].add(str(key))
    return [{'articles': sorted(keys), 'characters': len(paragraph), 'sample': paragraph[:160]}
            for paragraph, keys in paragraphs.items() if len(keys) >= occurrences]


def _lines(text, width):
    return textwrap.wrap(text, width=width, break_long_words=True, break_on_hyphens=False) or ['']


def render_svg(diagram, key):
    if not re.fullmatch(r'[a-z0-9-]+', key):
        raise ValueError('Unsafe artifact key')
    elements = []
    def rect(x, y, width, height, fill, radius=22, stroke=None):
        elements.append(f'<rect x="{x}" y="{y}" width="{width}" height="{height}" rx="{radius}" fill="{fill}"' + (f' stroke="{stroke}"' if stroke else '') + '/>')
    def text(value, x, y, size=25, width=42, color='#202522', weight=400):
        lines = _lines(value, width)
        elements.append(f'<text x="{x}" y="{y}" fill="{color}" font-family="DejaVu Sans, Arial, sans-serif" font-size="{size}" font-weight="{weight}">')
        for index, line in enumerate(lines):
            elements.append(f'<tspan x="{x}" dy="{0 if index == 0 else round(size * 1.4)}">{html.escape(line)}</tspan>')
        elements.append('</text>')
        return len(lines) * round(size * 1.4)
    text('СУТРИ / КАРТА ПРОЦЕССА', 68, 86, 19, 60, '#59644f', 600)
    title_height = text(diagram['title'], 68, 166, 47, 42, weight=600)
    subtitle_y = 166 + title_height + 8
    subtitle_height = text(diagram['subtitle'], 68, subtitle_y, 25, 78, '#59644f')
    y = subtitle_y + subtitle_height + 42
    for start in range(0, len(diagram['steps']), 2):
        row = diagram['steps'][start:start + 2]
        heights = [110 + len(_lines(s['label'], 30)) * 39 + len(_lines(s['detail'], 43)) * 32 for s in row]
        height = max(heights) + 20
        for column, step in enumerate(row):
            x = 68 + column * 662
            rect(x, y, 630, height, '#ffffff', stroke='#d8dfd0')
            rect(x + 30, y + 28, 66, 52, '#d8f58a', 26)
            text(f'{start + column + 1:02d}', x + 43, y + 63, 24, 4, weight=600)
            h = text(step['label'], x + 30, y + 125, 28, 30, weight=600)
            text(step['detail'], x + 30, y + 125 + h + 10, 23, 43, '#4f5c51')
        y += height + 24
    decision = diagram['decision']
    qheight = len(_lines(decision['question'], 66)) * 41
    answer_height = max(len(_lines(decision['yes'], 42)), len(_lines(decision['no'], 42))) * 32
    height = 132 + qheight + answer_height
    rect(68, y, 1292, height, '#242a25')
    text(decision['question'], 102, y + 58, 29, 66, '#ffffff', 600)
    answer_y = y + 58 + qheight + 30
    for column, (label, value) in enumerate((('УСЛОВИЕ ВЫПОЛНЕНО', decision['yes']), ('ТРЕБУЕТ ДОРАБОТКИ', decision['no']))):
        x = 102 + column * 645
        text(label, x, answer_y, 17, 40, '#d8f58a', 600)
        text(value, x, answer_y + 44, 23, 42, '#edf2e7')
    y += height + 44
    control = diagram['control']
    max_height = 0
    for column, (label, field) in enumerate((('ОТВЕТСТВЕННЫЙ', 'owner'), ('ПОДТВЕРЖДЕНИЕ', 'evidence'), ('ЧТО ИЗМЕРЯЕМ', 'metric'))):
        x = 68 + column * 440
        text(label, x, y, 17, 28, '#59644f', 600)
        h = text(control[field], x, y + 44, 22, 28)
        max_height = max(max_height, h)
    footer_y = y + max_height + 110
    text('sy3.ru · Рабочая схема для обсуждения и проверки требований', 68, footer_y, 18, 100, '#59644f')
    canvas_height = footer_y + 54
    svg = f'<svg xmlns="http://www.w3.org/2000/svg" width="1440" height="{canvas_height}" viewBox="0 0 1440 {canvas_height}" role="img" aria-labelledby="title description"><title id="title">{html.escape(diagram["title"])}</title><desc id="description">{html.escape(diagram["subtitle"])}</desc><rect width="1440" height="{canvas_height}" fill="#f3f5ee"/>' + ''.join(elements) + '</svg>\n'
    ET.fromstring(svg)
    return svg


def write_artifacts(record, raw, directory, key):
    directory = pathlib.Path(directory)
    directory.mkdir(parents=True, exist_ok=True)
    svg = render_svg(record['infographic'], key)
    (directory / (key + '.svg')).write_text(svg)
    output = io.StringIO(newline='')
    writer = csv.writer(output, lineterminator='\n')
    for row in tables(raw)[0]:
        # A downloaded worksheet must not execute spreadsheet formulas.
        writer.writerow([("'" if cell.lstrip().startswith(('=', '+', '-', '@')) else '') + cell for cell in row])
    (directory / (key + '.csv')).write_text('\ufeff' + output.getvalue())
    return {suffix: hashlib.sha256((directory / (key + suffix)).read_bytes()).hexdigest() for suffix in ('.svg', '.csv')}
