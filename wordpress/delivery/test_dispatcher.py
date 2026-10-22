"""Offline unit tests. All leads, tokens, HTTP replies and storage are synthetic."""
import contextlib
import dataclasses
import io
import json
import multiprocessing
import os
from pathlib import Path
import ssl
import tempfile
import time
import types
import unittest
from unittest.mock import MagicMock, patch
import urllib.error

import dispatcher as d


class FakeClock:
    def __init__(self):
        self.wall, self.mono = 1800000000.0, 0.0
        self.sleeps = []

    def time(self):
        return self.wall

    def monotonic(self):
        return self.mono

    def advance(self, seconds):
        self.wall += seconds
        self.mono += seconds

    def sleep(self, seconds):
        self.sleeps.append(seconds)
        self.advance(seconds)


class FakeTransport:
    def __init__(self, clock, actions=None, before=None):
        self.clock, self.actions, self.before = clock, list(actions or []), before
        self.calls = []

    def send(self, channel, text, seconds):
        self.calls.append((channel, self.clock.time(), text, seconds))
        if self.before:
            self.before(channel, text)
        result = self.actions.pop(0) if self.actions else (
            d.Reply(200, {'ok': True, 'result': {'message_id': 101}}) if channel == 'telegram'
            else d.Reply(200, {'message': {'body': {'mid': 'mid.test-101'}}}))
        if callable(result):
            return result(channel, text, seconds)
        if isinstance(result, BaseException):
            raise result
        return result


def child_lock(root, uid, gid, ready, release):
    with d.Store(Path(root), uid, gid, root_uid=uid) as store:
        with store.lock():
            ready.set()
            release.wait(5)


class Fixture(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='offline-test-')
        self.addCleanup(self.tmp.cleanup)
        self.path = Path(self.tmp.name)
        self.root = self.path / 'leads'
        self.root.mkdir(mode=0o750)
        (self.root / 'queue').mkdir(mode=0o770)
        (self.root / 'state').mkdir(mode=0o750)
        for path, mode in [(self.root, 0o750), (self.root/'queue', 0o770), (self.root/'state', 0o750)]:
            path.chmod(mode)
        self.uid, self.gid = os.getuid(), os.getgid()
        self.clock = FakeClock()
        self.store = d.Store(self.root, self.uid, self.gid, root_uid=self.uid)
        self.addCleanup(self.store.close)

    def lead(self, number=1, **changes):
        job = dict(version=1, id=f'{number:064x}', created_at=int(self.clock.time()),
                   name='Учебный клиент', contact='test@example.invalid',
                   message='Проверить маршрут', page='https://sy3.ru/uslugi/',
                   utm={'utm_source': 'offline-test'}, consent=True)
        job.update(changes)
        file = self.root / 'queue' / (f'{number:064x}' + '.json')
        file.write_text(json.dumps(job, ensure_ascii=False))
        file.chmod(0o600)
        return job, file

    def state(self, number=1):
        return self.store.read_state(f'{number:064x}.json')

    def run_worker(self, actions=None, before=None):
        transport = FakeTransport(self.clock, actions, before)
        result = d.Dispatcher(self.store, transport, self.clock).once()
        return result, transport


