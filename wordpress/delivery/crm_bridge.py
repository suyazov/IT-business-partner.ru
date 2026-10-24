"""Fixed-portal CRM delivery; never sends a message to a customer.

Uses the existing private lead Store/lock. Public bot databases are read-only.
Unknown writes are reconciled by stable external IDs, never blindly repeated.
"""
import hashlib
import html
import json
import os
import re
import sqlite3
import ssl
import time
import urllib.error
import urllib.request
from pathlib import Path

import dispatcher as base

CONFIG = Path('/root/.config/client-access/sy3.ru/bitrix24-rest.json')
BOT_ROOT = Path('/var/lib/sy3-support-public')
ORIGIN = 'SY3_WEBSITE'
METHODS = {'crm.item.list', 'crm.item.add', 'crm.duplicate.findbycomm',
           'crm.timeline.comment.list', 'crm.timeline.comment.add'}


class Unknown(Exception):
    pass


class Retry(Exception):
    pass


class Rejected(Exception):
    pass


def sha(value):
    return hashlib.sha256(value.encode()).hexdigest()


def positive(value):
    return type(value) is int and value > 0


def load_config():
    try:
        value = base.strict_json(base.protected_read(CONFIG))
    except FileNotFoundError:
        return None
    if (not isinstance(value, dict) or set(value) != {'version', 'webhook_url', 'assigned_by_id',
            'cutoff', 'category_id', 'stage_id'} or value['version'] != 1
            or not isinstance(value['webhook_url'], str)
            or not re.fullmatch(r'https://sy3\.bitrix24\.ru/rest/1/[A-Za-z0-9]{8,128}/', value['webhook_url'])
            or value['assigned_by_id'] != 1 or not positive(value['cutoff'])
            or value['category_id'] != 8 or value['stage_id'] != 'C8:NEW'):
        raise base.SafeError('invalid_crm_configuration')
    return value


class API:
    def __init__(self, config, deadline):
        self.config, self.deadline = config, deadline
        self.not_before = 0
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), base.NoRedirect(),
                                                 urllib.request.HTTPSHandler(context=ssl.create_default_context()))

    def call(self, method, data):
        if method not in METHODS:
            raise Rejected()
        wait = max(0, self.not_before - time.monotonic())
        if self.deadline - time.monotonic() < wait + 0.1:
            raise Retry()
        if wait:
            time.sleep(wait)
        self.not_before = time.monotonic() + 0.6
        seconds = min(12, self.deadline - time.monotonic())
        if seconds < 0.1:
            raise Retry()
        req = urllib.request.Request(self.config['webhook_url'] + method + '.json',
                                     data=json.dumps(data, ensure_ascii=False).encode(),
                                     headers={'Content-Type': 'application/json'}, method='POST')
        response = None
        try:
            with base.network_deadline(seconds):
                try:
                    response = self.opener.open(req, timeout=seconds)
                except urllib.error.HTTPError as exc:
                    response = exc
                if response.code == 429:
                    raise Retry()
                raw = response.read(512 * 1024 + 1)
                if len(raw) > 512 * 1024:
                    raise Unknown()
                if response.code >= 500:
                    raise Unknown()
                value = base.strict_json(raw)
                if not isinstance(value, dict):
                    raise Unknown()
                if value.get('error') is not None and not isinstance(value['error'], str):
                    raise Unknown()
                if value.get('error') in {'QUERY_LIMIT_EXCEEDED', 'OPERATION_TIME_LIMIT'}:
                    raise Retry()
                if value.get('error') or response.code in {400, 401, 403, 404}:
                    raise Rejected()
                if response.code != 200 or 'result' not in value:
                    raise Unknown()
                return value['result']
        except (base.UnknownSend, base.SafeError, OSError, ValueError):
            raise Unknown() from None
        finally:
            if response is not None:
                response.close()


def form_job(job):
    contact = job['contact'].strip()
    is_email = '@' in contact
    if is_email and not re.fullmatch(r'[^\s@]+@[^\s@]+\.[^\s@]+', contact):
        raise Rejected()
    normalized = contact.lower() if is_email else '+' + re.sub(r'\D', '', contact)
    if not is_email and not re.fullmatch(r'\+[1-9][0-9]{9,14}', normalized):
        raise Rejected()
    return {'key': 'form:' + job['id'], 'person': sha(('email:' if is_email else 'phone:') + normalized),
            'name': job.get('name') or 'Клиент с sy3.ru', 'body': job.get('message', ''),
            'source': 'Форма sy3.ru', 'contact': normalized, 'type': 'EMAIL' if is_email else 'PHONE',
            'page': job['page'], 'profile': '', 'utm': job.get('utm', {}), 'created': job['created_at']}


