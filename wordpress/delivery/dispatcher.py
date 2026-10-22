#!/usr/bin/env python3
"""Root-only SY3 lead delivery. Standard library; no WordPress execution.

The CLI has fixed paths and recipients come only from root's protected config.
Tests inject an isolated Store and transport; the production CLI has no dry-send,
arbitrary endpoint, token, recipient, or storage override.
"""
from __future__ import annotations

import argparse
import contextlib
import dataclasses
import fcntl
import hashlib
import json
import math
import os
from pathlib import Path
import pwd
import re
import secrets
import shlex
import signal
import ssl
import stat
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path('/var/lib/sy3-wordpress/leads')
CONFIG = Path('/root/.config/client-access/sy3.ru/lead-delivery.json')
ACCESS = Path('/root/.config/client-access/sy3-support')
TELEGRAM_ENV = ACCESS / 'telegram-bot.env'
MAX_ENV = ACCESS / 'max.env'
MAX_CA = ACCESS / 'max-ca.pem'
MAX_ENDPOINT = 'https://platform-api2.max.ru/messages'
ID = re.compile(r'^[a-f0-9]{64}$')
UTM_KEYS = ('utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content')
CHANNELS = ('telegram', 'max')
STATES = {'retry', 'sending', 'sent', 'failed', 'uncertain'}
REASONS = {'queued', 'confirmed', 'rate_limited', 'api_rejected', 'tls_failed',
           'unconfirmed', 'interrupted', 'network_unknown'}
JOB_LIMIT = 16 * 1024
MAX_RESPONSE = 64 * 1024
MAX_JOBS = 20
RUN_SECONDS = 75
NETWORK_SECONDS = 12
MAX_GAP = 0.55


class SafeError(Exception):
    """Only a constant, non-sensitive diagnostic code may leave the process."""


class Busy(SafeError):
    pass


class UnknownSend(Exception):
    pass


class TLSFailure(Exception):
    pass


def strict_json(raw: bytes):
    def pairs(items):
        result = {}
        for key, value in items:
            if key in result:
                raise SafeError('invalid_json')
            result[key] = value
        return result
    try:
        result = json.loads(raw.decode('utf-8'), object_pairs_hook=pairs,
                            parse_constant=lambda _: (_ for _ in ()).throw(SafeError('invalid_json')))
        stack, nodes = [(result, 0)], 0
        while stack:
            value, depth = stack.pop()
            nodes += 1
            if depth > 16 or nodes > 4096:
                raise SafeError('invalid_json')
            if isinstance(value, dict):
                stack.extend((v, depth + 1) for v in value.values())
            elif isinstance(value, list):
                stack.extend((v, depth + 1) for v in value)
        return result
    except (UnicodeError, ValueError, RecursionError):
        raise SafeError('invalid_json') from None


def open_directory(path: Path) -> int:
    """Traverse using directory FDs; no parent or final symlink is followed."""
    path = Path(path)
    if not path.is_absolute() or '..' in path.parts:
        raise SafeError('unsafe_directory')
    fd = os.open('/', os.O_RDONLY | os.O_DIRECTORY | os.O_CLOEXEC)
    try:
        for part in path.parts[1:]:
            nxt = os.open(part, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC,
                          dir_fd=fd)
            os.close(fd)
            fd = nxt
        return fd
    except OSError:
        os.close(fd)
        raise SafeError('unsafe_directory') from None


def require_directory(fd: int, uid: int, gid: int, mode: int):
    s = os.fstat(fd)
    if not stat.S_ISDIR(s.st_mode) or (s.st_uid, s.st_gid, stat.S_IMODE(s.st_mode)) != (uid, gid, mode):
        raise SafeError('unsafe_directory')


def file_stat(s, uid, mode, gid=None):
    if (not stat.S_ISREG(s.st_mode) or s.st_nlink != 1 or s.st_uid != uid
            or stat.S_IMODE(s.st_mode) != mode or (gid is not None and s.st_gid != gid)):
        raise SafeError('unsafe_file')