class DeliveryTests(Fixture):
    def test_partial_success_then_only_failed_channel_retries(self):
        self.lead()
        result, first = self.run_worker([
            d.Reply(200, {'ok': True, 'result': {'message_id': 101}}),
            d.Reply(429, {'code': 'limit.hit'}, '5')])
        self.assertEqual((result['sent'], result['retry']), (1, 1))
        self.assertEqual(self.state()['channels']['telegram']['message_id'], 101)
        _, early = self.run_worker()
        self.assertEqual(early.calls, [])
        self.clock.advance(5.1)
        result, second = self.run_worker()
        self.assertEqual([x[0] for x in second.calls], ['max'])
        self.assertEqual(result['sent'], 1)
        self.assertEqual(self.state()['channels']['telegram']['attempts'], 1)
        self.assertEqual(self.state()['channels']['max']['attempts'], 2)
        self.assertEqual(len(first.calls), 2)

    def test_duplicate_complete_job_never_resent(self):
        self.lead()
        self.run_worker()
        result, transport = self.run_worker()
        self.assertEqual(transport.calls, [])
        self.assertEqual(result['jobs'], 0)

    def test_sending_persisted_before_each_network_call(self):
        self.lead()
        def inspect(channel, _):
            self.assertEqual(self.state()['channels'][channel]['status'], 'sending')
            self.assertEqual(self.state()['channels'][channel]['attempts'], 1)
        self.run_worker(before=inspect)
        file = self.root/'state'/f'{1:064x}.json'
        self.assertEqual(file.stat().st_mode & 0o777, 0o640)
        self.assertEqual((file.stat().st_uid, file.stat().st_gid), (self.uid, self.gid))
        self.assertNotIn('test@example.invalid', file.read_text())

    def test_crash_recovery_marks_uncertain_and_other_channel_continues(self):
        self.lead()
        crashing = FakeTransport(self.clock, [SystemExit('simulated crash')])
        with self.assertRaises(SystemExit):
            d.Dispatcher(self.store, crashing, self.clock).once()
        self.assertEqual(self.state()['channels']['telegram']['status'], 'sending')
        _, transport = self.run_worker()
        self.assertEqual([x[0] for x in transport.calls], ['max'])
        self.assertEqual(self.state()['channels']['telegram']['status'], 'uncertain')
        self.assertEqual(self.state()['channels']['telegram']['reason'], 'interrupted')
        _, again = self.run_worker()
        self.assertEqual(again.calls, [])

    def test_unknown_timeout_no_blind_retry(self):
        self.lead()
        result, _ = self.run_worker([d.UnknownSend(), d.UnknownSend()])
        self.assertEqual(result['uncertain'], 2)
        self.clock.advance(10000)
        _, transport = self.run_worker()
        self.assertEqual(transport.calls, [])

    def test_rate_limit_cooldown_applies_across_jobs_but_other_channel_progresses(self):
        self.lead(1)
        self.lead(2)
        result, transport = self.run_worker([
            d.Reply(429, {'ok': False, 'parameters': {'retry_after': 200}})])
        self.assertEqual([x[0] for x in transport.calls], ['telegram', 'max', 'max'])
        self.assertEqual(result['sent'], 2)
        self.assertEqual(self.state(2)['channels']['telegram']['attempts'], 0)

    def test_clear_rejection_failed_without_blocking_other_channel(self):
        self.lead()
        result, _ = self.run_worker([d.Reply(403, {'ok': False, 'error_code': 403})])
        self.assertEqual((result['failed'], result['sent']), (1, 1))
        self.clock.advance(10000)
        _, again = self.run_worker()
        self.assertFalse(again.calls)

    def test_changed_job_blocks_remaining_channel(self):
        job, file = self.lead()
        def replace_after_telegram(channel, _):
            if channel == 'telegram':
                job['message'] = 'Заменённый текст'
                file.write_text(json.dumps(job))
        result, transport = self.run_worker(before=replace_after_telegram)
        self.assertEqual([x[0] for x in transport.calls], ['telegram'])
        self.assertEqual(result['integrity'], 1)
        self.assertTrue(self.state()['integrity_failed'])
        self.assertEqual(self.state()['channels']['telegram']['status'], 'sent')
        _, again = self.run_worker()
        self.assertEqual(again.calls, [])

    def test_changed_completed_job_does_not_reset_receipts(self):
        _, file = self.lead()
        self.run_worker()
        original = self.state()['channels'].copy()
        file.write_text(file.read_text() + ' ')
        result, transport = self.run_worker()
        self.assertEqual(result['integrity'], 1)
        self.assertEqual(transport.calls, [])
        self.assertEqual(self.state()['channels'], original)

    def test_two_processes_cannot_dispatch_concurrently(self):
        self.lead()
        ctx = multiprocessing.get_context('fork')
        ready, release = ctx.Event(), ctx.Event()
        process = ctx.Process(target=child_lock, args=(str(self.root), self.uid, self.gid, ready, release))
        process.start()
        try:
            self.assertTrue(ready.wait(3))
            transport = FakeTransport(self.clock)
            with self.assertRaises(d.Busy):
                d.Dispatcher(self.store, transport, self.clock).once()
            self.assertEqual(transport.calls, [])
        finally:
            release.set()
            process.join(3)
            if process.is_alive():
                process.terminate()
                process.join()
        self.assertEqual(process.exitcode, 0)

    def test_max_spacing_persists_across_jobs_and_worker_runs(self):
        self.lead(1)
        self.lead(2)
        _, first = self.run_worker()
        max_calls = [x for x in first.calls if x[0] == 'max']
        self.assertGreaterEqual(max_calls[1][1] - max_calls[0][1], 0.5499)
        self.lead(3)
        _, second = self.run_worker()
        next_max = next(x for x in second.calls if x[0] == 'max')
        self.assertGreaterEqual(next_max[1] - max_calls[-1][1], 0.5499)

    def test_crash_at_max_keeps_cross_restart_quiet_period(self):
        self.lead(1)
        transport = FakeTransport(self.clock, [d.Reply(200, {'ok': True, 'result': {'message_id': 1}}), SystemExit()])
        with self.assertRaises(SystemExit):
            d.Dispatcher(self.store, transport, self.clock).once()
        self.lead(2)
        _, restarted = self.run_worker()
        max_send = next(x for x in restarted.calls if x[0] == 'max')
        self.assertGreaterEqual(max_send[1] - transport.calls[-1][1], d.NETWORK_SECONDS + 1)
        self.assertEqual(self.state(1)['channels']['max']['status'], 'uncertain')

    def test_at_most_twenty_jobs_per_run(self):
        for number in range(1, 26):
            self.lead(number)
        result, transport = self.run_worker()
        self.assertEqual(result['jobs'], 20)
        self.assertEqual(len(transport.calls), 40)
        result2, _ = self.run_worker()
        self.assertEqual(result2['jobs'], 5)

    def test_budget_reduces_last_timeout_and_stops(self):
        for number in range(1, 7):
            self.lead(number)
        def slow(channel, text, seconds):
            self.clock.advance(seconds)
            raise d.UnknownSend()
        transport = FakeTransport(self.clock, [slow] * 12)
        d.Dispatcher(self.store, transport, self.clock).once()
        self.assertLessEqual(self.clock.monotonic(), 75)
        self.assertLessEqual(len(transport.calls), 7)
        self.assertLess(transport.calls[-1][3], 12)


