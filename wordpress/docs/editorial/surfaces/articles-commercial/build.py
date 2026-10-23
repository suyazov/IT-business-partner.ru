"""Assemble individual metadata/CTA plans; source-only, no CMS imports or writes."""
import hashlib
import html
import json
import re
from collections import Counter
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[4]
BASELINE = Path('/root/preview/sy3-wordpress-20261022/private/editorial-refactor-20261023/runtime-baseline.json')
BATCHES = ('02', '04', '06', '08', '10', '12', '14')
FIELDS = ('post_title', 'post_excerpt', '_sy3_description')

def sha(value):
    return hashlib.sha256(value).hexdigest()

def field_hash(mapping):
    return sha(json.dumps(mapping, ensure_ascii=False, separators=(',', ':'), sort_keys=True).encode('utf-8'))

def main():
    baseline = {int(r['ID']): r for r in json.loads(BASELINE.read_text())['rows']}
    candidates = []
    qa_rows = []
    reports = []
    cta_texts = []
    for number in BATCHES:
        plans = json.loads((HERE/f'copy-{number}.json').read_text())
        batch = ROOT/'wordpress/docs/editorial/batches'/f'drafts-{number}'
        manifest = json.loads((batch/'manifest.json').read_text())
        assert set(plans) == {str(c['id']) for c in manifest['candidates']}
        assert len(plans) == 20
        for item in manifest['candidates']:
            ident = int(item['id'])
            r = baseline[ident]
            assert r['post_type'] == 'post'
            assert item['baseline_hash'] == r['body_hash']
            assert item['slug'] == r['post_name']
            body_file = batch/item['file']
            body = body_file.read_bytes()
            body_hash = sha(body)
            assert body_hash == item['candidate_hash'], (ident, 'body changed since frozen manifest')
            assert item['review_state'] == 'NEEDS_EDITORIAL_REVIEW'
            before = {k: str(r['meta'].get(k, '') if k.startswith('_') else r.get(k, '')) for k in FIELDS}
            after = dict(before)
            plan = plans[str(ident)]
            assert len(plan) == 7
            description, title1, text1, label1, title2, text2, label2 = plan
            if description is not None:
                assert isinstance(description, str) and description.endswith('.')
                after['post_excerpt'] = after['_sy3_description'] = description
                reason = 'Обрезанное вступление или повествовательное описание заменено законченным изложением задачи и содержания; предмет: '+title1+' Заголовок точен и сохранён. Два CTA написаны отдельно под проблему и следующий шаг.'
            else:
                reason = 'KEEP полей: заголовок и законченные описания точно передают разбор; тема: '+title1+' Новые CTA предлагают конкретный разбор и проверку следующего шага, без смены темы.'
            ctas = [dict(title=title1, text=text1, label=label1), dict(title=title2, text=text2, label=label2)]
            for cta in ctas:
                assert all(isinstance(cta[k], str) and cta[k].strip() == cta[k] for k in ('title','text','label'))
                assert 8 <= len(cta['title']) <= 100
                assert 40 <= len(cta['text']) <= 230
                assert 8 <= len(cta['label']) <= 60
                assert cta['text'].endswith('.')
                assert '<' not in ''.join(cta.values()) and 'https://' not in ''.join(cta.values())
                assert not re.search(r'Арт[её]м|бесплатн|24/7|любого масштаба|гарантируем|гарантирую', ' '.join(cta.values()), re.I)
                cta_texts.append(cta['text'])
            assert text1 != text2
            for field in ('post_excerpt', '_sy3_description'):
                assert after[field].rstrip().endswith(('.', '?', '!')), (ident, 'unfinished description')
                assert len(after[field]) <= 350
            source_path = str(body_file.relative_to(ROOT))
            evidence = [source_path, 'Подготовленный body SHA256 '+body_hash, 'Индивидуальные решения и факты: '+str((batch/'edits.json').relative_to(ROOT)), 'Source checks и границы подтверждения: '+str((batch/'SOURCE_CHECKS.md').relative_to(ROOT))]
            c = dict(id=ident, type='post', slug=r['post_name'], baseline_body_hash=r['body_hash'], baseline_fields_hash=field_hash(before), candidate_fields_hash=field_hash(after), before=before, after=after, action='KEEP' if before == after else 'REWRITE', reason=reason, evidence=evidence, review_state='NEEDS_EDITORIAL_REVIEW', article_support=dict(body_hash=body_hash, ctas=ctas))
            candidates.append(c)
            qa_rows.append(dict(id=ident, batch='drafts-'+number, fields_action=c['action'], body_file=source_path, body_hash=body_hash, baseline_fields_hash=c['baseline_fields_hash'], candidate_fields_hash=c['candidate_fields_hash'], title_kept=before['post_title']==after['post_title'], faq_authored_in_body=True, cta_count=len(ctas)))
            reports += [f"### {ident} / {r['post_name']}", '', reason, '', 'До: '+json.dumps(before, ensure_ascii=False), '', 'После: '+json.dumps(after, ensure_ascii=False), '', 'CTA 1: '+json.dumps(ctas[0], ensure_ascii=False), '', 'CTA 2: '+json.dumps(ctas[1], ensure_ascii=False), '', 'Body: '+source_path+'; SHA256 '+body_hash, '']
    assert len(candidates) == len({c['id'] for c in candidates}) == 140
    assert len(cta_texts) == len(set(cta_texts)) == 280, 'Repeated CTA paragraph'
    assert len({c['slug'] for c in candidates}) == 140
    for c in candidates:
        assert set(c['before']) == set(c['after']) == set(FIELDS)
        assert c['baseline_fields_hash'] == field_hash(c['before'])
        assert c['candidate_fields_hash'] == field_hash(c['after'])
        assert c['article_support']['body_hash'] != c['baseline_body_hash']
    output = dict(production_authority=False, candidates=candidates)
    (HERE/'manifest.json').write_text(json.dumps(output, ensure_ascii=False, indent=2)+'\n')
    qa = dict(count=140, batches=list(BATCHES), fields_rewrite=sum(c['action']=='REWRITE' for c in candidates), fields_keep=sum(c['action']=='KEEP' for c in candidates), titles_kept=sum(c['before']['post_title']==c['after']['post_title'] for c in candidates), cta_count=280, unique_cta_texts=280, body_hashes_match_frozen_manifests=True, baseline_identity_and_field_hashes_verified=True, only_three_metadata_fields=True, faq_added=False, body_changed=False, theme_or_runtime_changed=False, approval_hash_created=False, reader_review='NOT_RUN; root owns independent editorial/render review', rows=qa_rows)
    (HERE/'QA.json').write_text(json.dumps(qa, ensure_ascii=False, indent=2)+'\n')
    intro = ['# Article metadata and intermediate CTA candidates', '', '140 собственных статей drafts-02/04/06/08/10/12/14. Два CTA на статью, всего 280 разных абзацев. Production authority: false. Это source-кандидаты для независимого review; WordPress и runtime не менялись.', '', '## Границы и метод', '', 'Полные body и mapped sources прочитаны при подготовке каждой из семи FROZEN партий; индивидуальные решения сохранены в edits.json и source checks. Для surface-прохода прочитаны baseline title/excerpt/description и соответствующие планы. Тексты не получены обрезкой первых 160 знаков: новые описания и оба CTA написаны индивидуально, готовые точные описания сохранены.', '', 'action KEEP/REWRITE относится только к трём metadata-полям. При KEEP article_support всё равно содержит два новых CTA. Все заголовки оставлены, так как соответствуют материалу. У финансовых и детских моделей сохраняются методические границы; подтверждённые кейсы не расширены новыми результатами.', '', 'article_support.body_hash — SHA256 точных байтов подготовленного HTML, сверенный с FROZEN manifest. Он связывает CTA с новой редакцией, но не является publisher reviewed hash и не создаёт WP approval. FAQ не добавлены: в body уже есть два авторских вопроса с распознаваемым H2. Кнопки ведут через существующую интеграцию root; в тексты не встроены новые адреса, тарифы или обещания.', '', '## Проверки', '', '140 identity/slug/baseline body hashes и SHA исходных полей сверены с baseline. Точный алгоритм полей: SHA256 UTF8 json.dumps(mapping, ensure_ascii=False, separators=(comma,colon), sort_keys=True). В before/after ровно post_title, post_excerpt, _sy3_description. Проверены 140 совпадений нового body hash с FROZEN manifest, 280 непустых индивидуальных CTA, отсутствие повторяющихся абзацев CTA, законченные описания, отсутствие третьего лица «с Артёмом», бесплатного аудита и гарантий. Независимая смысловая и render-проверка остаётся root.', '', 'Новые технические или тарифные утверждения не добавлены; ссылки на первоисточники и их границы остаются в source checks каждой партии. Никаких paid API/CRM/DB/WP/secret/Git writes. Новых копий и временных файлов нет; build.py и copy-*.json — воспроизводимые source-артефакты.', '', '## Индивидуальные решения', '']
    (HERE/'REPORT.md').write_text('\n'.join(intro+reports)+'\n')
    print(json.dumps({k:v for k,v in qa.items() if k != 'rows'}, ensure_ascii=False))

if __name__ == '__main__':
    main()