def read_file(fd: int, name: str, *, uid: int, mode: int, limit: int, gid=None) -> bytes:
    if '/' in name or name in ('.', '..'):
        raise SafeError('unsafe_file')
    try:
        file_fd = os.open(name, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK | os.O_CLOEXEC,
                          dir_fd=fd)
    except FileNotFoundError:
        raise
    except OSError:
        raise SafeError('unsafe_file') from None
    try:
        before = os.fstat(file_fd)
        file_stat(before, uid, mode, gid)
        if before.st_size > limit:
            raise SafeError('oversized_file')
        chunks, size = [], 0
        while size <= limit:
            part = os.read(file_fd, min(4096, limit + 1 - size))
            if not part:
                break
            chunks.append(part)
            size += len(part)
        after = os.fstat(file_fd)
        identity = lambda s: (s.st_dev, s.st_ino, s.st_uid, s.st_gid, s.st_mode,
                              s.st_nlink, s.st_size, s.st_mtime_ns, s.st_ctime_ns)
        if size > limit or size != before.st_size or identity(before) != identity(after):
            raise SafeError('changed_file')
        return b''.join(chunks)
    finally:
        os.close(file_fd)


def protected_read(path: Path, limit=65536) -> bytes:
    fd = open_directory(path.parent)
    try:
        return read_file(fd, path.name, uid=0, mode=0o600, limit=limit)
    finally:
        os.close(fd)


def env_values(raw: bytes) -> dict:
    """A data parser, never shell expansion/source/eval. Unknown keys ignored."""
    result = {}
    try:
        for line in raw.decode('utf-8').splitlines():
            line = line.strip()
            if not line or line.startswith('#'):
                continue
            if line.startswith('export '):
                line = line[7:].lstrip()
            words = shlex.split(line, comments=True, posix=True)
            if len(words) != 1 or '=' not in words[0]:
                raise SafeError('invalid_env')
            key, value = words[0].split('=', 1)
            if not re.fullmatch(r'[A-Z][A-Z0-9_]*', key) or key in result:
                raise SafeError('invalid_env')
            result[key] = value
    except (UnicodeError, ValueError):
        raise SafeError('invalid_env') from None
    return result


@dataclasses.dataclass(repr=False)
class Settings:
    telegram_owner_id: int
    max_owner_id: int
    telegram_token: str
    max_token: str
    telegram_tls: ssl.SSLContext
    max_tls: ssl.SSLContext


def load_settings() -> Settings:
    config = strict_json(protected_read(CONFIG, 2048))
    if not isinstance(config, dict) or set(config) != {'version', 'telegram_owner_id', 'max_owner_id'}:
        raise SafeError('invalid_config')
    if type(config['version']) is not int or config['version'] != 1:
        raise SafeError('invalid_config')
    for field in ('telegram_owner_id', 'max_owner_id'):
        if type(config[field]) is not int or not 0 < config[field] < 2**63:
            raise SafeError('invalid_owner')
    tg = env_values(protected_read(TELEGRAM_ENV))
    mx = env_values(protected_read(MAX_ENV))
    tg_token, mx_token = tg.get('TELEGRAM_BOT_TOKEN', ''), mx.get('MAX_BOT_TOKEN', '')
    if not re.fullmatch(r'[0-9]+:[A-Za-z0-9_-]{16,200}', tg_token):
        raise SafeError('invalid_telegram_token')
    if not re.fullmatch(r'[!-~]{16,4096}', mx_token):
        raise SafeError('invalid_max_token')
    if mx.get('MAX_OWNER_ID') != str(config['max_owner_id']):
        raise SafeError('max_owner_mismatch')
    try:
        tg_tls = ssl.create_default_context()
        max_tls = ssl.create_default_context()
        max_tls.load_verify_locations(cadata=protected_read(MAX_CA, 131072).decode('ascii'))
        for context in (tg_tls, max_tls):
            if context.verify_mode != ssl.CERT_REQUIRED or not context.check_hostname:
                raise SafeError('unsafe_tls')
    except (ssl.SSLError, UnicodeError, ValueError):
        raise SafeError('invalid_ca') from None
    return Settings(config['telegram_owner_id'], config['max_owner_id'], tg_token, mx_token,
                    tg_tls, max_tls)


