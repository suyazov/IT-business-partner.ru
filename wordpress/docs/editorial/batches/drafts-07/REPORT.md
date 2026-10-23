# drafts-07: FROZEN

20 полных Gutenberg HTML — **NEEDS_EDITORIAL_REVIEW**. Публикационного одобрения нет. Исходные тела, WordPress, очередь, статусы, SEO-метаданные и approval hashes не изменялись. Родитель собирает scoped commit.

Каждый mapped source прочитан целиком; все runtime-дополнения прочитаны после сопоставления с исходным текстом. Решения индивидуальны: полезная подробность сохранена, повторы удалены, добавлены предметные проверки и только подтверждённые факты. KEEP означает сохранение сильного основного разбора с конкретизацией контакта и указанными точечными редакторскими правками; REWRITE — смысловую переработку или уточнение фактической границы. Дополнительные главы ради объёма не добавлялись.

Авторизованное исключение href: личный Telegram заменён принятыми support-ботами Telegram/MAX. Все значимые сервисные, документальные и SVG/CSV-ссылки сохранены. CTA от первого лица предлагают конкретный первый материал для разбора без бесплатного аудита, обещанных сроков и коммерческого результата.

## Before → after

Знаки измерены actual `sy3_editorial_quality`. Точные baseline/candidate SHA256 и source mapping находятся в manifest.json.

| ID | Знаки до | Знаки после | Решение | Кандидат |
|---|---:|---:|---|---|
|142|12002|11860|REWRITE|[neaktivnye-sotrudniki-v-crm](neaktivnye-sotrudniki-v-crm.html)|
|304|13310|11264|REWRITE|[obuchenie-i-priemka-crm](obuchenie-i-priemka-crm.html)|
|361|13088|11001|REWRITE|[karta-integratsiy-biznes-sistem](karta-integratsiy-biznes-sistem.html)|
|342|14156|12858|REWRITE|[dostupnost-form-cheklist](dostupnost-form-cheklist.html)|
|153|12607|12574|REWRITE|[kak-izmerit-rabotu-antispama](kak-izmerit-rabotu-antispama.html)|
|362|13229|11018|REWRITE|[podderzhka-posle-vnedreniya-crm](podderzhka-posle-vnedreniya-crm.html)|
|343|14799|12431|REWRITE|[priemka-sayta-prakticheskiy-cheklist](priemka-sayta-prakticheskiy-cheklist.html)|
|363|12853|10923|REWRITE|[kak-vybrat-biznes-analitika](kak-vybrat-biznes-analitika.html)|
|164|11267|11409|REWRITE|[gostevoy-ai-chat](gostevoy-ai-chat.html)|
|368|13980|11541|REWRITE|[wordpress-ne-otpravlyaet-pisma](wordpress-ne-otpravlyaet-pisma.html)|
|369|13794|11529|REWRITE|[antispam-form-server-check](antispam-form-server-check.html)|
|475|12525|12420|KEEP|[beget-ai-gateway-responses-404](beget-ai-gateway-responses-404.html)|
|174|11241|11401|REWRITE|[versii-shablonov-dokumentov](versii-shablonov-dokumentov.html)|
|476|12834|12725|KEEP|[ai-beget-byudzhet-pilota](ai-beget-byudzhet-pilota.html)|
|477|12875|12779|KEEP|[beget-ai-gateway-kluch-server](beget-ai-gateway-kluch-server.html)|
|478|12174|12041|KEEP|[beget-ai-gateway-test-modeli](beget-ai-gateway-test-modeli.html)|
|185|12804|13041|REWRITE|[kalendar-vnutri-sdelki-crm](kalendar-vnutri-sdelki-crm.html)|
|479|12184|12102|KEEP|[beget-ai-gateway-ochered-zayavok](beget-ai-gateway-ochered-zayavok.html)|
|480|12436|13228|REWRITE|[beget-ai-gateway-kontekst-dannyh](beget-ai-gateway-kontekst-dannyh.html)|
|481|12087|13244|REWRITE|[beget-embeddings-baza-znaniy](beget-embeddings-baza-znaniy.html)|

## Смысловые решения и доказательства

### 142: neaktivnye-sotrudniki-v-crm

- Полностью прочитаны longform4 иruntimeдополнения; сохранены все таблица/схема/sourceпанель/кейсhref.
- ОфициальныйБитрикс24материал18001440 оCRMуволенногосотрудника проверен23.10.2026; точныекаскадныепоследствия непредполагаются.
- Подтверждённыйдвернойкейс ограничен почтовымдубликатом/доступомвладельцаитрёхменеджеров; массоваяпередачаисторическихсделокневымышлена.
- Удалены ранние повторы и неподтверждённое «дешевле»; добавлен учебный разбор техническойзависимостиотстарого пользователя.

