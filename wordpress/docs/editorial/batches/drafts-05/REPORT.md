# drafts-05: FROZEN

Двадцать полных Gutenberg HTML подготовлены как **NEEDS_EDITORIAL_REVIEW**. Публикационного одобрения нет. Runtime, исходные тела, очередь, статусы, SEO-метаданные и approval hashes не изменялись. Родитель собирает scoped commit.

Каждый mapped source прочитан целиком; все runtime-дополнения прочитаны отдельно после сопоставления с исходным текстом. Решения индивидуальны: сохранены полезные подробные разделы, удалены их ранние повторные пересказы, добавлены конкретные контрпримеры и только подтверждённые факты портфолио. Во всех двадцати выбран REWRITE: повторная вводная и устаревший контакт требовали редакторской работы даже при хорошем основном разборе.

Авторизованное исключение сохранения href: личный Telegram заменён принятыми support-ботами Telegram/MAX. Все значимые сервисные, документальные и SVG/CSV-ссылки сохранены. CTA от первого лица предлагают конкретный исходный материал для разбора, без бесплатного аудита, обещанных сроков и коммерческого результата.

## Before → after

Знаки измерены actual `sy3_editorial_quality`, а не длиной HTML. Точные baseline/candidate SHA256 и source mapping находятся в manifest.json.

| ID | Знаки до | Знаки после | Действие | Кандидат |
|---|---:|---:|---|---|
|141|11521|11502|REWRITE|[pisma-s-sayta-v-crm](pisma-s-sayta-v-crm.html)|
|273|12118|11173|REWRITE|[www-bez-www-osnovnoy-domen](www-bez-www-osnovnoy-domen.html)|
|334|13017|11769|REWRITE|[oshibki-502-504-chto-proveryat](oshibki-502-504-chto-proveryat.html)|
|297|12577|10577|REWRITE|[utm-metki-v-crm-bitrix24](utm-metki-v-crm-bitrix24.html)|
|152|11979|11988|REWRITE|[antispam-bez-kapchi](antispam-bez-kapchi.html)|
|354|13461|11464|REWRITE|[otsenka-effekta-avtomatizatsii](otsenka-effekta-avtomatizatsii.html)|
|274|12455|11123|REWRITE|[stranica-404-pravilnyy-otvet](stranica-404-pravilnyy-otvet.html)|
|335|13783|12412|REWRITE|[belyy-ekran-wordpress](belyy-ekran-wordpress.html)|
|163|11208|11472|REWRITE|[upravlenie-promptom-iz-adminki](upravlenie-promptom-iz-adminki.html)|
|298|13549|11569|REWRITE|[roboty-ili-biznes-processy-bitrix24](roboty-ili-biznes-processy-bitrix24.html)|
|355|13019|11043|REWRITE|[prioritizatsiya-zadach-avtomatizatsii](prioritizatsiya-zadach-avtomatizatsii.html)|
|275|12565|10617|REWRITE|[massovye-404-posle-perenosa](massovye-404-posle-perenosa.html)|
|173|11649|11836|REWRITE|[mnogostranichnyy-pdf-iz-dannyh](mnogostranichnyy-pdf-iz-dannyh.html)|
|336|13266|12094|REWRITE|[monitoring-dostupnosti-sayta](monitoring-dostupnosti-sayta.html)|
|299|13431|11291|REWRITE|[smart-processy-bitrix24-kogda-nuzhny](smart-processy-bitrix24-kogda-nuzhny.html)|
|356|13873|11895|REWRITE|[tehnicheskoe-zadanie-na-avtomatizatsiyu](tehnicheskoe-zadanie-na-avtomatizatsiyu.html)|
|184|14074|14366|REWRITE|[statusy-broni-i-zanyatost](statusy-broni-i-zanyatost.html)|
|276|12968|10618|REWRITE|[smena-domena-sohranenie-url](smena-domena-sohranenie-url.html)|
|337|13688|11380|REWRITE|[logirovanie-form-zayavok](logirovanie-form-zayavok.html)|
|300|13457|11478|REWRITE|[prosrochennye-dela-crm-napominaniya](prosrochennye-dela-crm-napominaniya.html)|

## Смысловые решения и доказательства

### 141: pisma-s-sayta-v-crm