def bot_job(channel, row):
    author = str(row['author'])
    if (channel not in {'telegram', 'max'} or not re.fullmatch(r'[1-9][0-9]{0,18}', author)
            or not positive(row['id']) or not isinstance(row['body'], str)
            or not 1 <= len(row['body']) <= 2000 or not isinstance(row['name'], str)
            or len(row['name']) > 120 or not isinstance(row['username'], str)):
        raise Rejected()
    username = row['username'].lstrip('@')
    profile = 'https://t.me/' + username if channel == 'telegram' and re.fullmatch(r'[A-Za-z0-9_]{5,32}', username) else ''
    return {'key': channel + ':' + str(row['id']), 'person': sha(channel + ':' + author),
            'name': row['name'] or 'Клиент ' + channel.upper(), 'body': row['body'],
            'source': 'Бот ' + channel.upper(), 'contact': '', 'type': '', 'page': 'https://sy3.ru/',
            'profile': profile, 'utm': {}, 'created': int(row['created'])}


def message(job):
    # HTML escaping keeps source messages as text in CRM's rich-text fields.
    lines = ['Обращение SY3 ' + job['key'], job['source'], job['page']]
    if job['profile']:
        lines.append(job['profile'])
    lines.append(job['body'])
    return '<br>'.join(html.escape(line, quote=True) for line in lines)


class Bridge:
    def __init__(self, store, api, config):
        self.store, self.api, self.config = store, api, config

    def find(self, entity, filters):
        result = self.api.call('crm.item.list', {'entityTypeId': entity,
            'filter': filters, 'select': ['id', 'originatorId', 'originId'], 'start': 0})
        if not isinstance(result, dict) or not isinstance(result.get('items'), list):
            raise Unknown()
        rows = result['items']
        if len(rows) > 1:
            raise Rejected()
        if rows and (not isinstance(rows[0], dict) or not positive(rows[0].get('id'))):
            raise Unknown()
        return rows[0] if rows else None

    def external(self, entity, key):
        row = self.find(entity, {'=originatorId': ORIGIN, '=originId': key})
        if row and (row.get('originatorId') != ORIGIN or row.get('originId') != key):
            raise Rejected()
        return row

    def add(self, entity, fields):
        value = self.api.call('crm.item.add', {'entityTypeId': entity, 'fields': fields})
        item = value.get('item') if isinstance(value, dict) else None
        if not isinstance(item, dict) or not positive(item.get('id')):
            raise Unknown()
        return item['id']

    def contact(self, job, person, person_file):
        origin = 'contact:' + job['person']
        if positive(person.get('contact_id')):
            return person['contact_id']
        found = self.external(3, origin)
        if found:
            person.update(contact_id=found['id'], pending_contact=False)
            self.store.write_state(person_file, person)
            return found['id']
        if person.get('pending_contact'):
            raise Unknown()
        if job['contact']:
            result = self.api.call('crm.duplicate.findbycomm',
                {'entity_type': 'CONTACT', 'type': job['type'], 'values': [job['contact']]})
            # Bitrix returns [] for no duplicates, or an object keyed by entity.
            if result == []:
                ids = []
            elif isinstance(result, dict) and set(result) <= {'CONTACT'}:
                ids = result.get('CONTACT', [])
            else:
                raise Unknown()
            if not isinstance(ids, list) or any(not positive(i) for i in ids):
                raise Unknown()
            if len(ids) > 1:
                raise Rejected()
            if ids:
                person['contact_id'] = ids[0]
                self.store.write_state(person_file, person)
                return ids[0]
        person['pending_contact'] = True
        self.store.write_state(person_file, person)
        fields = {'name': job['name'], 'assignedById': 1, 'opened': 'N',
                  'originatorId': ORIGIN, 'originId': origin, 'comments': html.escape(job['profile'])}
        if job['contact']:
            fields['fm'] = [{'typeId': job['type'], 'valueType': 'WORK', 'value': job['contact']}]
        try:
            cid = self.add(3, fields)
        except (Retry, Rejected):
            person['pending_contact'] = False
            self.store.write_state(person_file, person)
            raise
        person.update(contact_id=cid, pending_contact=False)
        self.store.write_state(person_file, person)
        return cid

    def deliver(self, job):
        state_file = 'crm-job-' + sha(job['key']) + '.json'
        checksum = sha(json.dumps(job, sort_keys=True, ensure_ascii=True))
        state = self.store.read_state(state_file) or {'version': 1, 'checksum': checksum, 'status': 'retry', 'phase': None}
        if state.get('version') != 1 or state.get('checksum') != checksum:
            raise Rejected()
        if state['status'] == 'sent':
            return 'sent'
        if state['status'] == 'failed':
            return 'failed'
        person_file = 'crm-person-' + job['person'] + '.json'
        person = self.store.read_state(person_file) or {'version': 1}
        try:
            # Reconcile an uncertain comment against its original deal before
            # looking for any current/open deal. The original may now be closed.
            if state.get('phase') == 'comment':
                if not positive(state.get('deal_id')):
                    raise Unknown()
                comments = self.api.call('crm.timeline.comment.list', {'filter': {
                    'ENTITY_ID': state['deal_id'], 'ENTITY_TYPE': 'deal'},
                    'select': ['ID', 'COMMENT'], 'order': {'ID': 'DESC'}})
                if not isinstance(comments, list) or any(not isinstance(c, dict) for c in comments):
                    raise Unknown()
                found = [c for c in comments if c.get('COMMENT') == message(job)]
                if len(found) != 1 or not re.fullmatch(r'[1-9][0-9]*', str(found[0].get('ID', ''))):
                    raise Unknown()
                state.update(comment_id=int(found[0]['ID']), status='sent', phase=None,
                             confirmed_at=int(time.time()))
                self.store.write_state(state_file, state)
                return 'sent'
            cid = self.contact(job, person, person_file)
            deal = self.external(2, job['key'])
            pending = person.get('pending_deal')
            if not deal and pending:
                deal = self.external(2, pending)
                if not deal:
                    raise Unknown()
            if deal and pending:
                person.pop('pending_deal', None)
                self.store.write_state(person_file, person)
            if not deal:
                deal = self.find(2, {'contactId': cid, 'categoryId': 8, 'closed': 'N'})
            if not deal:
                person['pending_deal'] = job['key']
                self.store.write_state(person_file, person)
                fields = {'title': 'SY3 — ' + job['name'][:120], 'categoryId': 8, 'stageId': 'C8:NEW',
                          'assignedById': 1, 'opened': 'N', 'contactIds': [cid], 'sourceId': 'WEB',
                          'sourceDescription': job['source'], 'originatorId': ORIGIN,
                          'originId': job['key'], 'comments': message(job)}
                for source, dest in [('utm_source','utmSource'), ('utm_medium','utmMedium'),
                                     ('utm_campaign','utmCampaign'), ('utm_content','utmContent'), ('utm_term','utmTerm')]:
                    if source in job['utm']:
                        fields[dest] = job['utm'][source]
                try:
                    did = self.add(2, fields)
                except (Retry, Rejected):
                    person.pop('pending_deal', None)
                    self.store.write_state(person_file, person)
                    raise
                deal = {'id': did, 'originId': job['key']}
                person.pop('pending_deal', None)
                self.store.write_state(person_file, person)
            state.update(contact_id=cid, deal_id=deal['id'])
            if deal.get('originId') != job['key']:
                state.update(status='sending', phase='comment')
                self.store.write_state(state_file, state)
                try:
                    mid = self.api.call('crm.timeline.comment.add', {'fields': {
                        'ENTITY_ID': deal['id'], 'ENTITY_TYPE': 'deal', 'COMMENT': message(job)}})
                except (Retry, Rejected):
                    state['phase'] = None
                    raise
                if not positive(mid):
                    raise Unknown()
                state['comment_id'] = mid
            state.update(status='sent', phase=None, confirmed_at=int(time.time()))
        except Retry:
            state['status'] = 'retry'
        except Rejected:
            state['status'] = 'uncertain' if state.get('phase') or person.get('pending_contact') or person.get('pending_deal') else 'failed'
        except Unknown:
            state['status'] = 'uncertain'
        self.store.write_state(state_file, state)
        return state['status']