Source mapping: `wordpress/editorial/articles/01-crm.md#id=4`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-4.md`, `wordpress/longform/manifest.json`.

### 304: obuchenie-i-priemka-crm

- Полностью прочитан mappedsource160 иruntimeдополнения; все таблицы/схема/источники сохранены.
- ОфициальнаяБитрикс24ролеваямодель23240636 сверена вdrafts03; обучениеподадминистраторомневыданозапроверкурабочейроли.
- Удалены пять ранних пересказов подробныхучебныхразделов; добавлен контрольпереносанавыкасидеальногообразцанаповторного клиента.
- Новыхметрикэффективностиобучения/реальныхклиентскихрезультатовнет; примерявноучебный.

Source mapping: `wordpress/search-content/articles/160-obuchenie-i-priemka-crm.md`, `wordpress/search-content/manifest.json`.

### 361: karta-integratsiy-biznes-sistem

- Полностью прочитан mappedsource179 иruntimeдополнения; все исходные таблицы/схема/sourceпанель сохранены.
- ОфициальныйсправочникRESTБитрикс24 открыт23.10.2026; универсальнаясемантикаполейилидоступностьметодов не утверждаются.
- Удалены пять ранних пересказов подробной карты; добавлена авторская проверка отсутствующее/пустое/явноочищаемоеполе.
- Учебныйобмен невыдан заклиентскоевнедрение; секреты/платежи/частныеURLневключались.

Source mapping: `wordpress/search-content/articles/179-karta-integratsiy-biznes-sistem.md`, `wordpress/search-content/manifest.json`.

### 342: dostupnost-form-cheklist

- Полностью прочитан mappedsource140 иruntimeдополнения; все таблицы/схема/источники сохранены.
- W3CWAIForms первичныйисточник открыт23.10.2026;полнаяWCAGсертификацияилиуниверсальнаядоступностьнеобещаются.
- cases.json realflacon подтверждаетроссийскуюмаску+servervalidation; кейсу не приписанынеизмереннаядоступностьиростконверсии.
- Удалены четыре ранних повтора; добавлен конкретный разбор ограниченного телефонногоформата и пользовательскихисправлений.

Source mapping: `wordpress/search-content/articles/140-dostupnost-form-cheklist.md`, `wordpress/search-content/manifest.json`.

### 153: kak-izmerit-rabotu-antispama

- Полностью прочитаны longform14 иruntimeдополнения; таблица/схема/sourceпанель/кейсhref сохранены.
- OWASPLogging первичныйисточникпроверен23.10.2026;подробныетелазаявок/секретынепредлагаетсякопироватьвжурнал.
- Сохранёноподтверждение91контрольнойформы безприписанногодолгосрочногопроцента;метафразаопоказеэкспертизызамененапрактическимсоставомотчёта.
- Удалены два ранних повтора; добавлен конкретный контрпример сменыклассификациивместоизмененияфильтра.

Source mapping: `wordpress/editorial/articles/02-integrations.md#id=14`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-14.md`, `wordpress/longform/manifest.json`.

### 362: podderzhka-posle-vnedreniya-crm

- Полностью прочитан mappedsource180 иruntimeдополнения; все таблицы/схема/sourceпанель сохранены.
- PMI Rolesresponsibilitiesresources первичныйматериалпрочитанвdrafts03;общийпринциппередачиневыданзаSLA24/7.
- Удалены пять ранних пересказов подробнойстабилизации; добавлен разбор дефектапринятойформы и нового канала.
- Нетвыдуманныхсроковсопровождения/результатовклиента;приоритетновогообъёмаостаётсярешениемвладельца.

Source mapping: `wordpress/search-content/articles/180-podderzhka-posle-vnedreniya-crm.md`, `wordpress/search-content/manifest.json`.

### 343: priemka-sayta-prakticheskiy-cheklist

- Полностью прочитан mappedsource141 иruntimeдополнения; все исходные таблицы/процесс/источники сохранены.
- W3CWAIForms проверен вэтойредактуре; web.devVitals первичныйисточник открыт23.10.2026,баллневыданзавеськлиентскийпуть.
- cases.json saneqtechnology подтверждает53/53technical,41equipment,14desktop/mobile; числаразныхработнеобъединенывметрикуэффекта.
- Удалены пять ранних повторныхразделов; добавлен предметный разборохватаадресов/шаблонов/действий.