def text_field(value, maximum, *, multiline=False):
    if not isinstance(value, str) or len(value) > maximum:
        raise SafeError('invalid_schema')
    for char in value:
        if (ord(char) < 32 and (not multiline or char not in '\n\t')) or ord(char) == 127:
            raise SafeError('invalid_schema')
    # Unpaired surrogates cannot be encoded into a request.
    try:
        value.encode('utf-8')
    except UnicodeError:
        raise SafeError('invalid_schema') from None
    return value


def validate_job(raw: bytes, expected_id: str, now: float) -> dict:
    job = strict_json(raw)
    required = {'version', 'id', 'created_at', 'contact', 'page', 'consent'}
    if not isinstance(job, dict) or not required <= set(job) or set(job) - required - {'name', 'message', 'utm'}:
        raise SafeError('invalid_schema')
    if type(job['version']) is not int or job['version'] != 1 or job['id'] != expected_id:
        raise SafeError('invalid_schema')
    if not isinstance(job['id'], str) or not ID.fullmatch(job['id']):
        raise SafeError('invalid_schema')
    if type(job['created_at']) is not int or not 0 < job['created_at'] <= now + 300:
        raise SafeError('invalid_schema')
    if job['consent'] is not True or not text_field(job['contact'], 200).strip():
        raise SafeError('invalid_schema')
    text_field(job.get('name', ''), 120)
    text_field(job.get('message', ''), 2000, multiline=True)
    page = text_field(job['page'], 2048)
    try:
        url = urllib.parse.urlsplit(page)
        if (url.scheme != 'https' or url.netloc != 'sy3.ru' or not url.path.startswith('/')
                or '\\' in page or any(c.isspace() for c in page)):
            raise SafeError('invalid_schema')
    except ValueError:
        raise SafeError('invalid_schema') from None
    utm = job.get('utm', {})
    if not isinstance(utm, dict) or set(utm) - set(UTM_KEYS):
        raise SafeError('invalid_schema')
    for value in utm.values():
        text_field(value, 120)
    return job


def units(text):
    return len(text.encode('utf-16-le')) // 2


def shorten(text, maximum):
    if units(text) <= maximum:
        return text
    result, used = [], 0
    for char in text:
        count = 2 if ord(char) > 0xFFFF else 1
        if used + count > maximum - 1:
            break
        result.append(char)
        used += count
    return ''.join(result) + '…'


def format_message(job):
    head = ['Новая заявка SY3', 'SY3-' + job['id'][:12].upper()]
    if job.get('name'):
        head.append('Имя: ' + job['name'])
    head.append('Контакт: ' + job['contact'])
    tail = ['Страница: ' + shorten(job['page'], 800)]
    for key in UTM_KEYS:
        if job.get('utm', {}).get(key):
            tail.append(key + ': ' + shorten(job['utm'][key], 120))
    prefix, suffix = '\n'.join(head), '\n'.join(tail)
    budget = 3900 - units(prefix + '\nСообщение: \n' + suffix)
    message = shorten(job.get('message', '') or 'Не указано', budget)
    text = prefix + '\nСообщение: ' + message + '\n' + suffix
    if units(text) > 3900:
        raise SafeError('message_limit')
    return text


