# drafts-13: FROZEN

20 полных Gutenberg HTML — **NEEDS_EDITORIAL_REVIEW**. Публикационного одобрения нет. Исходные тела, WordPress, очередь, статусы, SEO-метаданные и approval hashes не изменялись. Родитель собирает scoped commit.

Каждый mapped source прочитан целиком; все runtime-дополнения прочитаны после сопоставления с исходным текстом. Решения индивидуальны: полезная подробность сохранена, повторы удалены, добавлены предметные проверки и только подтверждённые факты. KEEP означает сохранение сильного основного разбора с конкретизацией контакта и указанными точечными редакторскими правками; REWRITE — смысловую переработку или уточнение фактической границы. Дополнительные главы ради объёма не добавлялись.

Авторизованное исключение href: личный Telegram заменён принятыми support-ботами Telegram/MAX. Все значимые сервисные, документальные и SVG/CSV-ссылки сохранены. CTA от первого лица предлагают конкретный первый материал для разбора без бесплатного аудита, обещанных сроков и коммерческого результата.

## Before → after

Знаки измерены actual `sy3_editorial_quality`. Точные baseline/candidate SHA256 и source mapping находятся в manifest.json.

| ID | Знаки до | Знаки после | Решение | Кандидат |
|---|---:|---:|---|---|
|146|11963|12095|REWRITE|[edinaya-forma-zayavok-na-obuchenie](edinaya-forma-zayavok-na-obuchenie.html)|
|544|11975|11869|KEEP|[beget-moodle-vebinar-dostupy](beget-moodle-vebinar-dostupy.html)|
|545|11671|11569|KEEP|[beget-authelia-vnutrennie-instrumenty](beget-authelia-vnutrennie-instrumenty.html)|
|562|12342|12193|KEEP|[beget-seafile-peredacha-dokumentov](beget-seafile-peredacha-dokumentov.html)|
|156|12207|12284|REWRITE|[zhurnal-integracii-dlya-biznesa](zhurnal-integracii-dlya-biznesa.html)|
|563|11537|11465|KEEP|[beget-vikunja-obrashcheniya-podderzhki](beget-vikunja-obrashcheniya-podderzhki.html)|
|564|11997|11910|KEEP|[beget-markdown-baza-reglamentov](beget-markdown-baza-reglamentov.html)|
|565|11143|11023|KEEP|[beget-lms-vybor-shared-vps](beget-lms-vybor-shared-vps.html)|
|167|11286|11479|REWRITE|[ai-gotovit-chelovek-podtverzhdaet](ai-gotovit-chelovek-podtverzhdaet.html)|
|566|11631|11508|KEEP|[beget-obuchenie-dashbord-pokazateli](beget-obuchenie-dashbord-pokazateli.html)|
|567|11631|11550|KEEP|[beget-multiakkaunt-klienty](beget-multiakkaunt-klienty.html)|
|568|12069|11969|KEEP|[beget-dostupy-karta-proekta](beget-dostupy-karta-proekta.html)|
|177|11581|11794|REWRITE|[istoriya-pravok-dokumenta](istoriya-pravok-dokumenta.html)|
|569|12000|11893|KEEP|[beget-dokumenty-zakryvayushchie](beget-dokumenty-zakryvayushchie.html)|
|570|12967|12856|KEEP|[beget-support-zapros-diagnostika](beget-support-zapros-diagnostika.html)|
|571|12064|11978|KEEP|[beget-api-inventarizaciya](beget-api-inventarizaciya.html)|
|188|12963|13248|REWRITE|[shag-kalendarya-i-dlitelnost-broni](shag-kalendarya-i-dlitelnost-broni.html)|
|572|12556|12455|KEEP|[beget-plavayushchiy-ip-pereezd](beget-plavayushchiy-ip-pereezd.html)|
|573|13834|13723|KEEP|[beget-uslugi-nenuzhnyy-resurs](beget-uslugi-nenuzhnyy-resurs.html)|
|512|12030|11995|KEEP|[beget-systemd-fonovyy-servis](beget-systemd-fonovyy-servis.html)|