Source mapping: `wordpress/search-content/articles/141-priemka-sayta-prakticheskiy-cheklist.md`, `wordpress/search-content/manifest.json`.

### 363: kak-vybrat-biznes-analitika

- Полностью прочитан mappedsource181 иruntimeдополнения; все таблицы/схема/sourceпанель сохранены.
- IIBABACCM первичныйтекстсверен23.10.2026 вdrafts05; выдуманныесертификаты/команда/личныедостижениянепривнесены.
- Удалены пять ранних пересказов подробноговыбора; добавлена предметная проверка двухнесовпадающихграницсрока.
- Пример учебный:необещанфиксированныйсрок/бесплатностьанализа,решениебизнесаприпротиворечиинеузурпируется.

Source mapping: `wordpress/search-content/articles/181-kak-vybrat-biznes-analitika.md`, `wordpress/search-content/manifest.json`.

### 164: gostevoy-ai-chat

- Полностью прочитаны longform24 иruntimeдополнения; все исходные таблицы/схема/sourceпанель/GlossCheckhref сохранены.
- АтрибуцияGlossCheckсужена до подтверждённыхhistory/guest/auth/сообщений/токенов/частоты;неприписанточныйадминскийконтрольпроизвольнойдлиныконтекста.
- OWASPAuthorization/OpenAIproductionпервичныедокументыпрочитанывэтойредактуре;платныевызовыневыполнялись.
- Удалены ранние повторные регистрация/чеклист; добавлен конкретный контрпримерлимитовипродолженияпри двухвкладках.

Source mapping: `wordpress/editorial/articles/03-ai.md#id=24`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-24.md`, `wordpress/longform/manifest.json`.

### 368: wordpress-ne-otpravlyaet-pisma

- Полностью прочитан mappedsource186 иruntimeдополнения; таблица/процесс/sourceпанель/значимыеhref сохранены.
- WordPresswp_mail/wp_mail_failed иGoogleSenderGuidelines первичныеисточникипроверены23.10.2026;trueнеобъявленгарантиейвходящих.
- Удалены пять ранних пересказов подробнойцепочки; добавлена конкретная развилка общийящик→пересылка→менеджер.
- Не предлагаютсяготовыеDNSзначения/пароли/публичныежурналы; реальныхписемвэтойработенет.

Source mapping: `wordpress/search-content/articles/186-wordpress-ne-otpravlyaet-pisma.md`, `wordpress/search-content/manifest.json`.

### 369: antispam-form-server-check

- Полностью прочитан mappedsource187 иruntimeдополнения; таблица/схема/sourceпанель/кейсhref сохранены.
- OWASPInputValidation первичныйтекстпрочитан23.10.2026;проверкаподлинностипризнакаразделенаотналичияполя,безкодаобходачужойзащиты.
- Удалены пять ранних пересказов полногоразбора; добавлены различающие отрицательныепроверки признака/контекста.
- 24сайта/91форма остаются подтверждённымохватомдругогокейса, неперенесены автоматическинаформу читателя.

Source mapping: `wordpress/search-content/articles/187-antispam-form-server-check.md`, `wordpress/search-content/manifest.json`.

### 475: beget-ai-gateway-responses-404

- Полностью прочитан mappedBeget203 ивсеruntimeдополнения. Основной разбор уже последовательный и предметный; KEEP без добавления главы ради объёма.
- Begetai-gateway официально перепроверен23.10.2026:ResponsesAPIнеподдерживается,404можетотноситьсякмаршруту/модели/разрешениюключа;это неуниверсальныйдиагноз.
- Исходныйучебныйредакторневыданзаклиентскоевнедрение; сохраненытаблица/схема/источники иединственнаяпартнёрскаяссылка сраскрытием.
- ИзменёнтолькоконтактныйCTAнаконкретныйпервыйшагипринятыеsupportbots; платныхвызовов/реальныхотправокнет.

Source mapping: `wordpress/beget-content/articles/203-beget-ai-gateway-responses-404.md`, `wordpress/beget-content/manifest.json`.

### 174: versii-shablonov-dokumentov

- Полностью прочитаны longform34 иruntimeдополнения; все исходные таблицы/схема/sourceпанель/кейсhref сохранены.
- Неподтверждённаяатрибуция реализованногоконструктораверсий заменена требованиямипроектирования;вступлениеотделеноотправовогоутверждения.
- cases.json bitrix24doors подтверждает4DOCXшаблона,нонеавтоматическийархивверсий;этиграницыпряморазделены.
- IIBAtraceability сверено23.10.2026; удалены ранние повторы, добавлена конкретная пара скачиваниеистории/новыйвыпуск.

Source mapping: `wordpress/editorial/articles/04-documents.md#id=34`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-34.md`, `wordpress/longform/manifest.json`.

