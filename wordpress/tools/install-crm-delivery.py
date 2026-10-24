#!/usr/bin/env python3
"""Scoped CRM rollout: reviewed source, owner/project locks, worker lock, no backups."""
import argparse
import fcntl
import hashlib
import json
import os
from pathlib import Path
import stat
import subprocess
import sys
import tempfile

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[2]
BEFORE = '25ccc3c'
FILES = [
    ('wordpress/delivery/crm_bridge.py', '/usr/local/lib/sy3-leads/crm_bridge.py'),
    ('wordpress/delivery/dispatcher.py', '/usr/local/lib/sy3-leads/dispatcher.py'),
    ('wordpress/plugin/sy3-leads.php', '/var/www/sy3-wordpress/wp-content/plugins/sy3-leads.php'),
    ('wordpress/deploy/systemd/sy3-leads.service', '/etc/systemd/system/sy3-leads.service'),
]


def git(*args):
    return subprocess.check_output(['git', '-C', str(ROOT), *args])


def main():
    parser = argparse.ArgumentParser()
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument('--check', action='store_true')
    group.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    if os.geteuid() != 0 or os.environ.get('SY3_CRM_ALLOW_PRODUCTION') != '1':
        raise RuntimeError('exact_crm_authority_required')
    head = git('rev-parse', 'HEAD').decode().strip()
    if head != os.environ.get('SY3_CRM_REVIEWED_HEAD') or git('status', '--porcelain').strip():
        raise RuntimeError('clean_exact_reviewed_source_required')
    pid = os.environ.get('SY3_CRM_LOCK_PID', '')
    held = Path('/proc/locks').read_text().splitlines()
    for name in ['suyazov-IT-business-partner.ru', 'sy3.ru', 'sy3-wordpress-content']:
        inode = str(Path('/var/lock/kimi-projects/' + name + '.lock').stat().st_ino)
        if not any(line.split()[4] == pid and line.split()[5].split(':')[-1] == inode for line in held):
            raise RuntimeError('project_lock_required')
    sys.path.insert(0, str(ROOT / 'wordpress/delivery'))
    import crm_bridge
    if crm_bridge.load_config() is None:
        raise RuntimeError('crm_configuration_required')
    planned = []
    for source, target in FILES:
        dest = Path(target)
        raw = (ROOT / source).read_bytes()
        if dest.resolve() != dest or dest.parent.is_symlink():
            raise RuntimeError('unsafe_runtime_path')
        if dest.exists():
            s = dest.lstat()
            if not stat.S_ISREG(s.st_mode) or s.st_uid != 0 or s.st_nlink != 1 or stat.S_IMODE(s.st_mode) != 0o644:
                raise RuntimeError('unsafe_runtime_file')
            before = git('show', BEFORE + ':' + source) if source != 'wordpress/delivery/crm_bridge.py' else b''
            if dest.read_bytes() not in [before, raw]:
                raise RuntimeError('runtime_conflict_preserve_existing')
        elif source != 'wordpress/delivery/crm_bridge.py':
            raise RuntimeError('expected_runtime_file_missing')
        planned.append((source, dest, raw, dest.read_bytes() if dest.exists() else None))
    if args.check:
        print(json.dumps({'preflight': 'PASS', 'files': len(planned), 'writes': 0, 'backups': 0}))
        return
    lock = os.open('/var/lib/sy3-wordpress/leads/state/_worker.lock', os.O_RDWR | os.O_NOFOLLOW)
    try:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        # No dispatcher can observe an incomplete module update while this lock is held.
        for source, dest, raw, before in planned:
            current = dest.read_bytes() if dest.exists() else None
            if current != before:
                raise RuntimeError('runtime_changed_after_preflight')
            if current == raw:
                continue
            fd, tmp = tempfile.mkstemp(prefix='.sy3-crm-', dir=dest.parent)
            try:
                with os.fdopen(fd, 'wb') as f:
                    f.write(raw); f.flush(); os.fsync(f.fileno())
                os.chmod(tmp, 0o644); os.chown(tmp, 0, 0)
                os.replace(tmp, dest)
            finally:
                if os.path.exists(tmp): os.unlink(tmp)
            if dest.read_bytes() != raw:
                raise RuntimeError('runtime_readback_failed')
        subprocess.run(['systemctl', 'daemon-reload'], check=True)
    finally:
        os.close(lock)
    subprocess.run(['/usr/bin/python3', '-B', '/usr/local/lib/sy3-leads/dispatcher.py', '--check'], check=True)
    print(json.dumps({'status': 'APPLIED_VERIFIED', 'source_head': head, 'backups': 0,
        'files': [{'path': str(dest), 'sha256': hashlib.sha256(raw).hexdigest()} for _, dest, raw, _ in planned],
        'bot_runtime_changed': False, 'timer_schedule_changed': False}))


if __name__ == '__main__':
    try:
        main()
    except Exception:
        # Do not print secrets, endpoint URLs, config contents or exception strings.
        print('{"error":"crm_rollout_stopped_preserve_existing"}', file=sys.stderr)
        raise SystemExit(1)