## Смысловые решения и доказательства

### 146: edinaya-forma-zayavok-na-obuchenie

- REWRITE: полностью прочитаны longform7/runtime+cases.json bitrix24-training. Удалены ранние краткие повторы; факты внедрения сохранены отдельным точным разделом.
- Подтверждены две ветки, отдельные поля CRM, задача в нужномпотоке,IDсотрудника и ручноеназваниекурса; экономия/эффектнеизмерялись.
- Дополнительные тесты авторvsучастник,сменаветки,уточнениеvsновыйзапрос явно методические, не новые результаты кейса.
- Б24user.get/tasks.task.add открыты23.10.2026; описание методаAPIнеявляетсядоказательствомготовности конкретного процесса/прав.

Source mapping: `wordpress/editorial/articles/01-crm.md#id=7`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-7.md`, `wordpress/longform/manifest.json`.

### 544: beget-moodle-vebinar-dostupy

- KEEP: полный source285/runtime содержит две учебные диагностики,картуролей,матрицу и триприкладныхартефакта, не обзор продукта.
- Точная BegetMoodle/BigBlueButtonинструкция открыта23.10.2026; совместимость/запись/вместимостьнеобещаныбезпроверкисвоейконфигурации.
- Входадминистратораневыданзаприёмкуучастника; завершениевстречинеравнодоступностизаписи, примерынеклиентскийрезультат.
- Убраны обобщённое «мы» и отрицательное коммерческое обещание; affiliateпрозрачен, CTAличныйспредметнымпервымшагом.

Source mapping: `wordpress/beget-content/articles/285-beget-moodle-vebinar-dostupy.md`, `wordpress/beget-content/manifest.json`.

### 545: beget-authelia-vnutrennie-instrumenty

- KEEP: полное чтение source286/runtime; уже различены authentication/authorization, proxy/OIDC,локальныероли,машинныйдоступ и активныесеансы, с двумяпредметнымиконтрпримерами.
- BegetAuthelia и официальный OpenIDConnectProvider открыты23.10.2026. Совместимостькаждогопотребителя/немедленныйотзывнеобещаныбезпроверки.
- Нет заявления о внедрении всемклиентам/универсальнойгранулярности; примерыусловные.
- Убраны обобщённое мы/маппинг, affiliateотпервоголица с участвующимиуслугами; CTAпервыйограниченныйконтур.

Source mapping: `wordpress/beget-content/articles/286-beget-authelia-vnutrennie-instrumenty.md`, `wordpress/beget-content/manifest.json`.

### 562: beget-seafile-peredacha-dokumentov

- KEEP: source287/runtime полностью прочитаны; реестр,комплектность,принятаяредакция,доступ и замечания подробноразобраны, сохраняетсяметодическаяценность.
- BegetSeafile открыт23.10.2026; установка/синхронизациянеприравненыкделовомупринятию, свойствассылки/историипроверяютсянасвоейконфигурации.
- Учебныестарыйфайл/чужойпроект не названы клиентскими фактами. Убрана служебная редакторская фраза «безвыдуманногоотнесения» из публичногорассказа.
- Affiliateуточнён, личныйCTAведёткреестру и конкретному действию, исходныеартефакты/ссылкинеизменны.

Source mapping: `wordpress/beget-content/articles/287-beget-seafile-peredacha-dokumentov.md`, `wordpress/beget-content/manifest.json`.

### 156: zhurnal-integracii-dlya-biznesa

- REWRITE: полное чтение longform17/runtime, удалены три ранних кратких повтора с общей личной формулировкой, сохранён основной управленческий разбор.
- Добавлена отдельная последовательность: неизвестныйисход→ручнаясверка→подтверждение либо похожийчужойобъект; техническаяисториянепереписывается.
- OWASPLogging/CloudEvents первичка прочитана23.10.2026; событиясвязываютсясоперацией,попыткинеприравненыкобязательствам.
- Нет заявления о клиентскомвнедрении/измеренномсокращении времени; личныйCTAнаконкретныйуправленческийвопрос.

Source mapping: `wordpress/editorial/articles/02-integrations.md#id=17`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-17.md`, `wordpress/longform/manifest.json`.

