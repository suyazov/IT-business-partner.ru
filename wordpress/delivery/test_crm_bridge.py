import contextlib
import copy
import json
import sqlite3
import tempfile
import time
import unittest
from pathlib import Path
from unittest.mock import patch

import crm_bridge as crm


class Store:
    def __init__(self, queue=None, jobs=None):
        self.states, self.queue, self.jobs = {}, queue, jobs or {}

    def read_state(self, name):
        return copy.deepcopy(self.states.get(name))

    def write_state(self, name, value):
        self.states[name] = copy.deepcopy(value)

    def job(self, name, now):
        return self.jobs[name], 'unused'

    def lock(self):
        return contextlib.nullcontext()


class API:
    def __init__(self):
        self.items, self.comments, self.calls = {2: [], 3: []}, [], []
        self.lose_reply, self.reject_write, self.unknown_without_write = None, False, False

    def call(self, method, data):
        self.calls.append((method, copy.deepcopy(data)))
        if method == 'crm.item.list':
            rows = self.items[data['entityTypeId']]
            def matches(row):
                for key, value in data['filter'].items():
                    key = key.lstrip('=')
                    if key == 'contactId':
                        if value not in row.get('contactIds', []): return False
                    elif row.get(key) != value: return False
                return True
            return {'items': copy.deepcopy([r for r in rows if matches(r)])}
        if method == 'crm.duplicate.findbycomm':
            ids = [r['id'] for r in self.items[3] if any(f['value'] in data['values'] for f in r.get('fm', []))]
            return {'CONTACT': ids}
        if method == 'crm.item.add':
            entity = data['entityTypeId']
            if self.reject_write: raise crm.Rejected()
            if self.unknown_without_write: raise crm.Unknown()
            row = dict(data['fields'], id=len(self.items[entity]) + 1, closed='N')
            self.items[entity].append(row)
            if self.lose_reply == entity:
                self.lose_reply = None
                raise crm.Unknown()
            return {'item': copy.deepcopy(row)}
        if method == 'crm.timeline.comment.add':
            row = dict(data['fields'], ID=len(self.comments) + 1)
            self.comments.append(row)
            if self.lose_reply == 'comment':
                self.lose_reply = None
                raise crm.Unknown()
            return row['ID']
        if method == 'crm.timeline.comment.list':
            return copy.deepcopy([c for c in self.comments if c['ENTITY_ID'] == data['filter']['ENTITY_ID']])
        raise AssertionError(method)


def form(number=1, contact='customer@example.invalid'):
    return crm.form_job({'id': str(number).zfill(64), 'name': 'Тест', 'message': 'Нужен сайт <script>',
        'contact': contact, 'page': 'https://sy3.ru/services/website/', 'utm': {'utm_source': 'test'},
        'created_at': int(time.time())})


