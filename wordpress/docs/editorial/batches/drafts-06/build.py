#!/usr/bin/env python3
"""Apply the twenty authored editorial plans to a read-only baseline snapshot."""
import argparse
import csv
import hashlib
import html
import json
import re
import subprocess
from pathlib import Path
from urllib.parse import urlsplit


HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[4]
IDS = [194, 357, 277, 338, 204, 301, 358, 278, 214, 339, 302, 359, 224, 279, 340, 303, 234, 360, 280, 341]
TG_OLD = 'https://t.me/moi_imperator'
TG = 'https://t.me/sy3_support_bot?start=site_sy3'
MAX = 'https://max.ru/id263514840335_1_bot?start=site_sy3'


def sha(text):
    return hashlib.sha256(text.encode()).hexdigest()


def p(text, cls=None):
    attr = ' ' + json.dumps({'className': cls}, ensure_ascii=False, separators=(',', ':')) if cls else ''
    tag = ' class="' + cls + '"' if cls else ''
    return '<!-- wp:paragraph' + attr + ' --><p' + tag + '>' + text + '</p><!-- /wp:paragraph -->\n'


def h(text):
    return '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">' + html.escape(text) + '</h2><!-- /wp:heading -->\n'


def raw_top_blocks(body):
    depth = 0
    start = None
    for token in re.finditer(r'<!--\s*(/?)wp:([\w/-]+)\b[^>]*?-->', body):
        if token.group(1):
            depth -= 1
            if depth < 0:
                raise ValueError('Unbalanced native block')
            if depth == 0:
                yield body[start:token.end()]
        else:
            if depth == 0:
                start = token.start()
            depth += 1
    if depth:
        raise ValueError('Unclosed native block')


def protected(body):
    return [block for block in raw_top_blocks(body) if (
        block.startswith('<!-- wp:table')
        or re.search(r'^<!-- wp:group[^>]*"className":"(?:process-map|editorial-sources)"', block)
    )]


def urls(body):
    return set(re.findall(r'href="([^"]+)"', body))


def project_sources():
    result = {}
    with (ROOT / 'wordpress/docs/editorial/CONTENT_INVENTORY.csv').open() as stream:
        for row in csv.DictReader(stream):
            if row['wp_id'].isdigit() and int(row['wp_id']) in IDS:
                result.setdefault(int(row['wp_id']), set()).update(row['source'].split(' | '))
    return {key: sorted(value) for key, value in result.items()}


def quality(bodies):
    # Evaluate only the existing quality function and native parser. No WordPress bootstrap or database.
    code = r'''
require '/var/www/sy3-wordpress/wp-includes/class-wp-block-parser.php';
require '/var/www/sy3-wordpress/wp-includes/formatting.php';
function parse_blocks($body) { return (new WP_Block_Parser())->parse($body); }
$source=file_get_contents($argv[1].'/wordpress/plugin/sy3-editorial.php');
$start=strpos($source,'function sy3_editorial_quality(');
$end=strpos($source,"\nfunction sy3_editorial_review_hash(",$start);
if($start===false||$end===false)throw new Exception('Quality source boundary');
$function=substr($source,$start,$end-$start); eval($function);
$rows=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR); $out=[];
foreach($rows as $id=>$body){$out[$id]=sy3_editorial_quality($body);}
echo json_encode(['function_hash'=>hash('sha256',$function),'results'=>$out],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
'''
    run = subprocess.run(['php', '-r', code, str(ROOT)], input=json.dumps(bodies, ensure_ascii=False), text=True, capture_output=True, check=True)
    return json.loads(run.stdout)