- Полностью прочитан source longform3 и все runtimeдополнения; исходные таблица/схема/источники/кейсы сохранены.
- Битрикс24FAQпочтыCRM открыт23.10.2026: связьписьма/объекта/прав различается; новыхуниверсальныхнастроек не обещано.
- cases.json matklass подтверждает12тестовыхсделок иdesktop/mobile проверки; это не результат привлечения12клиентов.
- Удалены два ранних пересказа идентификаторов/приёмки, добавлена предметная граница проверкиCRM и рабочего доступа.

Source mapping: `wordpress/editorial/articles/01-crm.md#id=3`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-3.md`, `wordpress/longform/manifest.json`.

### 273: www-bez-www-osnovnoy-domen

- Полностью прочитан mapped source113 и runtime схема/дополнения; все исходные значимые href/таблица/источники сохранены.
- Google canonical официальный документ открыт23.10.2026: согласование canonical/redirect/sitemap — сигналы, не гарантияSEOрезультата; Nginxreturn док сверялся вdrafts03.
- Удалены четыре ранних повторных раздела, оставлен полный технический процесс выбораимени/TLS/форм/сессии.
- Добавлен учебный контрпример одновременной смены имени и структурыURL, без выдуманного клиентского переезда.

Source mapping: `wordpress/search-content/articles/113-www-bez-www-osnovnoy-domen.md`, `wordpress/search-content/manifest.json`.

### 334: oshibki-502-504-chto-proveryat

- Полностью прочитан mapped source132 и runtimeдополнения; схема/таблица/источники сохранены.
- MDN502/504 первичные страницы открыты23.10.2026; код отделён от конкретной причины и фактического результата записи.
- Удалены ранние повторные диагностические разделы; подробные длительныеоперации/upstream/фон/приёмка оставлены.
- Добавлен учебный контрпример внешнего504 против собственного таймаута; реальные инциденты/результаты не придуманы.

Source mapping: `wordpress/search-content/articles/132-oshibki-502-504-chto-proveryat.md`, `wordpress/search-content/manifest.json`.

### 297: utm-metki-v-crm-bitrix24

- Полностью прочитан mapped source153 и runtime дополнения; таблицы словаря/маршрута/ошибок и источники сохранены.
- Битрикс24 UTM6266591 открыт23.10.2026; универсальное автоматическое поведение произвольной формы не обещается.
- Удалены пять ранних пересказов полного последовательного материала, без изменения slug/аналитическогоинтента.
- Добавлен учебный контрпример контакта и двух сделок; коммерческая атрибуция и исторические источники не выдуманы.

Source mapping: `wordpress/search-content/articles/153-utm-metki-v-crm-bitrix24.md`, `wordpress/search-content/manifest.json`.

### 152: antispam-bez-kapchi

- Полностью прочитаны longform13 и runtimeдополнения; актуальныйcase antispam24 перечитан целиком.
- Исходные подпись/время/повторы/частота/формат подтверждены cases.json;91acceptedHTTP200 не расширен до доставки писем или измеренной конверсии.
- OWASP InputValidation иW3CWAIформы — первичные источники; OWASP открыт23.10.2026, WAI ранее вэтойредактуре.
- Удалены ранние серверный/приёмочный пересказы; неподтверждённое уменьшение ошибочных отказов заменено целью с отдельным наблюдением.

Source mapping: `wordpress/editorial/articles/02-integrations.md#id=13`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-13.md`, `wordpress/longform/manifest.json`.

### 354: otsenka-effekta-avtomatizatsii

- Полностью прочитан mappedsource172 и runtimeдополнения; все таблицы, схема иIIBAисточник сохранены.
- IIBABACCM первичныйисточник открыт23.10.2026:need/value/context; конкретнаямодельручноговремени авторская.
- Удалены пять ранних пересказов подробного процесса; добавлен прозрачный учебный расчёт100*6 против80*2+20*12.
- Учебные600/400минут не выданы за клиентскуюэкономию и не переведены в прибыль или прогнозрасходов.

Source mapping: `wordpress/search-content/articles/172-otsenka-effekta-avtomatizatsii.md`, `wordpress/search-content/manifest.json`.

### 274: stranica-404-pravilnyy-otvet