### 563: beget-vikunja-obrashcheniya-podderzhki

- KEEP: полный source288/runtime ужесодержатпаспортзаявки,дваразборапровала,приоритет,шаблонрезультатаиматрицу; дополнительныеобщиеглавыненужны.
- BegetVikunja открыт23.10.2026; предлагаемыестатусы/приёмканеобъявленыгарантированнымифункциямикаждойверсии.
- Заявительненужентехническийдиагноз,пометкаисполнителянеприравненакрезультату,универсальныеSLAненазначены.
- Affiliateотпервоголица/участвующиеуслуги и предметныйCTA; таблицы/процесс/документальныеhrefсохранены.

Source mapping: `wordpress/beget-content/articles/288-beget-vikunja-obrashcheniya-podderzhki.md`, `wordpress/beget-content/manifest.json`.

### 564: beget-markdown-baza-reglamentov

- KEEP: полностью прочитаны source289/runtime. Текст ужедаётпаспорт,каркас,дваконтрпримера,матрицу и связьзадача→инструкция→выход; наполнениевместообщихглав.
- BegetMarkdown и CommonMark0.31.2 открыты23.10.2026. Возможноститаблиц/расширенийнеприписанылюбомурендереру; форматнезаменяетактуальность/полномочие.
- Не раскрытывнутренниерегламенты/проекты, прикладныеситуацииусловные; рольGitreviewневыданазапредметноепринятие.
- Вступление очищеноотненужнойдисклеймернойформы, affiliateуточнён/первое лицо, CTAпредлагаетоднупроцедуру.

Source mapping: `wordpress/beget-content/articles/289-beget-markdown-baza-reglamentov.md`, `wordpress/beget-content/manifest.json`.

### 565: beget-lms-vybor-shared-vps

- KEEP: полный source290/runtime ужесодержитпрофильсемисценариев,данные,обязанности,двеучебныедиагностики и матрицу, без универсального users-per-server.
- Точные BegetMoodle/sharedVPS и ограниченияхостинга открыты23.10.2026; параметрытарифов/версийнекопировались, возможностьвсехплагиновнеобещана.
- Во вступлении служебнаяфраза «нетвыдуманных...» замененапредметнымвыходом, обобщённое «мы» убрано.
- Affiliateотпервоголицаиучаствующихуслуг, CTAобозначаетпрофильзанятия и проверку; примерыневнедрения.

Source mapping: `wordpress/beget-content/articles/290-beget-lms-vybor-shared-vps.md`, `wordpress/beget-content/manifest.json`.

### 167: ai-gotovit-chelovek-podtverzhdaet

- REWRITE: полное чтение longform27/runtime; убраны ранние резюме и недостаточно подтверждённое личное заявление о реализации привязки подтверждения.
- Добавлены конкретные проверки адресата/вложения/безвредногооформления,инструкциивисточнике,двойногосогласия; не дублируют общий обзор Human-in-loop.
- OWASPLLMprimary открыт23.10.2026: promptinjection/excessiveagency рассматриваются как основания негативныхтестов, не сертификатзащиты.
- НетAPIкодовыхрецептов/придуманныхкейсов/обещанийбезошибочности. Сохранены матрицы,схема,источники.

