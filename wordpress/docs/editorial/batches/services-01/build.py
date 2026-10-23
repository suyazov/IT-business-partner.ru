#!/usr/bin/env python3
"""Render the owned service candidates. Does not import into WordPress."""
import argparse
import hashlib
import html
import json
import re
from pathlib import Path


HERE = Path(__file__).resolve().parent
EXPECTED = {
    'web-development', 'website-support', 'website-modernization', 'website-audit',
    'wordpress-support', 'bitrix24-support', 'business-analysis',
    'business-process-automation', 'website-migration', 'crm-audit',
    'website-crm-integration', '1c-integration', 'bi-dashboards',
}
CASE_SLUGS = {
    'web-development': ['libertygas', 'chitayka', 'stilspace', 'postavki26', 'glosscheck', 'shumakova-yookassa'],
    'website-support': ['design-moskva', 'avg-realestate', 'antispam-24-sites'],
    'website-modernization': ['real-flacon', 'digital-belka', 'asia-shadrin'],
    'website-audit': ['anfilada-design', 'saneq-technology', 'design-moskva'],
    'wordpress-support': ['star26', 'real-flacon', 'anfilada-design'],
    'bitrix24-support': ['bitrix24-training', 'bitrix24-doors'],
    'business-analysis': ['crm-hr-requirements', 'bitrix24-doors', 'bitrix24-training', 'libertygas'],
    'business-process-automation': ['bitrix24-training', 'libertygas', 'bitrix24-doors'],
    'website-migration': ['petlifeexpo', 'star26', 'stilspace'],
    'crm-audit': ['matklass', 'bitrix24-doors', 'bitrix24-training'],
    'website-crm-integration': ['matklass', 'bitrix24-doors', 'antispam-24-sites'],
    '1c-integration': ['crm-hr-requirements'],
    'bi-dashboards': ['hiring-funnel-dashboard'],
}
NOTES = {
    'web-development': 'Сохранены форматы разработки, CMS, стек и 6 примеров; подробнее раскрыт проверяемый инструмент LibertyGas.',
    'website-support': 'Сбой отделён от развития; есть воспроизводимый пример и кейс ошибки административной части; режим поддержки согласуется отдельно.',
    'website-modernization': 'Первый этап привязан к действию пользователя; пример WooCommerce объясняет, что проверять вместе с изменением.',
    'website-audit': 'Разделены задачи SEO, поддержки и редизайна; наблюдение и гипотеза не смешиваются; исправления оцениваются отдельно.',
    'wordpress-support': 'Сохранены обновления и работа с актуальными заказами; управляемость показана через WordPress/ACF, без требования новой темы.',
    'bitrix24-support': 'Личная роль и рабочие права заменили обезличенный процесс; сохранены ограничения тарифа, зависимостей и часов поддержки.',
    'business-analysis': 'Есть конкретный состав требований и проверенный аналитический этап; ТЗ не выдано за внедрённую интеграцию.',
    'business-process-automation': 'Один ручной участок и исключения вместо общего обещания; автоматизация и последующая оценка эффекта разделены.',
    'website-migration': 'Статический перенос не выдан за WordPress; сохранены актуальные данные, DNS, внешние подключения и ограничения SEO.',
    'crm-audit': 'Проверка построена на рабочем случае; контрольные сделки отделены от продаж, диагностика — от изменений данных.',
    'website-crm-integration': 'Маршруты форм, конечная карточка, повторы и сбои сохранены; безопасность объяснена в месте выбора подключения.',
    '1c-integration': 'Явно названы подрядчики; аналитическая практика не выдана за реализацию 1С; методика учёта остаётся у заказчика.',
    'bi-dashboards': 'Добавлен подтверждённый обезличенный HR-дашборд без выдуманных функций; определения, качество и свежесть данных сохранены.',
}


def digest(value):
    return hashlib.sha256(value.encode()).hexdigest()


def inline(value):
    result = []
    pos = 0
    for match in re.finditer(r'\[([^\]]+)\]\(([^)]+)\)', value):
        result.append(html.escape(value[pos:match.start()], quote=False))
        label, url = match.groups()
        if not re.fullmatch(r'/[a-z0-9/-]+/', url):
            raise ValueError('Unexpected editorial link: ' + url)
        result.append('<a href="' + html.escape(url, quote=True) + '">' + html.escape(label, quote=False) + '</a>')
        pos = match.end()
    result.append(html.escape(value[pos:], quote=False))
    return ''.join(result)


def paragraph(value):
    return '<!-- wp:paragraph --><p>' + inline(value) + '</p><!-- /wp:paragraph -->\n'