def once(store, config=None, api=None, bot_root=BOT_ROOT):
    config = config or load_config()
    if config is None:
        return {'enabled': False}
    deadline = time.monotonic() + 75
    bridge = Bridge(store, api or API(config, deadline), config)
    stats = {'enabled': True, 'sent': 0, 'retry': 0, 'uncertain': 0, 'failed': 0, 'skipped': 0}
    with store.lock():
        for name in sorted(os.listdir(store.queue)):
            if time.monotonic() >= deadline - 1:
                break
            if not re.fullmatch(r'[a-f0-9]{64}\.json', name):
                continue
            try:
                job, _ = store.job(name, time.time())
                if job['created_at'] < config['cutoff']:
                    continue
                result = bridge.deliver(form_job(job))
                stats[result] += 1
            except (base.SafeError, Rejected, FileNotFoundError):
                stats['skipped'] += 1
        for channel in ['telegram', 'max']:
            if time.monotonic() >= deadline - 1:
                break
            cursor_file = '_crm_cursor_' + channel + '.json'
            cursor = store.read_state(cursor_file) or {'version': 1, 'last_id': 0}
            database = bot_root / channel / 'inbox.sqlite3'
            # No immutable=1: read current committed WAL without creating a snapshot.
            try:
                with sqlite3.connect('file:' + str(database) + '?mode=ro', uri=True, timeout=1) as db:
                    db.execute('PRAGMA query_only=ON')
                    db.row_factory = sqlite3.Row
                    rows = db.execute('SELECT id,author,name,username,body,created FROM tickets '
                                      'WHERE created>=? AND id>? ORDER BY id LIMIT 100',
                                      (config['cutoff'], cursor['last_id'])).fetchall()
                for row in rows:
                    if time.monotonic() >= deadline - 1:
                        break
                    result = bridge.deliver(bot_job(channel, dict(row)))
                    stats[result] += 1
                    if result in {'retry', 'uncertain'}:
                        break
                    cursor['last_id'] = row['id']
                    store.write_state(cursor_file, cursor)
            except (sqlite3.Error, OSError, Rejected):
                stats['skipped'] += 1
    return stats