### 476: ai-beget-byudzhet-pilota

- Полностью прочитан mappedBeget204 иruntimeдополнения. KEEP:полныйпоследовательныйразборужеразделяетвызов/полезныйрезультат/проверку/эксплуатацию,безраннегодублирующегорезюме.
- Begetai-gatewaymanual иофициальнаястраницауслугиоткрыты23.10.2026:расходзависитотмоделиииспользования;текущиецены/бесплатность/фиктивнаяэкономиянедобавлялись.
- Сохранены исходнаятаблица/схема/sourceпанель ировнооднапартнёрскаяссылкаскороткимраскрытием.
- ИзменёнтолькоконтактныйCTAнапервыйконкретныйшагисupportbots;финансовыйрезультатневыдуман,платныхвызововнет.

Source mapping: `wordpress/beget-content/articles/204-ai-beget-byudzhet-pilota.md`, `wordpress/beget-content/manifest.json`.

### 477: beget-ai-gateway-kluch-server

- Полностью прочитан mappedBeget205 иruntimeдополнения. KEEP:основнойматериалпредметноразделяетвнешнийключ/правапользователя/хранение/замену/диагностику.
- BegetAIруководствопроверено23.10.2026,OWASPSecretsManagement первичныйисточник открыт; сервису не приписаны неизвестныеузкиеправапроектов.
- УпрощёнанглийскийзаголовокSharedhosting,конкретизированCTA;общиедублирующиеглавынедобавлялись.
- Таблица/схема/sourceпанель/партнёрскаяссылкаскороткимраскрытиемсохранены;реальныеключи/пути/доступынечитались.

Source mapping: `wordpress/beget-content/articles/205-beget-ai-gateway-kluch-server.md`, `wordpress/beget-content/manifest.json`.

### 478: beget-ai-gateway-test-modeli

- Полностью прочитан mappedBeget206 иruntimeдополнения. KEEP:полнаяметодикасравненияужевключаетобычный/неполный/противоречивыйвход,совместимость,возврат.
- BegetAIофициальноеруководствопроверено23.10.2026:моделииподдерживаемыевозможностипроверяютсяпосценарию;текущийрейтинг/ценынедобавлены.
- Финальнаянеподтверждённаяформулировкауменьшенияслучайныхизмененийзамененапроверяемымсоставомрешения;CTAпервоголица.
- Исходныетаблица/схема/sourceпанель/однапартнёрскаяссылкаскороткимраскрытиемсохранены;платныхэкспериментовнет.

Source mapping: `wordpress/beget-content/articles/206-beget-ai-gateway-test-modeli.md`, `wordpress/beget-content/manifest.json`.

### 185: kalendar-vnutri-sdelki-crm

- Полностью прочитаны longform44 иruntimeдополнения; все таблицы/схема/sourceпанель сохранены.
- Неподтверждённаяатрибуциякалендарногоклиентскогопроектазамененаявнойпроектноймоделью;снятобезусловноезаявлениеуменьшенияручныхдействий.
- OWASPAuthorizationпервичныйтекстсверен23.10.2026;сервернаяпроверкаотделенаотпоказаокна.
- Удалены два ранних повторныхраздела;добавлен конкретный разборизмененияправмеждуоткрытиемисохранением.