- Полностью прочитан mappedsource114 и runtime схема/дополнения, все таблицы/process/sourceблоки сохранены.
- Google HTTPstatuscodes первичныйисточник проверен23.10.2026: soft404/реальныйстатус; настройкипоиска представлены как учебнаяпроверка.
- cases.json saneqtechnology подтверждаеттехническуюпроверку404/URL/redirect, безгарантии отсутствиябудущихошибок.
- Удалены ранние повторы кода/навигации/чеклиста; добавлена конкретная приёмка поискового действия и пустойвыборки.

Source mapping: `wordpress/search-content/articles/114-stranica-404-pravilnyy-otvet.md`, `wordpress/search-content/manifest.json`.

### 335: belyy-ekran-wordpress

- Полностью прочитан mappedsource133 и runtimeдополнения; таблица/процесс/источники сохранены.
- WordPress commonerrors иdebugging официальнаядокументация проверены23.10.2026; не добавлены публичные режимыотладки или командыизмененияproduction.
- Удалены ранние пересказы полного разбора, терминscoped заменён простым ограничениемпричиной.
- Добавлена учебная проверка повторногосозданияошибки через редакторскийцикл; реальныеинциденты не придуманы.

Source mapping: `wordpress/search-content/articles/133-belyy-ekran-wordpress.md`, `wordpress/search-content/manifest.json`.

### 163: upravlenie-promptom-iz-adminki

- Полностью прочитаны longform23 иruntimeдополнения; все исходные таблицы/схема/sourceблоки сохранены.
- АтрибуцияGlossCheck сужена до подтверждённого WordPressprompt/history/лимитов; точный набор админскихполей не выдан за установленныйрезультат.
- OpenAI evals иOWASP Authorization первичные источники открыты23.10.2026, технические вызовы/расходы не выполнялись.
- Удалены ранние повторные версия/границы; добавлен учебный контрпример стилевойправки, меняющей решениепри неполныхданных.

Source mapping: `wordpress/editorial/articles/03-ai.md#id=23`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-23.md`, `wordpress/longform/manifest.json`.

### 298: roboty-ili-biznes-processy-bitrix24

- Полностью прочитан mappedsource154 иruntimeдополнения; все таблицы и process/source блоки сохранены.
- Официальная справка Битрикс24 бизнес-процессов21290220 открыта23.10.2026; конкретные действия/тарифы/интерфейсы не обещаются.
- Удалены пять ранних пересказов полного процесса; добавлен контрпример двух возвращений с разным смыслом изменения.
- Повторное согласование связано с предметом решения, а неуниверсальным правилом любойстадии; пример не выдан за клиентскоевнедрение.

Source mapping: `wordpress/search-content/articles/154-roboty-ili-biznes-processy-bitrix24.md`, `wordpress/search-content/manifest.json`.

### 355: prioritizatsiya-zadach-avtomatizatsii

- Полностью прочитан mappedsource173 иruntimeдополнения; исходные таблицы/схема/источники сохранены.
- IIBABACCM первичныйтекст проверен23.10.2026:связьneed/solution/value/context; метод сравнения не выдан заобязательныйстандарт.
- Удалены пять ранних повторов дальнейших подробных разделов; добавлена предметная разница условийдопуска ивзвешенногорейтинга.
- ПримерИИпоисточникам учебный, без заявления внедренияRAG/доступовуклиентов.

Source mapping: `wordpress/search-content/articles/173-prioritizatsiya-zadach-avtomatizatsii.md`, `wordpress/search-content/manifest.json`.

### 275: massovye-404-posle-perenosa

- Полностью прочитан mappedsource115 иruntimeдополнения; сохранены таблица проявлений, process/sourceблоки ивсезначимыеhref.
- Google moveURL иHTTPcodes первичныеисточники открыты23.10.2026;Nginx/Apache различие сверено документациейпредыдущегопакета.
- Удалены пять ранних пересказов полноценного процесса восстановления; добавлен конкретный контрпример временнойзависимости вложения отстарогосервера.
- Наличие работающейглавной/статьи невыдано задоказательствополного переноса, права файлов отдельны.

Source mapping: `wordpress/search-content/articles/115-massovye-404-posle-perenosa.md`, `wordpress/search-content/manifest.json`.

### 173: mnogostranichnyy-pdf-iz-dannyh

- Полностью прочитаны longform33 иruntimeдополнения; исходные таблицы/схема/MDNисточники/ссылкакейса сохранены.
- cases.json libertygas подтверждаетA4/3фото/1–3объекта/longdescription/offline/reexport; централизованныйархиввыпусковнеприписан.
- MDNPagedMedia открыт23.10.2026;печатныйконтракт и проверкадлинноготекста авторскаяметодика, безуниверсальнойсовместимости.
- Удалены ранние повторные проверочныеразделы и неподтверждённый выводобэкономии; добавлена предметная границаданных иPDF.

Source mapping: `wordpress/editorial/articles/04-documents.md#id=33`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-33.md`, `wordpress/longform/manifest.json`.

