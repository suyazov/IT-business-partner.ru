"""Compile 100 existing drafts and ten published-article supplements without rewriting originals."""
import argparse
import html
import json
import pathlib
import re
from content_artifacts import validate_article, repeated_paragraphs, write_artifacts

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('directories', nargs='+', type=pathlib.Path)
parser.add_argument('--audit-only', action='store_true', help='Validate available ready records; do not write canonical files')
parser.add_argument('--ready-only', action='store_true', help='Write only a reviewed subset; the importer needs explicit partial-import mode')
args = parser.parse_args()
root = pathlib.Path(__file__).resolve().parents[1]
originals = {}
for path in sorted((root/'editorial/articles').glob('*.md')):
    for chunk in re.split(r'^@@ ', path.read_text(), flags=re.M):
        if not chunk.strip():
            continue
        metadata, raw = chunk.strip().split('\n', 1)
        number, slug, category, cover, sources = metadata.split('|')
        title, body = raw.split('\n', 1)
        originals[int(number)] = {'id': int(number), 'slug': slug, 'title': title.removeprefix('# '), 'body': body.strip()}
published = {r['slug']: r for r in json.loads((root/'content/articles.json').read_text())}
records, texts, quality = {}, {}, {}
for directory in args.directories:
    for kind, folder in [('expanded',directory/'expanded'),('expanded',directory),('published',directory/'published')]:
        if not (folder/'manifest.json').is_file():
            continue
        for row in json.loads((folder/'manifest.json').read_text()):
            if kind=='expanded' and not 1 <= int(row.get('id',0)) <= 100:
                continue
            if not row.get('longform_ready', False):
                continue
            key = f"article-{int(row['id'])}" if kind == 'expanded' else 'published-'+row['slug']
            if key in records:
                raise ValueError('Duplicate longform key '+key)
            if not re.fullmatch(r'[a-z0-9-]+', row['slug']):
                raise ValueError('Unsafe slug')
            candidate = folder/row.get('file', f"{int(row['id']):03d}-{row['slug']}.md" if kind == 'expanded' else row['slug']+'.md')
            if not candidate.is_file() and row.get('file'):
                candidate=directory/row['file']
            if not candidate.resolve().is_relative_to(folder.resolve()) or not candidate.is_file():
                raise ValueError('Unsafe or missing manuscript '+str(candidate))
            raw = candidate.read_text()
            if kind == 'expanded':
                original = originals[int(row['id'])]
                if row['slug'] != original['slug'] or row['title'] != original['title']:
                    raise ValueError('Existing article identity changed '+key)
                body = raw.split('\n', 1)[1].strip()
                if not body.startswith(original['body']+'\n'):
                    raise ValueError('Original manuscript prefix must be preserved '+key)
                addition = body[len(original['body']):].strip()
            else:
                if row['slug'] not in published or row['title'] != published[row['slug']]['title']:
                    raise ValueError('Published article identity changed '+key)
                # Supplements alone exceed the minimum, so existing hand edits never need replacing.
                if raw.startswith('# '+row['title']+'\n'):
                    addition = raw.split('\n', 1)[1].strip()
                elif raw.startswith('## '):
                    addition=raw.strip();raw='# '+row['title']+'\n\n'+raw
                else:
                    raise ValueError('Supplement must start with its title or a level-two heading '+key)
            quality[key] = validate_article(row, raw)
            for source in row.get('sources', []):
                if not isinstance(source, dict) or not source.get('url', '').startswith('https://'):
                    raise ValueError('Expected primary-source URL '+key)
            if not row.get('sources'):
                raise ValueError('Primary sources missing '+key)
            texts[key] = raw
            record = {k: v for k, v in row.items() if k in ('id','slug','title','infographic','sources')}
            record.update(key=key, kind=kind, file=key+'.md', addition=addition)
            records[key] = record
duplicates = repeated_paragraphs(texts)
if duplicates:
    raise ValueError('Repeated filler paragraphs: '+json.dumps(duplicates, ensure_ascii=False))
if not args.audit_only:
    expected = {f'article-{n}' for n in range(1,101)} | {'published-'+slug for slug in published}
    if not records or not set(records).issubset(expected) or (not args.ready_only and set(records) != expected):
        raise ValueError('All 110 original articles required; missing '+', '.join(sorted(expected-set(records))))
    target = root/'longform'
    (target/'articles').mkdir(parents=True, exist_ok=True)
    for key, row in records.items():
        raw = '# '+row['title']+'\n\n'+row.pop('addition')+'\n'
        (target/'articles'/row['file']).write_text(raw)
        row['artifacts'] = write_artifacts(row, texts[key], root/'theme/assets/infographics', key)
    (target/'manifest.json').write_text(json.dumps(list(records.values()),ensure_ascii=False,indent=2)+'\n')
    (target/'quality.json').write_text(json.dumps(quality,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'validated': len(records), 'canonical_written': not args.audit_only, 'quality': quality}, ensure_ascii=False))