class Store:
    def __init__(self, root: Path, wp_uid: int, wp_gid: int, root_uid=0):
        self.root_uid, self.wp_uid, self.wp_gid = root_uid, wp_uid, wp_gid
        self.fds = []
        self.lock_fd = None
        try:
            self.base = open_directory(root)
            self.fds.append(self.base)
            require_directory(self.base, root_uid, wp_gid, 0o750)
            self.queue = os.open('queue', os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC,
                                 dir_fd=self.base)
            self.fds.append(self.queue)
            require_directory(self.queue, root_uid, wp_gid, 0o770)
            self.state = os.open('state', os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC,
                                 dir_fd=self.base)
            self.fds.append(self.state)
            require_directory(self.state, root_uid, wp_gid, 0o750)
        except (OSError, SafeError):
            self.close()
            raise SafeError('unsafe_storage') from None

    def close(self):
        if self.lock_fd is not None:
            os.close(self.lock_fd)
            self.lock_fd = None
        for fd in reversed(self.fds):
            os.close(fd)
        self.fds = []

    def __enter__(self):
        return self

    def __exit__(self, *args):
        self.close()

    @contextlib.contextmanager
    def lock(self):
        created = False
        try:
            try:
                fd = os.open('_worker.lock', os.O_RDWR | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW
                             | os.O_CLOEXEC, 0o640, dir_fd=self.state)
                created = True
            except FileExistsError:
                fd = os.open('_worker.lock', os.O_RDWR | os.O_NOFOLLOW | os.O_NONBLOCK
                             | os.O_CLOEXEC, dir_fd=self.state)
            self.lock_fd = fd
            if created:
                os.fchown(fd, self.root_uid, self.wp_gid)
                os.fchmod(fd, 0o640)
            file_stat(os.fstat(fd), self.root_uid, 0o640, self.wp_gid)
            try:
                fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except BlockingIOError:
                raise Busy('worker_busy') from None
            yield
        except OSError:
            raise SafeError('unsafe_lock') from None
        finally:
            if self.lock_fd is not None:
                os.close(self.lock_fd)
                self.lock_fd = None

    def job(self, name, now):
        if not re.fullmatch(r'[a-f0-9]{64}\.json', name):
            raise SafeError('invalid_filename')
        raw = read_file(self.queue, name, uid=self.wp_uid, mode=0o600, limit=JOB_LIMIT)
        return validate_job(raw, name[:-5], now), hashlib.sha256(raw).hexdigest()

    def read_state(self, name):
        try:
            value = strict_json(read_file(self.state, name, uid=self.root_uid, gid=self.wp_gid,
                                          mode=0o640, limit=16384))
            if not isinstance(value, dict):
                raise SafeError('invalid_state')
            return value
        except FileNotFoundError:
            return None

    def write_state(self, name, value):
        # Refuse to replace an existing unsafe target even though state/ is root-writable only.
        try:
            read_file(self.state, name, uid=self.root_uid, gid=self.wp_gid,
                      mode=0o640, limit=16384)
        except FileNotFoundError:
            pass
        raw = (json.dumps(value, ensure_ascii=True, separators=(',', ':')) + '\n').encode()
        if len(raw) > 16384:
            raise SafeError('state_limit')
        temp = '.tmp-' + secrets.token_hex(16)
        fd = None
        try:
            fd = os.open(temp, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW
                         | os.O_CLOEXEC, 0o600, dir_fd=self.state)
            os.fchown(fd, self.root_uid, self.wp_gid)
            os.fchmod(fd, 0o640)
            view = memoryview(raw)
            while view:
                count = os.write(fd, view)
                if count <= 0:
                    raise SafeError('state_write_failed')
                view = view[count:]
            os.fsync(fd)
            os.close(fd)
            fd = None
            os.replace(temp, name, src_dir_fd=self.state, dst_dir_fd=self.state)
            os.fsync(self.state)
        finally:
            if fd is not None:
                os.close(fd)
            try:
                os.unlink(temp, dir_fd=self.state)
            except FileNotFoundError:
                pass


def new_state(digest, now):
    return {'version': 1, 'job_sha256': digest, 'created_at': int(now), 'integrity_failed': False,
            'channels': {channel: {'status': 'retry', 'attempts': 0, 'next_attempt_at': 0,
                                    'reason': 'queued'} for channel in CHANNELS}}