### 336: monitoring-dostupnosti-sayta

- Полностью прочитан mappedsource134 иruntimeдополнения; таблицауровней/схема/источники сохранены.
- GoogleHTTPcodes проверен23.10.2026:200 не является доказательством полезнойоперации; WordPresshardening первичныйисточник открыт.
- Удалены четыре ранних повтора полного разбора; добавлен контрпример смешениясинтетическогоиреальногопотока.
- Не заявляется установленный мониторингклиента или круглосуточнаяреакция; методпроверкибезреальных отправок вэтойзадаче.

Source mapping: `wordpress/search-content/articles/134-monitoring-dostupnosti-sayta.md`, `wordpress/search-content/manifest.json`.

### 299: smart-processy-bitrix24-kogda-nuzhny

- Полностью прочитан mappedsource155 иruntimeдополнения; все матрицы/паспорт/схема/источники сохранены.
- ОфициальнаяинструкцияБитрикс24 смартпроцессов18913884 проверена23.10.2026; конкретнаядоступностьфункцийоговорена порталом.
- Удалены пять ранних пересказов полного проектирования; добавлен контрпример разныхжизненныхциклов сделкиисервиса.
- Не заявлено готовое внедрение сервисногосмартпроцесса вреальномклиентскомпроекте.

Source mapping: `wordpress/search-content/articles/155-smart-processy-bitrix24-kogda-nuzhny.md`, `wordpress/search-content/manifest.json`.

### 356: tehnicheskoe-zadanie-na-avtomatizatsiyu

- Полностью прочитан mappedsource174 иruntimeдополнения; все исходные таблицы/процесс/источники сохранены.
- IIBA4.4.3traceability первичныйтекстпроверен23.10.2026; версияТЗнеобъявляетсясертифицированной.
- cases.json crmhrrequirements подтверждаетподготовленноеТЗ READYFORDECOMPOSITION; прямоотделеноотвнедренияобмена, беззакрытыхимён/ID.
- Удалены пять ранних повторных разделов; добавлен реальный аналитическийрезультат и предметнаяпроверка идентичности/неоднозначности.

Source mapping: `wordpress/search-content/articles/174-tehnicheskoe-zadanie-na-avtomatizatsiyu.md`, `wordpress/search-content/manifest.json`.

### 184: statusy-broni-i-zanyatost

- Полностью прочитан longform43 иruntimeдополнения; все исходные таблицы/схема/источники сохранены.
- Неподтверждённая атрибуция календарного прототипа заменена явной моделью требований; невыдано завнедрение.
- GoogleCalendarEvents первичныйисточник проверен23.10.2026:status/transparencyразличны; собственные удержания не приписаныGoogleавтоматически.
- Удалены два ранних повторных раздела; добавлена конкретная граница позднегоподтверждения послеистечения и новойброни.

