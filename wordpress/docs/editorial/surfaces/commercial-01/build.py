"""Render reviewed-source metadata candidates; does not import WordPress or write runtime."""
import hashlib
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[4]
BASELINE = Path('/root/preview/sy3-wordpress-20261022/private/editorial-refactor-20261023/runtime-baseline.json')
FIELDS = ('post_title', 'post_excerpt', '_sy3_description')

def sha(value):
    return hashlib.sha256(value).hexdigest()

def field_hash(mapping):
    return sha(json.dumps(mapping, ensure_ascii=False, separators=(',', ':'), sort_keys=True).encode())

# Individually written against the accepted service/page bodies, not clipped introductions.
DESCRIPTIONS = {
    46: ('Исправил ссылки и замечания SEO-аудита WordPress. Нашёл конфликт плагинов, вызывавший ошибку админки, и сделал точечное исправление.', 'Narrow reopen по независимому review: двусмысленное «конфликт плагинов по памяти» заменено точным описанием найденной причины ошибки административной части; выполненная работа и title сохранены.'),
    4: ('Создаю сайты и веб-сервисы, настраиваю Битрикс24 и обмен данными. Лично веду проект; для задач по 1С и BI подключаю профильных специалистов.', 'Главная объединяет разработку и автоматизацию; личная роль и состав команды соответствуют принятому пилоту.'),
    5: ('Я Артём Суязов. Разрабатываю сайты и интеграции, разбираю процессы с заказчиком и пользователями, готовлю требования и проверяю результат с командой.', 'Вместо общего обещания помощи описаны конкретные действия из раздела о работе автора.'),
    6: ('Напишите мне в Telegram, MAX или на help@sy3.ru. Для начала хватит адреса сайта и примера задачи; готовое ТЗ, пароли и клиентские базы не нужны.', 'Завершённое описание обращения соответствует принятому контактному пилоту; предпочтение переписки сохранено.'),
    8: ('Разбираю разработку и поддержку сайтов, CRM, интеграции и ИИ: что проверить, как описать требования и принять работу. С примерами из моих проектов.', 'Описание раскрывает содержание журнала и аудиторию вместо перечня трёх технологий.'),
    9: ('Настрою Битрикс24 под путь обращения: воронку, поля, назначение менеджера и рабочие права. Проверю согласованные сценарии с командой и передам инструкцию.', 'Состав настройки и проверка взяты из принятого пилота; нет обещания роста продаж.'),
    10: ('Свяжу сайт, CRM и рабочие сервисы. Согласую поля, повторы и действия при ошибке, проверю появление нужной записи в принимающей системе.', 'Понятный результат обмена вместо абстрактной связи систем; возможности подключения проверяются в body.'),
    11: ('Помогу выбрать одну задачу для ИИ и проверить пробную версию на примерах вашей работы. Согласуем данные, участие сотрудника и расходы на обработку.', 'Ограниченный пилот с ручной проверкой, не обещание автоматизации отдела.'),
    242: ('Разработаю лендинг, корпоративный сайт, каталог или веб-сервис. Согласую действия посетителя и редактора, CMS и интеграции, затем проверю готовый сценарий.', 'Сохранены форматы разработки и управление контентом; результат описан через действия пользователей.'),
    249: ('Разберу ошибку страницы, формы или административной части и предложу исправление. Беру действующие сайты в поддержку; условия регулярной работы согласуем отдельно.', 'Убрано третье лицо «с Артёмом на связи»; отдельная ошибка и регулярная поддержка имеют разные условия.'),
    250: ('Доработаю каталог, заказ, мобильную страницу или управление контентом на действующем сайте. До правок определю зависимости и согласую, что нужно сохранить.', 'Вместо общего развития сайта перечислены конкретные изменения и условие сохранения работающих частей.'),
    251: ('Проверю техническое состояние сайта под вашу задачу: индексацию, адреса, формы и важные действия. Подготовлю подтверждённые замечания и порядок исправлений.', 'Аудит описан как обследование с результатом; исправления и активные проверки не обещаны автоматически.'),
    252: ('Исправлю ошибку темы или плагина, доработаю шаблоны, формы и WooCommerce. Сначала разберу зависимости WordPress, после изменений проверю важные действия.', 'От первого лица, с конкретными объектами поддержки и связью с проверкой.'),
    253: ('Разберу сбой робота, прав или интеграции Битрикс24, доработаю поля и воронки. Проверю результат под рабочей ролью; состав сопровождения согласуем отдельно.', 'Сохранено отличие доработки действующего портала от нового внедрения; нет неоговорённого SLA.'),
    254: ('Разберу процесс с заказчиком и пользователями, опишу данные, правила и исключения. Подготовлю требования и критерии приёмки для оценки и разработки.', 'Конкретные действия аналитика и самостоятельный результат этапа без гарантии эффекта внедрения.'),
    255: ('Помогу убрать выбранную ручную операцию: передать заявку, назначить исполнителя или собрать документ. Опишу правила, реализую согласованный участок и проверю его.', 'Один ограниченный процесс вместо обещания комплексной автоматизации всего бизнеса.'),
    256: ('Подготовлю перенос сайта с учётом адресов, контента, форм и подключений. Составлю карту переходов и проверю важные действия после согласованного переключения.', 'Перенос не сводится к копированию файлов; позиции в поиске и публикация без согласования не обещаны.'),
    257: ('Проверю CRM на примерах обращений: источники, карточки, права, автоматизацию и обмен. Подготовлю список подтверждённых проблем и предложу первый этап исправлений.', 'Диагностика отличается от выполнения правок, сохранён предмет аудита.'),
    258: ('Подключу формы сайта к CRM и проверю путь до карточки и ответственного. Согласую поля, источники, повторы и поведение при ошибках передачи.', 'Краткое самостоятельное описание полного маршрута формы без отрицательных рекламных сравнений.'),
    259: ('Организую обмен между 1С, CRM и сайтом: разберу данные, подготовлю требования и проверю связанный результат. Изменения в 1С выполняют профильные подрядчики.', 'Сохранены точные роли: я веду требования и координацию, подрядчик выполняет работы внутри 1С.'),
    260: ('Помогу определить показатели и источники для дашборда. Вместе с профильными дата-аналитиками подготовлю отчёт и сверю расчёты с исходными данными.', 'Уточнены определения и сверка данных; роль профильных аналитиков сохранена без обещания любых источников.'),
}