class ValidationTests(Fixture):
    def test_strict_schema_rejects_unexpected_types_and_lengths(self):
        job, _ = self.lead()
        invalid = [dict(contact=''), dict(contact=['phone']), dict(name='x'*121),
                   dict(contact='x'*201), dict(message='x'*2001), dict(consent=1),
                   dict(consent=False), dict(version=True), dict(created_at=True),
                   dict(created_at=int(self.clock.time()+301)), dict(utm=[]),
                   dict(utm={'other': 'x'}), dict(utm={'utm_source': 'x'*121}),
                   dict(page='https://sy3.ru.evil.invalid/'), dict(page='http://sy3.ru/'),
                   dict(page='https://user@sy3.ru/'), dict(page='https://sy3.ru:443/'),
                   dict(page='https://sy3.ru/\n'), dict(contact='a\x00b'),
                   dict(contact='a\nb'), dict(message='\ud800'), dict(id='b'*64), dict(unexpected=1)]
        for change in invalid:
            with self.subTest(change=repr(change)):
                raw = json.dumps(job | change).encode()
                with self.assertRaises(d.SafeError):
                    d.validate_job(raw, job['id'], self.clock.time())

    def test_duplicate_json_keys_nan_broken_utf8_and_deep_nesting(self):
        for raw in [b'{"a":1,"a":2}', b'{"a":NaN}', b'\xff', b'['*1200+b']'*1200]:
            with self.subTest(raw=raw[:20]), self.assertRaises(d.SafeError):
                d.strict_json(raw)

    def test_symlink_regular_hardlink_fifo_and_size_rejected(self):
        _, file = self.lead()
        original = file.read_bytes()
        target = self.path/'target'
        file.rename(target)
        file.symlink_to(target)
        with self.assertRaises(d.SafeError):
            self.store.job(file.name, self.clock.time())
        file.unlink()
        os.link(target, file)
        with self.assertRaises(d.SafeError):
            self.store.job(file.name, self.clock.time())
        file.unlink()
        os.mkfifo(file, 0o600)
        with self.assertRaises(d.SafeError):
            self.store.job(file.name, self.clock.time())
        file.unlink()
        file.write_bytes(b' '* (d.JOB_LIMIT+1))
        file.chmod(0o600)
        with self.assertRaises(d.SafeError):
            self.store.job(file.name, self.clock.time())
        file.write_bytes(original)
        file.chmod(0o644)
        with self.assertRaises(d.SafeError):
            self.store.job(file.name, self.clock.time())

    def test_wrong_owner_and_filename_traversal_rejected(self):
        _, file = self.lead()
        with d.Store(self.root, self.uid+1, self.gid, root_uid=self.uid) as store:
            with self.assertRaises(d.SafeError):
                store.job(file.name, self.clock.time())
        for name in ['../outside.json', 'a'*64+'.json/../target', 'A'*64+'.json', 'x.json']:
            with self.assertRaises(d.SafeError):
                self.store.job(name, self.clock.time())

    def test_symlink_parent_and_queue_directory_rejected(self):
        link = self.path/'alias'
        link.symlink_to(self.root, target_is_directory=True)
        with self.assertRaises(d.SafeError):
            d.Store(link, self.uid, self.gid, root_uid=self.uid)
        parent_alias = self.path/'parent-alias'
        parent_alias.symlink_to(self.path, target_is_directory=True)
        with self.assertRaises(d.SafeError):
            d.Store(parent_alias/'leads', self.uid, self.gid, root_uid=self.uid)
        (self.root/'queue').rename(self.root/'original-queue')
        (self.root/'queue').symlink_to(self.root/'original-queue', target_is_directory=True)
        with self.assertRaises(d.SafeError):
            d.Store(self.root, self.uid, self.gid, root_uid=self.uid)

    def test_unsafe_state_link_cannot_be_overwritten(self):
        target = self.path/'do-not-change'
        target.write_text('keep')
        (self.root/'state'/'_control.json').symlink_to(target)
        with self.assertRaises(d.SafeError):
            self.store.write_state('_control.json', {'version': 1})
        self.assertEqual(target.read_text(), 'keep')

    def test_change_while_reading_is_detected(self):
        _, file = self.lead()
        real_read = os.read
        mutated = False
        def mutate(fd, length):
            nonlocal mutated
            data = real_read(fd, length)
            if not mutated:
                mutated = True
                file.write_text(file.read_text() + ' ')
            return data
        with patch.object(d.os, 'read', side_effect=mutate):
            with self.assertRaises(d.SafeError):
                self.store.job(file.name, self.clock.time())

    def test_state_validation_fails_closed_for_missing_fields(self):
        state = d.new_state('a'*64, self.clock.time())
        del state['channels']['telegram']['next_attempt_at']
        state['channels']['telegram']['message_id'] = 1
        with self.assertRaises(d.SafeError):
            d.validate_state(state)

    def test_corrupt_null_state_is_not_treated_as_missing_receipt(self):
        self.lead()
        self.run_worker()
        file = self.root/'state'/f'{1:064x}.json'
        file.write_text('null')
        result, transport = self.run_worker()
        self.assertFalse(transport.calls)
        self.assertEqual(result['rejected'], 1)

    def test_empty_control_is_not_treated_as_fresh_cooldown(self):
        self.lead()
        self.store.write_state('_control.json', {})
        transport = FakeTransport(self.clock)
        with self.assertRaises(d.SafeError):
            d.Dispatcher(self.store, transport, self.clock).once()
        self.assertFalse(transport.calls)

    def test_maximal_unicode_message_is_bounded_and_stable(self):
        job, _ = self.lead(name='😀'*120, contact='😀'*200, message='😀'*2000,
                           page='https://sy3.ru/'+'p'*2034,
                           utm={k: '😀'*120 for k in d.UTM_KEYS})
        text = d.format_message(job)
        self.assertLessEqual(d.units(text), 3900)
        self.assertLessEqual(len(text), 3900)
        self.assertIn('SY3-000000000000', text)
        self.assertIn('Контакт: '+'😀'*200, text)
        self.assertIn('Страница: https://sy3.ru/', text)
        for key in d.UTM_KEYS:
            self.assertIn(key+':', text)
        self.assertEqual(text, d.format_message(job))