Source mapping: `wordpress/editorial/articles/05-booking.md#id=43`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-43.md`, `wordpress/longform/manifest.json`.

### 276: smena-domena-sohranenie-url

- Полностью прочитан mappedsource116 иruntimeдополнения; исходные таблица/схема/источники сохранены.
- GoogleSiteMove первичныйисточник прочитан23.10.2026:URLmapping/redirects/медиа/разделениеизменений,безгарантиипоисковоготрафика.
- cases.json shumakovayookassa подтверждаетзаявку/payment/webhook/servercheck; явноневыдано закейсдоменноймиграции.
- Удалены пять ранних пересказов процесса; добавлена конкретная разница browserreturn и servernotification, безреальныхплатежей.

Source mapping: `wordpress/search-content/articles/116-smena-domena-sohranenie-url.md`, `wordpress/search-content/manifest.json`.

### 337: logirovanie-form-zayavok

- Полностью прочитан mappedsource135 иruntimeдополнения; все таблицы/схема/sourceблоки/кейсhref сохранены.
- Nginx logmodule первичныйисточникпроверен23.10.2026; WordPresshardening ранее вэтомпакете. Новые строкиконфигурации/секреты не добавлялись.
- Удалены пять ранних повторов полногоразбора;Scopeзаменён простым ограничениемнужнымсайтом.
- Новый учебный пример различает временныеотметки/часовыепояса/идентичностьоперации, безюридическихобещаний иложнойатрибуцииклиенту.

Source mapping: `wordpress/search-content/articles/135-logirovanie-form-zayavok.md`, `wordpress/search-content/manifest.json`.

### 300: prosrochennye-dela-crm-napominaniya

- Полностью прочитан mappedsource156 иruntimeдополнения; все таблицы/схема/sourceпанель сохранены.
- Официальная Битрикс24инструкция18942844 проверена23.10.2026:персональныенапоминанияневыданызаавтоматическуюуправленческуюэскалацию.
- Удалены пять ранних повторов полногоразбора; добавлен учебныйконтрпримерустаревшегосигналапри ужеизменённомделе.
- Отправкауведомлениянеприравненакпринятиюпоручения; круглосуточнаяреакция/ростпродажнеобещаются.

Source mapping: `wordpress/search-content/articles/156-prosrochennye-dela-crm-napominaniya.md`, `wordpress/search-content/manifest.json`.

## Общие проверки

- Actual gate:20/20 complete, 10577–14366 знаков; все counts и candidate SHA256 совпали с manifest.
- 79 исходных верхнеуровневых блоков таблиц, process-map и editorial-sources сохранены побайтно, включая ссылки SVG/CSV.
- PHP WordPress parser:2525 блоков, непустых freeform-блоков0. Это не native validation Gutenberg.
- Baseline SHA256 всех20, существование mapped sources и значимые исходные href проверены; добавленные case slugs валидны.
- Личного Telegram нет. Общих абзацев≥240 знаков в3+ статьях нет. Whitespace check каждого HTML прошёл.
- READER_TEST_NOT_RUN; VISUAL_PREVIEW_NOT_RUN; NATIVE_GUTENBERG_VALIDATION_NOT_RUN. Технический gate не заменяет редакторское и визуальное review.

## Границы фактов

В152 уменьшение ошибочных отказов обозначено как цель отдельного наблюдения:91 accepted/HTTP200 подтверждают приём обработчиком, а не доставку почты и измеренный рост конверсии. В163 точный набор настроек GlossCheck сужен до подтверждённых prompt/history/лимитов; управление произвольной глубиной истории через админку не приписано.

В184 неподтверждённый календарный прототип заменён явной моделью требований и приёмки. Google Calendar приведён только как технический пример разделения status/transparency. Сроки удержания и реализация у клиента не выдуманы. В356 подтверждён аналитический пакет READY FOR DECOMPOSITION, прямо отделённый от внедрения обмена. В276 работающий платёжный кейс иллюстрирует разные контрольные точки; он не назван кейсом доменного переезда.

Все числовые примеры эффективности в354 прямо учебные и не переведены в обещанную прибыль. Первичные источники, открытые23.10.2026, поддерживают относящиеся к теме механизмы: Битрикс24 mail/UTM/smart processes/бизнес-процессы/напоминания; Google HTTP/canonical/site move; MDN502/504/paged media; OWASP validation/authorization; IIBA BACCM/traceability; Nginx logging; WordPress debugging/hardening. Текущие тарифы, цены и номера версий не добавлялись; платных API-вызовов, сообщений и реальных платежей не было.

Сохранённые таблицы не упрощались даже при возможной редактуре термина: действует требование побайтной неизменности артефактов. Исходные документальные ссылки остаются в source panels каждого кандидата.

Следующий шаг — независимое редакторское review, затем отдельная preview-проверка изменённых сценариев. Пакет заморожен; дальнейшие правки только по точным замечаниям родителя. Временные backup, дополнительные runtime-процессы и копии исходных тел не создавались. Редактируемые кандидаты и отчёт — полезный результат до приёмки и Git-фиксации.