class CRMTests(unittest.TestCase):
    def setUp(self):
        self.store, self.api = Store(), API()
        self.bridge = crm.Bridge(self.store, self.api, {})

    def test_new_lead_and_replay_create_one_contact_and_deal(self):
        job = form()
        self.assertEqual(self.bridge.deliver(job), 'sent')
        self.assertEqual(self.bridge.deliver(job), 'sent')
        self.assertEqual(len(self.api.items[3]), 1)
        self.assertEqual(len(self.api.items[2]), 1)
        deal = self.api.items[2][0]
        self.assertEqual((deal['categoryId'], deal['stageId'], deal['assignedById']), (8, 'C8:NEW', 1))
        self.assertEqual(deal['utmSource'], 'test')
        self.assertIn('https://sy3.ru/services/website/', deal['comments'])
        self.assertNotIn('<script>', deal['comments'])
        self.assertEqual(self.api.comments, [])

    def test_followup_uses_open_deal_and_adds_one_comment(self):
        self.bridge.deliver(form())
        self.bridge.deliver(form(2))
        self.bridge.deliver(form(2))
        self.assertEqual((len(self.api.items[3]), len(self.api.items[2]), len(self.api.comments)), (1, 1, 1))

    def test_closed_deal_is_not_reopened(self):
        self.bridge.deliver(form())
        self.api.items[2][0]['closed'] = 'Y'
        self.bridge.deliver(form(2))
        self.assertEqual(len(self.api.items[2]), 2)
        self.assertEqual(self.api.items[2][0]['closed'], 'Y')

    def test_lost_contact_or_deal_reply_recovers_without_duplicate(self):
        for entity in [3, 2]:
            with self.subTest(entity=entity):
                store, api = Store(), API(); api.lose_reply = entity
                bridge = crm.Bridge(store, api, {})
                self.assertEqual(bridge.deliver(form()), 'uncertain')
                self.assertEqual(bridge.deliver(form()), 'sent')
                self.assertEqual((len(api.items[3]), len(api.items[2])), (1, 1))

    def test_unknown_write_without_remote_record_is_never_repeated(self):
        self.api.unknown_without_write = True
        self.assertEqual(self.bridge.deliver(form()), 'uncertain')
        self.assertEqual(self.bridge.deliver(form()), 'uncertain')
        self.assertEqual(self.bridge.deliver(form(2)), 'uncertain')
        self.assertEqual(sum(m == 'crm.item.add' for m, _ in self.api.calls), 1)

    def test_lost_timeline_reply_recovers_without_second_comment(self):
        self.bridge.deliver(form())
        self.api.lose_reply = 'comment'
        self.assertEqual(self.bridge.deliver(form(2)), 'uncertain')
        self.assertEqual(self.bridge.deliver(form(2)), 'sent')
        self.assertEqual(len(self.api.comments), 1)

    def test_ambiguous_contacts_are_not_merged(self):
        self.api.items[3] = [{'id': i, 'fm': [{'value': 'customer@example.invalid'}]} for i in [1, 2]]
        self.assertEqual(self.bridge.deliver(form()), 'failed')
        self.assertEqual(len(self.api.items[2]), 0)

    def test_bot_messages_reuse_contact_and_keep_profile(self):
        row = {'id': 1, 'author': '123456', 'name': 'Тест', 'username': 'example_user',
               'body': 'Нужна CRM', 'created': int(time.time())}
        first = crm.bot_job('telegram', row)
        self.bridge.deliver(first)
        row['id'] = 2; row['body'] = 'Уточнение'
        self.bridge.deliver(crm.bot_job('telegram', row))
        self.assertEqual((len(self.api.items[3]), len(self.api.items[2])), (1, 1))
        self.assertIn('https://t.me/example_user', self.api.items[2][0]['comments'])
        self.assertNotIn('contact', self.api.items[3][0].get('fm', []))

    def test_invalid_contact_and_sender_are_rejected(self):
        with self.assertRaises(crm.Rejected): form(contact='not-a-contact')
        with self.assertRaises(crm.Rejected):
            crm.bot_job('telegram', {'author': '../escape', 'id': 1})

    def test_cutoff_and_bot_cursor_with_read_only_database(self):
        with tempfile.TemporaryDirectory(prefix='sy3-crm-test-') as tmp:
            root = Path(tmp); queue = root / 'queue'; queue.mkdir()
            cutoff = int(time.time()) - 30
            for ch in ['telegram', 'max']:
                d = root / ch; d.mkdir()
                with sqlite3.connect(d / 'inbox.sqlite3') as db:
                    db.execute('CREATE TABLE tickets(id INTEGER PRIMARY KEY,author TEXT,name TEXT,username TEXT,body TEXT,created INTEGER)')
                    if ch == 'telegram':
                        db.execute('INSERT INTO tickets VALUES(1,?,?,?,?,?)', ('123456', 'Тест', '', 'Старое', cutoff - 1))
                        db.execute('INSERT INTO tickets VALUES(2,?,?,?,?,?)', ('123456', 'Тест', '', 'Новое', cutoff + 1))
            store, api = Store(queue), API()
            result = crm.once(store, {'cutoff': cutoff}, api, root)
            self.assertEqual(result['sent'], 1)
            self.assertEqual(store.states['_crm_cursor_telegram.json']['last_id'], 2)
            crm.once(store, {'cutoff': cutoff}, api, root)
            self.assertEqual(len(api.items[2]), 1)


if __name__ == '__main__':
    unittest.main()