class ApiTests(unittest.TestCase):
    def settings(self):
        return d.Settings(1001, 2002, '123456:'+'a'*24, 'synthetic-max-token-123456',
                          ssl.create_default_context(), ssl.create_default_context())

    def test_success_requires_exact_confirmation_shape(self):
        cases = [
            ('telegram', d.Reply(200, {'ok': True, 'result': {'message_id': 12}}), 'sent'),
            ('telegram', d.Reply(200, {'ok': True, 'result': {'message_id': True}}), 'uncertain'),
            ('telegram', d.Reply(200, {'ok': True, 'result': {}}), 'uncertain'),
            ('telegram', d.Reply(200, {'ok': 'true', 'result': {'message_id': 1}}), 'uncertain'),
            ('max', d.Reply(200, {'message': {'body': {'mid': 'mid.abc:1'}}}), 'sent'),
            ('max', d.Reply(200, {'message': {'mid': 'not-body'}}), 'uncertain'),
            ('max', d.Reply(200, {'message': {'body': {'mid': 'https://bad.invalid/'}}}), 'uncertain'),
            ('max', d.Reply(200, {'message': {}}), 'uncertain'),
            ('max', d.Reply(503, {'message': {'body': {'mid': 'mid.abc'}}}), 'uncertain'),
            ('telegram', d.Reply(200, {'ok': False, 'error_code': []}), 'uncertain'),
            ('telegram', d.Reply(500, {'ok': False, 'error_code': 500}), 'uncertain'),
            ('max', d.Reply(302, None), 'uncertain'),
            ('max', d.Reply(401, {'code': 'access.denied'}), 'failed'),
        ]
        for channel, reply, expected in cases:
            with self.subTest(channel=channel, reply=reply):
                self.assertEqual(d.outcome(channel, reply)[0], expected)

    def test_explicit_429_retry_after_bounded_and_no_500_retry(self):
        for reply, seconds in [(d.Reply(429, {'parameters': {'retry_after': 7}}), 7),
                               (d.Reply(429, {}, '99999999'), 3600),
                               (d.Reply(429, {}, 'https://not-a-delay.invalid/'), 60),
                               (d.Reply(429, {'parameters': {'retry_after': True}}), 60)]:
            result = d.outcome('telegram', reply)
            self.assertEqual(result, ('retry', 'rate_limited', None, seconds))
        self.assertEqual(d.outcome('max', d.Reply(500, {}))[0], 'uncertain')

    def test_proxy_environment_ignored_and_verified_contexts_used(self):
        settings = self.settings()
        with patch.dict(os.environ, {'HTTPS_PROXY': 'http://proxy.invalid/'}), \
                patch.object(d.urllib.request, 'build_opener') as builder:
            d.Transport(settings)
            self.assertEqual(builder.call_count, 2)
            for call, context in zip(builder.call_args_list, [settings.telegram_tls, settings.max_tls]):
                handlers = call.args
                self.assertEqual(handlers[0].proxies, {})
                self.assertIsInstance(handlers[1], d.NoRedirect)
                self.assertIs(handlers[2]._context, context)
                self.assertEqual(context.verify_mode, ssl.CERT_REQUIRED)
                self.assertTrue(context.check_hostname)

    def test_unverified_context_rejected(self):
        settings = self.settings()
        settings.max_tls.check_hostname = False
        settings.max_tls.verify_mode = ssl.CERT_NONE
        with self.assertRaises(d.SafeError):
            d.Transport(settings)

    def test_no_redirect_request_even_with_untrusted_location(self):
        self.assertIsNone(d.NoRedirect().redirect_request(None, None, 302, 'Found', {}, 'https://evil.invalid/'))
        transport = d.Transport(self.settings())
        error = urllib.error.HTTPError('https://platform-api2.max.ru/messages', 302, 'Found',
                                       {'Location': 'https://evil.invalid/'}, io.BytesIO(b'{}'))
        opener = MagicMock()
        opener.open.side_effect = error
        transport.openers['max'] = opener
        reply = transport.send('max', 'synthetic', 1)
        self.assertEqual(reply.status, 302)
        self.assertEqual(opener.open.call_count, 1)

    def test_tls_error_and_unknown_network_error_are_sanitized(self):
        transport = d.Transport(self.settings())
        opener = MagicMock()
        transport.openers['max'] = opener
        opener.open.side_effect = urllib.error.URLError(ssl.SSLCertVerificationError(1, 'synthetic'))
        with self.assertRaises(d.TLSFailure) as caught:
            transport.send('max', 'synthetic', 1)
        self.assertEqual(str(caught.exception), '')
        opener.open.side_effect = urllib.error.URLError('https://sensitive.invalid/token')
        with self.assertRaises(d.UnknownSend) as caught:
            transport.send('max', 'synthetic', 1)
        self.assertEqual(str(caught.exception), '')

    def test_wire_payload_fixed_recipient_plaintext_and_no_max_token_in_url(self):
        settings = self.settings()
        transport = d.Transport(settings)
        for channel in d.CHANNELS:
            response = MagicMock()
            response.code, response.headers = 200, {}
            response.read.return_value = b'{}'
            opener = MagicMock()
            opener.open.return_value = response
            transport.openers[channel] = opener
            transport.send(channel, '<b>literal</b> **plain**', 1)
            request = opener.open.call_args.args[0]
            body = json.loads(request.data)
            self.assertEqual(body['text'], '<b>literal</b> **plain**')
            self.assertNotIn('parse_mode', body)
            self.assertNotIn('format', body)
            if channel == 'max':
                self.assertEqual(request.full_url, d.MAX_ENDPOINT+'?user_id=2002&disable_link_preview=true')
                self.assertNotIn(settings.max_token, request.full_url)
                self.assertEqual(request.get_header('Authorization'), settings.max_token)
            else:
                self.assertEqual(body['chat_id'], 1001)
                self.assertTrue(request.full_url.startswith('https://api.telegram.org/bot'))

    def test_oversized_and_malformed_response_not_confirmed(self):
        transport = d.Transport(self.settings())
        for raw in [b'x'*(d.MAX_RESPONSE+1), b'{invalid']:
            opener, response = MagicMock(), MagicMock()
            response.read.return_value, response.code, response.headers = raw, 200, {}
            opener.open.return_value = response
            transport.openers['max'] = opener
            if len(raw) > d.MAX_RESPONSE:
                with self.assertRaises(d.UnknownSend):
                    transport.send('max', 'synthetic', 1)
            else:
                self.assertEqual(d.outcome('max', transport.send('max', 'synthetic', 1))[0], 'uncertain')

    def test_total_network_deadline_interrupts_slow_operation(self):
        with self.assertRaises(d.UnknownSend):
            with d.network_deadline(0.01):
                time.sleep(0.2)