def validate_state(value):
    if (not isinstance(value, dict) or set(value) != {'version', 'job_sha256', 'created_at',
            'integrity_failed', 'channels'} or type(value['version']) is not int or value['version'] != 1
            or not isinstance(value['job_sha256'], str) or not ID.fullmatch(value['job_sha256'])
            or type(value['created_at']) is not int or type(value['integrity_failed']) is not bool
            or not isinstance(value['channels'], dict) or set(value['channels']) != set(CHANNELS)):
        raise SafeError('invalid_state')
    for item in value['channels'].values():
        if (not isinstance(item, dict) or set(item) - {'status', 'attempts', 'next_attempt_at',
                'reason', 'message_id'} or not {'status', 'attempts', 'next_attempt_at', 'reason'} <= set(item)
                or not isinstance(item['status'], str) or item['status'] not in STATES
                or not isinstance(item['reason'], str) or item['reason'] not in REASONS
                or type(item['attempts']) is not int or not 0 <= item['attempts'] <= 1000000
                or type(item['next_attempt_at']) not in (int, float)
                or not math.isfinite(item['next_attempt_at']) or item['next_attempt_at'] < 0):
            raise SafeError('invalid_state')
        if item['status'] == 'sent' and not valid_message_id(item.get('message_id')):
            raise SafeError('invalid_state')
    return value


def valid_message_id(value):
    return ((type(value) is int and value > 0) or
            (isinstance(value, str) and re.fullmatch(r'[A-Za-z0-9_.:-]{1,256}', value) is not None))


@dataclasses.dataclass
class Reply:
    status: int
    payload: object
    retry_after: str = ''


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


@contextlib.contextmanager
def network_deadline(seconds):
    """A real total deadline, including slow response headers/body (Linux main thread)."""
    def expired(*_):
        raise UnknownSend()
    previous_handler = signal.getsignal(signal.SIGALRM)
    previous_timer = signal.setitimer(signal.ITIMER_REAL, 0)
    signal.signal(signal.SIGALRM, expired)
    signal.setitimer(signal.ITIMER_REAL, max(0.001, seconds))
    try:
        yield
    finally:
        signal.setitimer(signal.ITIMER_REAL, 0)
        signal.signal(signal.SIGALRM, previous_handler)
        if previous_timer[0]:
            signal.setitimer(signal.ITIMER_REAL, *previous_timer)


class Transport:
    def __init__(self, settings):
        self.settings = settings
        self.openers = {}
        for channel, context in [('telegram', settings.telegram_tls), ('max', settings.max_tls)]:
            if context.verify_mode != ssl.CERT_REQUIRED or not context.check_hostname:
                raise SafeError('unsafe_tls')
            self.openers[channel] = urllib.request.build_opener(
                urllib.request.ProxyHandler({}), NoRedirect(),
                urllib.request.HTTPSHandler(context=context))

    def send(self, channel, text, seconds):
        settings = self.settings
        if channel == 'telegram':
            endpoint = 'https://api.telegram.org/bot' + settings.telegram_token + '/sendMessage'
            payload = {'chat_id': settings.telegram_owner_id, 'text': text,
                       'disable_web_page_preview': True}
            headers = {'Content-Type': 'application/json; charset=utf-8'}
        elif channel == 'max':
            endpoint = MAX_ENDPOINT + '?user_id=' + str(settings.max_owner_id) + '&disable_link_preview=true'
            payload = {'text': text}
            headers = {'Content-Type': 'application/json; charset=utf-8', 'Authorization': settings.max_token}
        else:
            raise SafeError('invalid_channel')
        request = urllib.request.Request(endpoint, data=json.dumps(payload, ensure_ascii=False).encode(),
                                         headers=headers, method='POST')
        response = None
        try:
            with network_deadline(seconds):
                try:
                    response = self.openers[channel].open(request, timeout=seconds)
                except urllib.error.HTTPError as error:
                    response = error
                raw = response.read(MAX_RESPONSE + 1)
                if len(raw) > MAX_RESPONSE:
                    raise UnknownSend()
                try:
                    parsed = strict_json(raw)
                except SafeError:
                    parsed = None
                return Reply(response.code, parsed, response.headers.get('Retry-After', ''))
        except ssl.SSLCertVerificationError:
            raise TLSFailure() from None
        except urllib.error.URLError as error:
            if isinstance(error.reason, ssl.SSLCertVerificationError):
                raise TLSFailure() from None
            raise UnknownSend() from None
        except (OSError, ValueError):
            raise UnknownSend() from None
        finally:
            if response is not None:
                response.close()