Source mapping: `wordpress/editorial/articles/03-ai.md#id=27`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-27.md`, `wordpress/longform/manifest.json`.

### 566: beget-obuchenie-dashbord-pokazateli

- KEEP: полностью прочитаны source291/runtime; содержательнызнаменатель,состояния,версиикурса,очередьпроверяющих,контрактвыгрузки, не обзордашбордов.
- Официальный Moodle4.05ActivityCompletion и BegetMoodleReview открыты23.10.2026; просмотр/оценкаразличены, завершениенеравнодоказанномунавыку.
- Двадиагностическихпримераучебные, эффектперераспределенияпроверяющихусловный и требуетизмерений, не результатыклиента.
- Убраны служебныйдисклеймеросcылке/обобщённоемы, affiliateсусловием, CTAотпервоголица.

Source mapping: `wordpress/beget-content/articles/291-beget-obuchenie-dashbord-pokazateli.md`, `wordpress/beget-content/manifest.json`.

### 567: beget-multiakkaunt-klienty

- KEEP: полное чтение source292/runtime. Деловое управление,оплата,техническиеполномочия и связанныересурсы разведены,естьдваприкладныхпримераиматрица.
- BegetМультиаккаунты/Настройкиаккаунтапроверены23.10.2026: переключениенеобъявленоизолированнойделегацией,изменениерегистрации/доменаотдельныерешения.
- Нет юридического выводаособственностиифактовклиентскихпереездов; примерыне реальное внедрение.
- Вступлениеупрощено, партнёрскоераскрытиеуточнено и личныйCTAописываетодинпроект.

Source mapping: `wordpress/beget-content/articles/292-beget-multiakkaunt-klienty.md`, `wordpress/beget-content/manifest.json`.

### 568: beget-dostupy-karta-proekta

- KEEP: полный source293/runtime содержит8-польныйреестр,жизненныйциклчеловеческих/машинныхролей,ротациюпотребителей и двеконкретныедиагностики.
- BegetНастройкиаккаунта открыт23.10.2026; наличиекартыподтверждаетсябезопаснымсценарием,анестрокойвдокументе.
- Нет поиска неизвестныхсекретов,необоснованногоизменениявладения,обещаниянемедленногоотзывавсехсеансов; все примерыусловные.
- Извступленияубранылишниеотрицательныепредупреждения, смыслзащищённогохранениясохранён; affiliateусловный, CTAличный.

Source mapping: `wordpress/beget-content/articles/293-beget-dostupy-karta-proekta.md`, `wordpress/beget-content/manifest.json`.

### 177: istoriya-pravok-dokumenta

- REWRITE: полное чтение longform37/runtime; удалено неподтверждённое личное утверждение о юридическом прототипе и три ранних кратких повторения.
- В cases.json нет подтверждения этого прототипа; текст оформлен как методика, не внедрение/юридическаяуслуга.
- Добавлены связанныепункты и различие принятоготекста/изменившихсяподстановочныхданных — конкретные тесты содержания, неводадляобъёма.
- IIBARequirementsDesigns первичка открыта23.10.2026: прослеживаемостьрешенийнеравнапубликации/отправке. Сохранены матрицы/источник/схема.

Source mapping: `wordpress/editorial/articles/04-documents.md#id=37`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-37.md`, `wordpress/longform/manifest.json`.

### 569: beget-dokumenty-zakryvayushchie

- KEEP: полностью прочитаны source294/runtime. Реестр/период/плательщик/профильныйспециалистразличены,естьдвеучебныедиагностики и картырешений.
- Точный BegetДокументы открыт23.10.2026; состав/периодпроверяютсяваккаунте,текущиецены/срокинеперенесены. Нетбухгалтерских/юридическихрекомендаций.
- Вступление и служебнаяфразапроэкспертнуюстатью приведеныкчитателю/личнойроли. Теханалитикнепринимаетфинансовыерешения.
- Affiliateусловный/первое лицо,безобещанийэкономии; существующиеартефактыпобайтносохранены.

Source mapping: `wordpress/beget-content/articles/294-beget-dokumenty-zakryvayushchie.md`, `wordpress/beget-content/manifest.json`.

### 570: beget-support-zapros-diagnostika