def main():
    rows = [r for r in json.loads(BASELINE.read_text())['rows'] if r['post_type'] != 'post']
    assert len(rows) == 49
    bodies = {}
    for c in json.loads((ROOT/'wordpress/docs/editorial/pilot/commercial.json').read_text())['candidates']:
        bodies[(c['type'], c['slug'])] = (c['content'], 'wordpress/docs/editorial/pilot/commercial.json')
    excerpts = {}
    for batch in ('services-01', 'cases-01', 'cases-02'):
        folder = ROOT/'wordpress/docs/editorial/batches'/batch
        for c in json.loads((folder/'manifest.json').read_text())['candidates']:
            path = folder/c['file']
            bodies[(c['type'], c['slug'])] = (path.read_text(), str(path.relative_to(ROOT)))
            if c.get('excerpt'):
                excerpts[c['id']] = c['excerpt']
    candidates = []
    for r in rows:
        ident = int(r['ID'])
        before = {k: str(r['meta'].get(k, '') if k.startswith('_') else r.get(k, '')) for k in FIELDS}
        after = dict(before)
        body, source = bodies.get((r['post_type'], r['post_name']), (r['post_content'], 'runtime-baseline.json'))
        if ident in DESCRIPTIONS:
            text, reason = DESCRIPTIONS[ident]
            after['post_excerpt'] = after['_sy3_description'] = text
        elif ident in excerpts:
            after['post_excerpt'] = after['_sy3_description'] = excerpts[ident]
            reason = 'Повторно использовано индивидуальное описание из принятого source-пакета кейсов; кратко описывает выполненную работу, а не отрасль клиента.'
        elif ident == 21:
            after['post_excerpt'] = after['_sy3_description'] = 'Разработал редактор документов LibertyGas: тексты, три фотографии на объект и PDF по правилу «один объект — один лист A4». Работает и как автономный HTML.'
            reason = 'Подтверждённый инструмент, редактирование и автономный формат из пилота; без утверждений об измеренной экономии.'
        elif ident in (3, 7):
            reason = 'KEEP: юридические и технические утверждения страницы не пересматриваются этим metadata-проходом; новые правила обработки данных не добавлены.'
        else:
            raise AssertionError(ident)
        if ident == 8:
            after['post_title'] = 'Статьи о сайтах, CRM и автоматизации бизнеса'
        if ident == 4:
            after['post_title'] = 'Сайты, CRM и автоматизация бизнеса'
        candidates.append(dict(id=ident, type=r['post_type'], slug=r['post_name'], baseline_body_hash=r['body_hash'], baseline_fields_hash=field_hash(before), candidate_fields_hash=field_hash(after), before=before, after=after, action='KEEP' if before == after else 'REWRITE', reason=reason, evidence=[source, 'Соответствующий подготовленный body прочитан целиком; SHA256 '+sha(body.encode()), 'Runtime baseline body SHA256 '+r['body_hash']], review_state='NEEDS_EDITORIAL_REVIEW'))
    out = dict(production_authority=False, candidates=candidates)
    other48_hash = field_hash([c for c in candidates if c['id'] != 46])
    assert other48_hash == '3b71ea59247c57aba08d19c101748b5a15149d710aa4b068b47146741bbb864e'
    (HERE/'manifest.json').write_text(json.dumps(out, ensure_ascii=False, indent=2)+'\n')
    qa = {'count': len(candidates), 'types': {t: sum(c['type']==t for c in candidates) for t in ('page','sy3_service','sy3_case')}, 'changed':sum(c['action']=='REWRITE' for c in candidates), 'kept':sum(c['action']=='KEEP' for c in candidates), 'fields_hash_algorithm':'SHA256 UTF8 json.dumps(mapping, ensure_ascii=False, separators=(comma,colon), sort_keys=True)', 'body_changed':False, 'runtime_changed':False, 'approval_created':False, 'reader_review':'NOT_RUN; parent owns independent review'}
    qa['narrow_reopen'] = {'id':46, 'fields':['post_excerpt','_sy3_description'], 'reason':'Independent reviewer requested removal of ambiguous memory-conflict wording.', 'other48_rows_canonical_sha256':other48_hash, 'other48_rows_unchanged':True}
    for c in candidates:
        assert set(c['before']) == set(c['after']) == set(FIELDS)
        assert c['baseline_fields_hash'] == field_hash(c['before'])
        assert c['candidate_fields_hash'] == field_hash(c['after'])
        assert not any('Артёмом' in c['after'][k] for k in ('post_excerpt', '_sy3_description'))
    (HERE/'QA.json').write_text(json.dumps(qa, ensure_ascii=False, indent=2)+'\n')
    report = ['# Commercial surface candidates', '', '49 объектов: 6 страниц, 16 услуг, 27 кейсов. Только post_title, post_excerpt, _sy3_description. Production authority: false. Независимое review и интеграция остаются у root.', '', 'Все исходные три поля прочитаны. Полные соответствующие body прочитаны: пилот страниц/трёх услуг/LibertyGas, 13 услуг services-01, 26 кейсов cases-01/02; privacy-policy/privacy и пустая journal — runtime baseline. Body и прежние пакеты не изменены.', '', 'Два заголовка уточнены: home включает разработку сайтов; journal раскрывает тематику. Названия услуг и кейсов сохранены. Для privacy-policy/privacy KEEP без правового переосмысления. Условия аналитики, форм, почта и UI этим пакетом не меняются.', '', '## Индивидуальные решения', '']
    report[2:2] = ['Narrow reopen row46 по независимому review: excerpt/description теперь называют найденный конфликт плагинов причиной ошибки админки без двусмысленного «по памяти». Title/body и остальные48 полных candidate rows не изменены; SHA256 канонического списка остальных48: '+other48_hash+'. Эта проверка включена в воспроизводимую сборку и QA.', '']
    for c in candidates:
        report += [f"### {c['id']} / {c['slug']} — {c['action']}", '', c['reason'], '', 'До: '+json.dumps(c['before'], ensure_ascii=False), '', 'После: '+json.dumps(c['after'], ensure_ascii=False), '', 'Источник: '+c['evidence'][0], '']
    report += ['## Проверки', '', 'Уникальность 49 identity, неизменность slug/type/body hash, точные SHA полей, три разрешённых ключа и отсутствие third-person «с Артёмом» проверены сборкой. Юридический смысл не проверялся заново. Никаких WP/DB/runtime/Git writes или approval hash. Новых резервных копий и временных файлов нет; build.py — воспроизводимый артефакт пакета.']
    (HERE/'REPORT.md').write_text('\n'.join(report)+'\n')
    print(json.dumps(qa, ensure_ascii=False))

if __name__ == '__main__':
    main()