def outcome(channel, reply):
    payload = reply.payload if isinstance(reply.payload, dict) else {}
    code = payload.get('error_code') if channel == 'telegram' and payload.get('ok') is False else reply.status
    if reply.status == 429 or code == 429:
        delay = None
        parameters = payload.get('parameters')
        if channel == 'telegram' and isinstance(parameters, dict):
            delay = parameters.get('retry_after')
        if type(delay) is not int or delay < 1:
            value = reply.retry_after
            delay = int(value) if isinstance(value, str) and re.fullmatch(r'[0-9]{1,8}', value) else 60
        return 'retry', 'rate_limited', None, max(1, min(delay, 3600))
    if reply.status == 200:
        if channel == 'telegram' and payload.get('ok') is True:
            result = payload.get('result')
            mid = result.get('message_id') if isinstance(result, dict) else None
            if type(mid) is int and mid > 0:
                return 'sent', 'confirmed', mid, 0
        if channel == 'max':
            message = payload.get('message')
            body = message.get('body') if isinstance(message, dict) else None
            mid = body.get('mid') if isinstance(body, dict) else None
            if isinstance(mid, str) and valid_message_id(mid):
                return 'sent', 'confirmed', mid, 0
    rejection = {400, 401, 403, 404, 405, 409, 410, 413, 415, 422}
    if reply.status in rejection or (channel == 'telegram' and payload.get('ok') is False
                                    and type(code) is int and code in rejection):
        return 'failed', 'api_rejected', None, 0
    return 'uncertain', 'unconfirmed', None, 0


class Clock:
    monotonic = staticmethod(time.monotonic)
    sleep = staticmethod(time.sleep)
    time = staticmethod(time.time)