Source mapping: `wordpress/editorial/articles/05-booking.md#id=44`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-44.md`, `wordpress/longform/manifest.json`.

### 479: beget-ai-gateway-ochered-zayavok

- Полностью прочитан mappedBeget207 иruntimeдополнения. KEEP:полныйразборужеразличаетзаявку/задание/попытку,частичноесостояние,старуюочередьиактуальность.
- BegetAIруководство иMicrosoftAzureRetry первичныеисточникипроверены23.10.2026;идемпотентность/очередьнеприписаныпровайдерупоумолчанию.
- Неподтверждённаякоммерческаяформулировка «сохраняетпродажи» суженадомаршрутаприёмаиучётазатрат;CTAпервоголица.
- Таблица/схема/sourceпанель/однапартнёрскаяссылкаскороткимраскрытиемсохранены;реальныесообщения/заданияневыполнялись.

Source mapping: `wordpress/beget-content/articles/207-beget-ai-gateway-ochered-zayavok.md`, `wordpress/beget-content/manifest.json`.

### 480: beget-ai-gateway-kontekst-dannyh

- Полностью прочитан mappedBeget208 иruntimeдополнения; основные разделы ужесильные,сохранены таблица/схема/sourceпанель/партнёрскаяссылка.
- REWRITEточечногофакта:Begetруководство23.10.2026 прямоограничиваетPII/payment/credentials/tradesecrets,внутреннееразрешениевладельцанедостаточно.
- Добавлен предметный раздел о применимости картыданныхкправиламсервиса;PIIвтаблиценеобъявленаразрешённойпередачейчерезBeget.
- OWASPPromptInjectionпервичныйисточникпроверен23.10.2026;модельныйответнеприравненкпроверкефактическихдействий.

Source mapping: `wordpress/beget-content/articles/208-beget-ai-gateway-kontekst-dannyh.md`, `wordpress/beget-content/manifest.json`.

### 481: beget-embeddings-baza-znaniy

- Полностью прочитан mappedBeget209 иruntimeдополнения;сильнаяметодикакорпуса/версий/прав/фрагментов/переиндексациисохранена.
- Begetруководство23.10.2026 подтверждаетembeddingsиограниченияпередаваемыхданных;добавленконкретныйразборвнешнегоэтапаиндексации.
- ОфициальныеQdrantFilteringиpgvectorREADMEпроверены23.10.2026;поддержкаметаданных/векторовнеобъявленаавтоматическойавторизацией.
- REWRITEуточненияграницданных;исходныетаблица/схема/sourceпанель/однапартнёрскаяссылкаскороткимраскрытиемсохранены.

Source mapping: `wordpress/beget-content/articles/209-beget-embeddings-baza-znaniy.md`, `wordpress/beget-content/manifest.json`.

## Проверки

- Actual gate:20/20 complete, 10923–13244 знаков; counts и candidate SHA256 совпали с manifest.
- 74 исходных верхнеуровневых блоков таблиц, process-map и editorial-sources сохранены побайтно, включая SVG/CSV.
- PHP WordPress parser:2543 блоков, непустых freeform-блоков0. Это не native validation Gutenberg.
- Baseline SHA256, source files, значимые исходные href и валидность добавленных case slugs проверены. Личного Telegram нет.
- Общих абзацев≥240 знаков в3+ статьях нет. Whitespace check HTML прошёл.
- READER_TEST_NOT_RUN; VISUAL_PREVIEW_NOT_RUN; NATIVE_GUTENBERG_VALIDATION_NOT_RUN. Gate не заменяет редакторское и визуальное review.

## Границы фактов

В174 неподтверждённое внедрение полного конструктора версий заменено проектной моделью;4 DOCX-шаблона дверного кейса прямо отделены от автоматического архива. В185 календарный маршрут описан как проектирование и приёмка, а не завершённая интеграция клиента. В164 сужены приписанные GlossCheck параметры. В342 маска российского номера Real Flacon не выдана за доказательство полного соответствия WCAG. В343 числа SANEQ53/53,41 и14 относятся к разным видам выполненных работ и не сложены в метрику SEO-эффекта.

Beget AI Gateway перепроверен по текущему руководству23.10.2026: Chat Completions/embeddings поддержаны, Responses API не поддерживается;404 не имеет единственной причины. В480/481 добавлена существенная граница: разрешение компании не отменяет правила сервиса о непередаче персональных/платёжных/учётных данных и коммерческой тайны; внешняя передача при embeddings рассматривается отдельно от прав выдачи. Семь Beget-материалов сохранили ровно по одной партнёрской ссылке с коротким раскрытием. В475–479 сильные полные разборы сохранены по решению KEEP, а не дополнены общими главами.

Остальные первичные материалы: WordPress wp_mail/wp_mail_failed, Google Sender Guidelines, OWASP Input Validation/Logging/Authorization/Secrets/Prompt Injection, Microsoft Azure Retry, IIBA BACCM/traceability, W3C WAI Forms, web.dev Vitals, Qdrant Filtering и pgvector README. Личные заявления о законченных клиентах, сертификаты, показатели конверсии и результаты платных экспериментов не добавлялись.

Проверенные первичные источники и границы технических утверждений указаны в evidence каждой статьи; документальные ссылки сохранены в source panels. Текущие цены и тарифы не копировались, платных API-вызовов, реальных сообщений и платежей не было. Сохранённые таблицы не упрощались даже при возможной редактуре термина: действует требование побайтного сохранения артефактов.

Следующий шаг — независимое редакторское review, затем отдельная preview-проверка изменённых сценариев. Пакет заморожен; дальнейшие правки только по точным замечаниям родителя. Временные backup, дополнительные runtime-процессы и копии исходных тел не создавались. Кандидаты и отчёт — полезный результат до приёмки и Git-фиксации.
