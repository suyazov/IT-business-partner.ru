#!/usr/bin/env python3
"""Index prepared copies from one published Git commit, without CMS access."""
import argparse
import hashlib
import json
import re
import subprocess
from collections import Counter
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]


def digest(text):
    return hashlib.sha256(text.encode()).hexdigest()


def git(*args):
    return subprocess.run(['git', '-C', str(REPO), *args], check=True,
                          text=True, capture_output=True).stdout


def build(baseline, commit):
    if not re.fullmatch(r'[0-9a-f]{40}', commit):
        raise ValueError('An exact full commit SHA is required')
    if baseline.get('site') != 'https://sy3.ru/':
        raise ValueError('Only sy3.ru is in scope')
    captured = baseline['rows']
    original = {int(r['ID']): r for r in captured}
    if len(original) != len(captured):
        raise ValueError('Duplicate baseline IDs')
    files = set(git('ls-tree', '-r', '--name-only', commit, '--',
                    'wordpress/docs/editorial/pilot',
                    'wordpress/docs/editorial/batches').splitlines())
    prepared = {}

    def read(path):
        if path not in files:
            raise ValueError('Missing tracked candidate file: ' + path)
        # Git object reads do not follow worktree symlinks or unfinished files.
        mode = git('ls-tree', commit, '--', path).split()[0]
        if mode != '100644':
            raise ValueError('Candidate must be a regular tracked file')
        return git('show', commit + ':' + path)

    def add(candidate, body, pointer, manifest, kind):
        item_id = candidate['id']
        if type(item_id) is not int or item_id in prepared:
            raise ValueError('Invalid/duplicate candidate ID')
        old = original.get(item_id)
        if (not old or old['post_type'] != kind
                or old['post_name'] != candidate['slug']
                or old['body_hash'] != candidate['baseline_hash']
                or digest(old['post_content']) != candidate['baseline_hash']
                or digest(body) != candidate['candidate_hash']
                or candidate.get('review_state') != 'NEEDS_EDITORIAL_REVIEW'):
            raise ValueError('Candidate identity/hash/state mismatch: ' + str(item_id))
        chars = candidate.get('visible_chars')
        if kind == 'post' and (type(chars) is not int or chars < 10000
                               or candidate.get('policy_state') != 'LENGTH_REQUIREMENT_MET'):
            raise ValueError('Article must declare the existing length policy')
        prepared[item_id] = {
            'id': item_id, 'type': kind, 'slug': candidate['slug'],
            'baseline_hash': candidate['baseline_hash'],
            'candidate_hash': candidate['candidate_hash'],
            'new_version': pointer, 'manifest': manifest,
            'content_characters_declared': chars,
            'excerpt_change_prepared': 'excerpt' in candidate and candidate['excerpt'] != old['post_excerpt'],
            'publisher_approval': 'NOT_CREATED',
        }

    commercial_path = 'wordpress/docs/editorial/pilot/commercial.json'
    commercial = json.loads(read(commercial_path))
    if commercial.get('production_authority') is not False:
        raise ValueError('No production authority may be declared')
    for index, c in enumerate(commercial['candidates']):
        add(c, c['content'], commercial_path + '#/candidates/' + str(index) + '/content',
            commercial_path, c['type'])
    manifests = sorted(p for p in files if re.fullmatch(
        r'wordpress/docs/editorial/batches/(?:cases|services|public-articles|drafts)-[0-9]{2}/manifest\.json', p))
    manifests.insert(0, 'wordpress/docs/editorial/pilot/articles/manifest.json')
    for path in manifests:
        data = json.loads(read(path))
        if data.get('production_authority', False) is not False:
            raise ValueError('No production authority may be declared')
        for c in data['candidates']:
            if not re.fullmatch(r'[a-z0-9]+(?:-[a-z0-9]+)*', c['slug']) or c['file'] != c['slug'] + '.html':
                raise ValueError('Unsafe candidate filename')
            target = str(Path(path).parent / c['file'])
            kind = 'post' if path.startswith('wordpress/docs/editorial/pilot/articles/') else c['type']
            add(c, read(target), target, path, kind)
    return {
        'source_git_commit': commit,
        'baseline_site': 'https://sy3.ru/',
        'production_authority': False,
        'runtime_or_queue_changed': False,
        'baseline_objects': len(captured),
        'prepared_objects': len(prepared),
        'prepared_by_type': dict(sorted(Counter(c['type'] for c in prepared.values()).items())),
        'length_note': 'Article counts are manifest declarations; actual plugin checks and independent review are separate evidence. No publisher approval is created.',
        'candidates': [prepared[k] for k in sorted(prepared)],
        'body_not_prepared': [{'id': int(r['ID']), 'type': r['post_type'], 'slug': r['post_name']}
                              for r in captured if int(r['ID']) not in prepared],
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('baseline', type=Path)
    parser.add_argument('--commit', required=True)
    args = parser.parse_args()
    result = build(json.loads(args.baseline.read_text()), args.commit)
    output = REPO / 'wordpress/docs/editorial/BATCH_INDEX.json'
    output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps({key: result[key] for key in ['source_git_commit', 'prepared_objects', 'prepared_by_type']}))


if __name__ == '__main__':
    main()