class Dispatcher:
    def __init__(self, store, transport, clock=None):
        self.store, self.transport, self.clock = store, transport, clock or Clock()

    def once(self):
        stats = dict(jobs=0, sent=0, retry=0, failed=0, uncertain=0, rejected=0, integrity=0)
        deadline = self.clock.monotonic() + RUN_SECONDS
        with self.store.lock():
            control = self.store.read_state('_control.json')
            if control is None:
                control = {'version': 1, 'max_not_before': 0, 'telegram_not_before': 0}
            if (set(control) != {'version', 'max_not_before', 'telegram_not_before'}
                    or type(control['version']) is not int or control['version'] != 1
                    or any(type(control[key]) not in (int, float) or not math.isfinite(control[key])
                           or control[key] < 0 for key in ('max_not_before', 'telegram_not_before'))):
                raise SafeError('invalid_control')
            with os.scandir(self.store.queue) as entries:
                for entry in entries:
                    if stats['jobs'] >= MAX_JOBS or self.clock.monotonic() >= deadline - 0.1:
                        break
                    name = entry.name
                    if not re.fullmatch(r'[a-f0-9]{64}\.json', name):
                        stats['rejected'] += 1
                        continue
                    try:
                        job, digest = self.store.job(name, self.clock.time())
                        value = self.store.read_state(name)
                        state = new_state(digest, self.clock.time()) if value is None else validate_state(value)
                    except (SafeError, FileNotFoundError):
                        stats['rejected'] += 1
                        continue
                    changed = False
                    for item in state['channels'].values():
                        if item['status'] == 'sending':
                            item.update(status='uncertain', reason='interrupted', next_attempt_at=0)
                            changed = True
                    if state['job_sha256'] != digest:
                        state['integrity_failed'] = True
                        changed = True
                    if changed:
                        self.store.write_state(name, state)
                    if state['integrity_failed']:
                        stats['integrity'] += 1
                        continue
                    due = [ch for ch in CHANNELS if state['channels'][ch]['status'] == 'retry'
                           and state['channels'][ch]['next_attempt_at'] <= self.clock.time()]
                    if not due:
                        continue
                    stats['jobs'] += 1
                    text = format_message(job)
                    for channel in due:
                        remaining = deadline - self.clock.monotonic()
                        if remaining <= 0.1:
                            break
                        wait = max(0, control[channel + '_not_before'] - self.clock.time())
                        if wait + 0.1 >= remaining:
                            continue
                        if wait:
                            self.clock.sleep(wait)
                        # Queue is writable by PHP: detect replacement between channel attempts.
                        try:
                            _, current = self.store.job(name, self.clock.time())
                        except (SafeError, FileNotFoundError):
                            current = None
                        if current != digest:
                            state['integrity_failed'] = True
                            self.store.write_state(name, state)
                            stats['integrity'] += 1
                            break
                        item = state['channels'][channel]
                        item.update(status='sending', attempts=item['attempts'] + 1,
                                    next_attempt_at=0, reason='queued')
                        self.store.write_state(name, state)  # durable reservation BEFORE HTTP
                        seconds = min(NETWORK_SECONDS, deadline - self.clock.monotonic())
                        if seconds <= 0:
                            item.update(status='retry', reason='queued')
                            self.store.write_state(name, state)
                            break
                        if channel == 'max':
                            # A hard crash retains a conservative quiet period across restarts.
                            control['max_not_before'] = self.clock.time() + seconds + 1
                            self.store.write_state('_control.json', control)
                        try:
                            reply = self.transport.send(channel, text, seconds)
                            status, reason, mid, delay = outcome(channel, reply)
                        except TLSFailure:
                            status, reason, mid, delay = 'failed', 'tls_failed', None, 0
                        except UnknownSend:
                            status, reason, mid, delay = 'uncertain', 'network_unknown', None, 0
                        if channel == 'max' or status == 'retry':
                            control[channel + '_not_before'] = self.clock.time() + max(
                                MAX_GAP if channel == 'max' else 0, delay)
                            self.store.write_state('_control.json', control)
                        item.update(status=status, reason=reason,
                                    next_attempt_at=self.clock.time() + delay if delay else 0)
                        if mid is not None:
                            item['message_id'] = mid
                        self.store.write_state(name, state)
                        stats[status] += 1
        return stats


def main(argv=None):
    parser = argparse.ArgumentParser(description='SY3 private lead dispatcher')
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument('--check', action='store_true', help='validate local config/permissions/TLS; no HTTP')
    group.add_argument('--once', action='store_true', help='dispatch at most 20 jobs within 75 seconds')
    args = parser.parse_args(argv)
    if os.geteuid() != 0:
        print('{"error":"root_required"}')
        return 1
    try:
        wp = pwd.getpwnam('php-sy3')
        settings = load_settings()
        with Store(ROOT, wp.pw_uid, wp.pw_gid) as store:
            if args.check:
                print('{"check":"ok","network":false}')
            else:
                print(json.dumps(Dispatcher(store, Transport(settings)).once(), sort_keys=True))
        return 0
    except Busy:
        print('{"status":"worker_busy","network":false}')
        return 0
    except (SafeError, OSError, KeyError, ValueError):
        print('{"error":"local_validation_or_storage_failed"}')
        return 1
    except Exception:
        # No exception repr, response body, recipient, request URL, or credentials in journals.
        print('{"error":"dispatcher_interrupted_check_state"}')
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