- KEEP: полное чтение source295/runtime; карточкадиагностики,хронология,последняяподтверждённаяграницаидваразборауже предметны.
- Точная BegetПомощьподдержкаоткрыта23.10.2026; канал/историяневыписаныкакгарантированныйSLAилиполномочиеисполнятьсоветнаобщемресурсе.
- Служебнаяфраза «мы неотправляемврамкахстатьи» замененаполезнымправиломобращениясданными; реальныеинцидентыневымышлены.
- Affiliateусловный/первое лицо, CTAпомогаетподготовитьодинвоспроизводимыйэпизод, безбесплатныхработ/сроков.

Source mapping: `wordpress/beget-content/articles/295-beget-support-zapros-diagnostika.md`, `wordpress/beget-content/manifest.json`.

### 571: beget-api-inventarizaciya

- KEEP: source296/runtimeполностьюпрочитаны; сильныпродуктовыйконтракт,полнота,составнойключ,подтверждённоеотсутствие и очередьрасхождений.
- Beget.APIХостинга открыт23.10.2026: хостингиоблачныйAPIразделены, отдельнаяcloudJWTдокументациясуществует. НебылоаутентифицированныхAPIвызовов.
- Read-onlyмеханизмнеобещанкакреальныйограниченныйтокенпоставщика,широкиеправаобозначеныограничением; примерыусловные.
- Убран службныйдисклеймерпроисполнениестатьёй,техническаяграницачтениясохранена; affiliateусловный/первое лицо.

Source mapping: `wordpress/beget-content/articles/296-beget-api-inventarizaciya.md`, `wordpress/beget-content/manifest.json`.

### 188: shag-kalendarya-i-dlitelnost-broni

- REWRITE: полностью прочитаны longform47/runtime; удалены краткие повторы и неподтверждённое заявление о параметрах календарного прототипа.
- Добавлен рассчитанный контрпример следующейподготовки: после15:15освобождения,10минподготовки →нераньше15:25приучебныхусловиях; отдельноучтенакратностьначала.
- RFC5545 первичка открыта23.10.2026: DTSTART/DTEND/DURATION/timezoneотносятсякданным,анексеткеUI. Экспортнезаявленготовойфункцией.
- Нет клиентского внедрения/универсального норматива. Все таблицы,точныеисходныечисла и схема сохранены.

Source mapping: `wordpress/editorial/articles/05-booking.md#id=47`, `wordpress/editorial/calendar.json`, `wordpress/longform/articles/article-47.md`, `wordpress/longform/manifest.json`.

### 572: beget-plavayushchiy-ip-pereezd

- KEEP: полный source297/runtime содержиткартусостояния,дваразбораграницыданных/двойногоисполнения и возвратпосленовыхзаписей, сильныйприкладнойтекст.
- BegetFloatingIPv4 открыт23.10.2026: переносвтойжелокации,неразныелокации; сохранённыйIPv4необъявленпереносомданных/исходящихмаршрутов.
- Все диагностическиеситуацииусловные, небылооперацийнадIP/серверами. Простои/скорость/автовозвратнеобещаны.
- Служебнаяфразавступлениязамененапредпосылками, affiliateотпервоголица/участвующиеуслуги, CTAпредметный.

Source mapping: `wordpress/beget-content/articles/297-beget-plavayushchiy-ip-pereezd.md`, `wordpress/beget-content/manifest.json`.

### 573: beget-uslugi-nenuzhnyy-resurs

- KEEP: полный source298/runtime содержитпаспорт,картузависимостей,двеучебныедиагностики,матрицудоказательств и условияудержания — предметнаяметодика.
- BegetУправлениеуслугами открыт23.10.2026: sharedквотынеприравненыкудалениюVPS,текущиецены/кнопкинеперенесены.
- НизкийCPU/названиетестнедоказательствоненужности,экономиянеобещана,фактическогоудаления/расходовнебыло.
- Убраны служебныеупоминанияотсутствиякоманд/созданиякопийстатьёй; affiliateусловный и личный CTA.

Source mapping: `wordpress/beget-content/articles/298-beget-uslugi-nenuzhnyy-resurs.md`, `wordpress/beget-content/manifest.json`.

