#!/usr/bin/env python3
"""Build a private, immutable transfer intent from accepted Git and the audit baseline."""
import argparse
import hashlib
import json
import pathlib
import subprocess

ROOT = pathlib.Path(__file__).resolve().parents[2]
SOURCE = 'f9904de5763c57fe127ed00c8f713a82f69125ed'
BASELINE_SHA = '0d68d6544efd1bdb3ed0a4e801b3b9f329277674f2726ec7b09c5f8c6a62e50b'
OLD_SUPPORT = 'a74fcd23af7f75ba784020b5067ddfa652a7ed50'


def digest(data):
    return hashlib.sha256(data).hexdigest()


def blob(path, commit=SOURCE):
    assert path.startswith('wordpress/') and '..' not in pathlib.PurePosixPath(path).parts
    return subprocess.check_output(['git', '-C', str(ROOT), 'show', commit + ':' + path])


def document(path):
    return json.loads(blob(path))


def build(baseline):
    assert baseline['site'] == 'https://sy3.ru/'
    originals = {int(r['ID']): r for r in baseline['rows']}
    assert len(originals) == 360
    bodies = {}
    for row in document('wordpress/docs/editorial/BATCH_INDEX.json')['candidates']:
        pointer = row['new_version'].split('#', 1)
        data = blob(pointer[0])
        if len(pointer) == 2:
            value = json.loads(data)
            for part in pointer[1].strip('/').split('/'):
                value = value[int(part)] if isinstance(value, list) else value[part]
            data = value.encode()
        assert digest(data) == row['candidate_hash']
        old = originals[row['id']]
        assert old['body_hash'] == row['baseline_hash']
        assert (old['post_type'], old['post_name']) == (row['type'], row['slug'])
        bodies[row['id']] = data.decode()
    assert len(bodies) == 357
    surfaces = {}
    for name in ['commercial-01', 'articles-commercial', 'articles-finance', 'articles-pilot']:
        for row in document('wordpress/docs/editorial/surfaces/' + name + '/manifest.json')['candidates']:
            assert row['id'] not in surfaces
            surfaces[row['id']] = row
    assert surfaces.keys() == originals.keys()
    posts = []
    for item_id, old in sorted(originals.items()):
        row = surfaces[item_id]
        before = {'post_title': old['post_title'], 'post_excerpt': old['post_excerpt'],
                  '_sy3_description': old['meta']['_sy3_description']}
        assert row['before'] == before and row['baseline_body_hash'] == old['body_hash']
        changes = {k: v for k, v in row['after'].items() if before[k] != v}
        if item_id in bodies and bodies[item_id] != old['post_content']:
            changes['post_content'] = bodies[item_id]
        posts.append({'id': item_id, 'type': old['post_type'], 'slug': old['post_name'],
                      'baseline_body_hash': old['body_hash'], 'baseline_fields': before, 'changes': changes})
    covers = document('wordpress/docs/editorial/surfaces/cover-alt/manifest.json')['candidates']
    assert len(covers) == 37
    support_path = 'wordpress/theme/inc/article-support-copy.json'
    return {'schema': 1, 'source_commit': SOURCE, 'baseline_sha256': BASELINE_SHA,
            'site': 'https://sy3.ru/', 'posts': posts, 'covers': covers,
            'support': {'source_path': support_path, 'old_commit': OLD_SUPPORT,
                        'before_sha256': digest(blob(support_path, OLD_SUPPORT)),
                        'after_sha256': digest(blob(support_path))},
            'protected': ['all other post fields', 'all other metadata including seed/approval hashes',
                          'terms', 'URLs', 'menus', 'queue options', 'images', 'other site']}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('baseline', type=pathlib.Path)
    parser.add_argument('output', type=pathlib.Path)
    args = parser.parse_args()
    data = args.baseline.read_bytes()
    assert digest(data) == BASELINE_SHA
    result = build(json.loads(data))
    assert not args.output.exists(), 'Do not replace a reviewed/approved intent'
    with args.output.open('x') as stream:
        stream.write(json.dumps(result, ensure_ascii=False, separators=(',', ':')) + '\n')
    args.output.chmod(0o600)
    print(json.dumps({'intent_sha256': digest(args.output.read_bytes()),
                      'objects': len(result['posts']), 'source': SOURCE}))