def render(text):
    result = []
    for chunk in text.strip().split('\n\n'):
        lines = chunk.splitlines()
        if chunk.startswith('## '):
            if len(lines) != 1:
                raise ValueError('Multiline heading')
            result.append('<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">' + inline(chunk[3:]) + '</h2><!-- /wp:heading -->\n')
        elif chunk.startswith('- '):
            if any(not line.startswith('- ') for line in lines):
                raise ValueError('Mixed list')
            result.append('<!-- wp:list --><ul class="wp-block-list">' + ''.join('<!-- wp:list-item --><li>' + inline(line[2:]) + '</li><!-- /wp:list-item -->\n' for line in lines) + '</ul><!-- /wp:list -->\n')
        elif chunk.startswith('? '):
            if len(lines) != 2:
                raise ValueError('FAQ must contain one question and answer')
            result.append('<!-- wp:details --><details class="wp-block-details"><summary>' + inline(lines[0][2:]) + '</summary>' + paragraph(lines[1]) + '</details><!-- /wp:details -->\n')
        else:
            if len(lines) != 1:
                raise ValueError('Unexpected multiline paragraph')
            result.append(paragraph(chunk))
    result.append('<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://t.me/sy3_support_bot?start=site_sy3">Написать в Telegram</a></div><!-- /wp:button -->\n<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="https://max.ru/id263514840335_1_bot?start=site_sy3">Написать в MAX</a></div><!-- /wp:button -->\n</div><!-- /wp:buttons -->\n')
    return ''.join(result)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--baseline', type=Path, required=True, help='Read-only runtime-baseline.json')
    args = parser.parse_args()
    baseline = json.loads(args.baseline.read_text())
    rows = {row['post_name']: row for row in baseline['rows'] if row['post_type'] == 'sy3_service'}
    cases = {row['post_name']: row for row in baseline['rows'] if row['post_type'] == 'sy3_case'}
    parts = re.split(r'^@ ([a-z0-9-]+)\n', (HERE / 'CONTENT.md').read_text(), flags=re.M)
    source = dict(zip(parts[1::2], parts[2::2]))
    if set(source) != EXPECTED or len(parts) != 27:
        raise ValueError('Candidate scope mismatch')
    candidates = []
    for slug, text in source.items():
        row = rows[slug]
        if digest(row['post_content']) != row['body_hash']:
            raise ValueError('Baseline body hash mismatch: ' + slug)
        body = render(text)
        target = HERE / (slug + '.html')
        target.write_text(body)
        canonical = ('wordpress/content/services.json' if slug == 'web-development' else 'wordpress/search-content/partner-services.json' if slug in {'1c-integration', 'bi-dashboards'} else 'wordpress/search-content/services.json')
        evidence = []
        for case_slug in CASE_SLUGS[slug]:
            case = cases[case_slug]
            evidence.append({
                'status': 'VERIFIED', 'scope': 'Accepted public case text only; no new live client-system audit or business-effect measurement.',
                'case_slug': case_slug, 'case_id': int(case['ID']), 'url': case['url'], 'body_hash': case['body_hash'],
            })
        if slug == '1c-integration':
            evidence.append({'status': 'VERIFIED', 'claim': 'Профильные подрядчики по 1С; личная роль — требования, координация, проверка.', 'source': 'Owner instruction; wordpress/docs/WORK_PROCESS.md', 'limitation': 'HR requirements case is not a 1C implementation case.'})
        if slug == 'bi-dashboards':
            evidence.append({'status': 'VERIFIED', 'claim': 'Профильные дата-аналитики участвуют в моделях данных и дашбордах.', 'source': 'Owner instruction; wordpress/docs/WORK_PROCESS.md', 'limitation': 'No invented HR dashboard platform, filters, volumes or recruitment performance.'})
        candidates.append({
            'id': int(row['ID']), 'slug': slug, 'type': 'sy3_service',
            'baseline_hash': row['body_hash'], 'file': target.name,
            'candidate_hash': digest(body), 'review_state': 'NEEDS_EDITORIAL_REVIEW',
            'decision': 'REWRITE', 'reason': NOTES[slug],
            'evidence': evidence,
            'source_files': [canonical, 'wordpress/docs/PORTFOLIO_SOURCE.md', 'wordpress/docs/WORK_PROCESS.md', 'wordpress/docs/editorial/FACTS_AND_CLAIMS.md'],
            'baseline_evidence': 'runtime-baseline.json: sy3_service ID ' + row['ID'],
            'preserved': {key: row[key] for key in ['post_title', 'post_excerpt', 'post_status', 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt', 'post_author', 'post_parent', 'menu_order', 'url', 'meta', 'terms', 'cover']},
            'body_only': True, 'review_hash_created': False,
            'proposed_work_status': 'PLANNED: scope, cost, limitations and acceptance agreed per project.',
            'reader_test': 'READER_TEST_NOT_RUN',
            'visible_characters': len(html.unescape(re.sub('<[^>]*>', '', body))),
        })
    output = {
        'batch': 'services-01', 'source_base': '273d8b4',
        'production_authority': False, 'runtime_or_database_changed': False,
        'article_minimum_policy_changed': False,
        'candidates': candidates,
    }
    (HERE / 'manifest.json').write_text(json.dumps(output, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps({'batch': 'services-01', 'candidates': len(candidates), 'visible_characters': {c['slug']: c['visible_characters'] for c in candidates}}, ensure_ascii=False))


if __name__ == '__main__':
    main()