### 512: beget-systemd-fonovyy-servis

- KEEP: полное чтение source299/runtime; триуровнясостояния,версия,путьзадания,неизвестныйвнешнийисход и последствиявосстановленияуже подробноразобраны.
- Официальный systemd.service открыт23.10.2026: lifecycle/Typeразличаются,activeневсегдаправильныйэталонразовойслужбы. Реальныеunit/serviceнеизменялись.
- Техническиепоказателинеобъявленыполучениемзаказа,примерыусловные,универсальныерестарты/интеграционныегарантиинепредлагаются.
- Heartbeat/scope/production замененыпростымрусскимвнеprotectedартефактов, affiliateусловный/CTAличный.

Source mapping: `wordpress/beget-content/articles/299-beget-systemd-fonovyy-servis.md`, `wordpress/beget-content/manifest.json`.

## Проверки

- Actual gate:20/20 complete, 11023–13723 знаков; counts и candidate SHA256 совпали с manifest.
- 83 исходных верхнеуровневых блоков таблиц, process-map и editorial-sources сохранены побайтно, включая SVG/CSV.
- PHP WordPress parser:2629 блоков, непустых freeform-блоков0. Это не native validation Gutenberg.
- Baseline SHA256, source files, значимые исходные href и валидность добавленных case slugs проверены. Личного Telegram нет.
- Общих абзацев≥240 знаков в3+ статьях нет. Whitespace check HTML прошёл.
- READER_TEST_NOT_RUN; VISUAL_PREVIEW_NOT_RUN; NATIVE_GUTENBERG_VALIDATION_NOT_RUN. Gate не заменяет редакторское и визуальное review.

## Границы фактов

Первичные материалы проверены23.10.2026: Битрикс24user.get/tasks.task.add; BegetBigBlueButton/Moodle/sharedVPS/ограниченияхостинга; Moodle405ActivityCompletion; BegetAuthelia/Seafile/Vikunja/Markdown и CommonMark0.31.2; официальный Autheliaoverview/authorization/openid-connect-1.0 (сначала ошибочный overview/authentication/... недоступен, затем найден и прочитан правильный URL); OWASPLogging/LLM; BegetМультиаккаунты/Настройкиаккаунта/Документы/Поддержка/APIХостинга/плавающийIPv4/Управлениеуслугами; IIBARequirementsDesigns; RFC5545; systemd.service. Все vendorвозможности ограниченыточнойконфигурацией, без тарифов/версий/гарантий нагрузки. BegetAPIхостинг≠облако; плавающийIPv4втойжелокации, не переносданных; Moodlecompletionнеравнодоказанномунавыку; proxy/OIDCненазначаютвсеправаприложения. Реальный кейс обучения146проверенпоточномуcases.json bitrix24-training: две ветки/CRM/поток/IDсотрудника, названиевручную, экономия/эффектнеизмерены.177/188неподтверждённыепрототипыневыданызареализацию;167подтверждаемоеAIдействие — методика,неклиентскийрезультат. Учебныечисла188иинтервалыявносинтетические. Все15affiliateровно1href/relсохранены, первое лицо и доходтолько заучаствующиеуслуги; AIшлюзнепредлагается. Protectedартефактысохраненыдажепристарыхслужебныхформулировках. НативнаяGutenberg/визуальная/читательскаяпроверканезапускалась.

Проверенные первичные источники и границы технических утверждений указаны в evidence каждой статьи; документальные ссылки сохранены в source panels. Текущие цены и тарифы не копировались, платных API-вызовов, реальных сообщений и платежей не было. Сохранённые таблицы не упрощались даже при возможной редактуре термина: действует требование побайтного сохранения артефактов.

Следующий шаг — независимое редакторское review, затем отдельная preview-проверка изменённых сценариев. Пакет заморожен; дальнейшие правки только по точным замечаниям родителя. Временные backup, дополнительные runtime-процессы и копии исходных тел не создавались. Кандидаты и отчёт — полезный результат до приёмки и Git-фиксации.