class ConfigAndCliTests(Fixture):
    def test_env_is_data_not_shell_and_duplicate_keys_rejected(self):
        self.assertEqual(d.env_values(b'export KEY="$(do-not-execute)"\nOTHER=plain #comment')['KEY'],
                         '$(do-not-execute)')
        for raw in [b'A=1\nA=2', b'A=1; echo bad', b'A="unterminated']:
            with self.assertRaises(d.SafeError):
                d.env_values(raw)

    def test_load_settings_owner_match_and_extra_ca(self):
        reads = {d.CONFIG: json.dumps({'version':1,'telegram_owner_id':1001,'max_owner_id':2002}).encode(),
                 d.TELEGRAM_ENV: b'TELEGRAM_BOT_TOKEN=123456:aaaaaaaaaaaaaaaaaaaaaaaa',
                 d.MAX_ENV: b'MAX_BOT_TOKEN=synthetic-max-token-123456\nMAX_OWNER_ID=2002',
                 d.MAX_CA: b'synthetic-ca-test-only'}
        contexts = [MagicMock(verify_mode=ssl.CERT_REQUIRED, check_hostname=True),
                    MagicMock(verify_mode=ssl.CERT_REQUIRED, check_hostname=True)]
        with patch.object(d, 'protected_read', side_effect=lambda path, *args: reads[path]), \
                patch.object(d.ssl, 'create_default_context', side_effect=contexts):
            settings = d.load_settings()
        self.assertEqual(settings.max_owner_id, 2002)
        contexts[1].load_verify_locations.assert_called_once_with(cadata='synthetic-ca-test-only')
        reads[d.MAX_ENV] = b'MAX_BOT_TOKEN=synthetic-max-token-123456\nMAX_OWNER_ID=2003'
        with patch.object(d, 'protected_read', side_effect=lambda path,*args: reads[path]):
            with self.assertRaises(d.SafeError):
                d.load_settings()

    def test_check_only_local_validation_and_no_transport_constructed(self):
        wp = types.SimpleNamespace(pw_uid=self.uid, pw_gid=self.gid)
        output = io.StringIO()
        with patch.object(d, 'ROOT', self.root), patch.object(d.os, 'geteuid', return_value=0), \
                patch.object(d.pwd, 'getpwnam', return_value=wp), patch.object(d, 'load_settings'), \
                patch.object(d, 'Transport') as transport, contextlib.redirect_stdout(output):
            self.assertEqual(d.main(['--check']), 0)
        transport.assert_not_called()
        self.assertEqual(json.loads(output.getvalue()), {'check':'ok','network':False})
        self.assertEqual(list((self.root/'state').iterdir()), [])

    def test_cli_nonroot_does_not_read_credentials(self):
        with patch.object(d.os, 'geteuid', return_value=1000), patch.object(d, 'load_settings') as settings, \
                contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(d.main(['--once']), 1)
        settings.assert_not_called()

    def test_cli_does_not_print_raw_exception_urls_or_tokens(self):
        self.lead(created_at=int(time.time()))
        wp = types.SimpleNamespace(pw_uid=self.uid, pw_gid=self.gid)
        transport = FakeTransport(self.clock, [RuntimeError('https://secret.invalid/SENSITIVE_TOKEN')])
        output = io.StringIO()
        with patch.object(d, 'ROOT', self.root), patch.object(d.os, 'geteuid', return_value=0), \
                patch.object(d.pwd, 'getpwnam', return_value=wp), patch.object(d, 'load_settings'), \
                patch.object(d, 'Transport', return_value=transport), contextlib.redirect_stdout(output):
            self.assertEqual(d.main(['--once']), 1)
        self.assertNotIn('SENSITIVE_TOKEN', output.getvalue())
        self.assertNotIn('https://', output.getvalue())
        self.assertEqual(self.state()['channels']['telegram']['status'], 'sending')


if __name__ == '__main__':
    unittest.main()