def candidate(row, plan):
    old = row['post_content']
    if sha(old) != row['body_hash'] or row['post_type'] != 'post' or row['post_status'] != 'draft':
        raise ValueError('Baseline identity/state mismatch: ' + row['ID'])
    marker = re.search(r'<!-- wp:heading[^>]*--><h2[^>]*>' + re.escape(html.escape(plan['cut_before'], quote=False)) + r'</h2><!-- /wp:heading -->', old)
    if not marker:
        raise ValueError('Authored cut anchor not found: ' + row['ID'])
    removed = old[:marker.start()]
    carried = protected(removed)
    body = ''.join(p(text) for text in plan['opening']) + '\n'.join(carried) + '\n' + old[marker.start():]
    inline = p(html.escape(plan['cta']) + ' <a href="' + TG + '">Написать в Telegram</a> · <a href="' + MAX + '">Написать в MAX</a>.', 'editorial-contact')
    table = re.search(r'<!-- wp:table\b.*?<!-- /wp:table -->', body, re.S)
    if not table:
        raise ValueError('Table absent: ' + row['ID'])
    body = body[:table.end()] + '\n' + inline + body[table.end():]
    extra = ''
    if plan['module']:
        extra += h(plan['module_heading']) + ''.join(p(text) for text in plan['module'])
    extra += h('Вопросы по теме')
    for question, answer in plan['faq']:
        extra += '<!-- wp:details --><details class="wp-block-details"><summary>' + html.escape(question) + '</summary>' + p(html.escape(answer)) + '</details><!-- /wp:details -->\n'
    sources = re.search(r'<!-- wp:group[^>]*"className":"editorial-sources"', body)
    if not sources:
        raise ValueError('Sources panel absent: ' + row['ID'])
    body = body[:sources.start()] + extra + body[sources.start():]
    body = body.replace(TG_OLD, TG).replace('Обсудить задачу с Артёмом', 'Обсудить проект').replace('Написать Артёму', 'Написать мне')
    # Replace generic footer prose while retaining its service URL and bot link.
    generic = re.compile(r'<!-- wp:paragraph --><p>(<a href="https://sy3\.ru/services/[^"]+">[^<]+</a>) — обсудим исходную ситуацию, объём и способ проверки результата\. (<a href="' + re.escape(TG) + r'">[^<]+</a>)</p><!-- /wp:paragraph -->')
    body = generic.sub(lambda match: p(match.group(1) + ' · ' + match.group(2)).rstrip(), body)
    body = body.replace('Если нужна помощь с этой задачей</h2>', 'Обсудить ваш случай</h2>')
    for before, after in plan.get('replacements', []):
        if body.count(before) != 1:
            raise ValueError('Authored replacement boundary: ' + row['ID'])
        body = body.replace(before, after)
    for block in protected(old):
        if block not in body:
            raise ValueError('Protected table/process/source bytes changed: ' + row['ID'])
    missing = {TG if url == TG_OLD else url for url in urls(old)} - urls(body)
    if missing:
        raise ValueError('Meaningful URL removed: ' + row['ID'] + repr(missing))
    list(raw_top_blocks(body))
    return body, {'removed_prefix_characters': len(html.unescape(re.sub('<[^>]+>', '', removed))), 'protected_blocks': len(protected(old)), 'carried_prefix_artifacts': len(carried)}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--baseline', type=Path, required=True)
    args = parser.parse_args()
    rows = json.loads(args.baseline.read_text())['rows']
    by_id = {int(row['ID']): row for row in rows}
    cases = {row['post_name']: row for row in rows if row['post_type'] == 'sy3_case'}
    plans = json.loads((HERE / 'edits.json').read_text())
    if set(map(int, plans)) != set(IDS):
        raise ValueError('Authored batch scope mismatch')
    source_map = project_sources()
    bodies = {}
    audits = {}
    for item_id in IDS:
        bodies[str(item_id)], audits[item_id] = candidate(by_id[item_id], plans[str(item_id)])
    checks = quality(bodies)
    candidates = []
    for item_id in IDS:
        row = by_id[item_id]
        body = bodies[str(item_id)]
        q = checks['results'][str(item_id)]
        if not q['complete'] or q['characters'] < 10500:
            raise ValueError('Article quality below target: ' + str(item_id) + ' ' + repr(q))
        filename = row['post_name'] + '.html'
        (HERE / filename).write_text(body)
        evidence = []
        for slug in plans[str(item_id)]['case_slugs']:
            case = cases[slug]
            evidence.append({'status': 'VERIFIED', 'case_slug': slug, 'id': int(case['ID']), 'url': case['url'], 'body_hash': case['body_hash'], 'scope': 'Accepted public case only; no additional client functions, new live audit or business-effect claim.'})
        evidence.append({'status': 'ILLUSTRATIVE_EXAMPLE', 'scope': 'Methodical examples remain explicitly illustrative; proposed tests are not client results.'})
        source_links = sorted(url for url in urls(row['post_content']) if url.startswith('https://') and urlsplit(url).hostname not in {'sy3.ru', 't.me', 'max.ru'})
        evidence.append({'status': 'PRIMARY_SOURCE_CHECKED', 'checked_on': '2026-10-23', 'urls': source_links, 'scope': 'Targeted documentation sections and claim boundaries; see REPORT.md. Not a complete standards audit or legal conclusion.'})
        assets = []
        for url in sorted(urls(row['post_content'])):
            if '/assets/infographics/' in url:
                local = ROOT / 'wordpress/theme/assets/infographics' / url.rsplit('/', 1)[1]
                assets.append({'url': url, 'file': str(local.relative_to(ROOT)), 'sha256': hashlib.sha256(local.read_bytes()).hexdigest()})
        candidates.append({
            'id': item_id, 'slug': row['post_name'], 'type': 'post', 'baseline_hash': row['body_hash'],
            'file': filename, 'candidate_hash': sha(body), 'visible_chars': q['characters'],
            'policy_state': 'LENGTH_REQUIREMENT_MET', 'review_state': 'NEEDS_EDITORIAL_REVIEW',
            'action': 'TARGETED_REWRITE_KEEP_SUBSTANTIVE_ANALYSIS', 'reason': plans[str(item_id)]['why'],
            'source_files': source_map[item_id], 'evidence': evidence,
            'preserved': {key: row[key] for key in ['post_title', 'post_excerpt', 'post_status', 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt', 'post_author', 'post_parent', 'menu_order', 'url', 'meta', 'terms', 'cover']},
            'protected_artifacts': assets, 'audit': audits[item_id],
            'quality': q, 'quality_function_hash': checks['function_hash'],
            'review_hash_created': False, 'reader_test': 'READER_TEST_NOT_RUN',
        })
    manifest = {'batch': 'drafts-06', 'source_base': '273d8b4', 'production_authority': False, 'database_changed': False, 'article_policy_changed': False, 'candidates': candidates}
    (HERE / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps({'candidates': len(candidates), 'minimum_quality_characters': min(c['visible_chars'] for c in candidates), 'maximum_quality_characters': max(c['visible_chars'] for c in candidates), 'quality_function_hash': checks['function_hash']}, ensure_ascii=False))


if __name__ == '__main__':
    main()
