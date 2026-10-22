# IT-business-partner.ru — Project Context

## Развёрнутые статьи: второй пакет

Result: подготовлен второй пакет из 63 материалов: 43 новых SEO-черновика и расширение 20 существующих статей. Canonical содержит 87 новых SEO-текстов, 30 расширений старых и десять дополнений публичных статей; отдельно сохранена опубликованная статья Beget 202. Все готовые тексты имеют более 10 000 содержательных знаков, таблицы, предметные схемы и скачиваемые SVG/CSV.

Current state: второй пакет опубликован после независимого PASS exact `6b102715ff7e79fde47e867f67d2f7bd28607d5c`; PR #15 merged `1c24c3473d1ecc9f6ed7e18d49563c5a6b80f276`. Production содержит 198 принадлежащих проекту статей, 128 проходят аудит, минимум 11 914 знаков; статусы 187 draft/11 publish. В браузерном редакторе проверены все 198 уникальных записей с устойчивой сортировкой REST по ID: 0 невалидных блоков, 128 схем. Новая статья — 105 валидных блоков, без JS/HTTP ошибок. Отпечатки нецелевых записей, защищённых полей и метаданных совпали до/после; даты, статусы, обложки и расписание сохранены. Evidence `batch2-{rollout,production-audit,live-editor-checks}.json`. Статья об ИИ-шлюзе опубликована по отдельному выбору владельца: https://sy3.ru/journal/ai-shlyuz-beget-crm-sayt-processy/ (23.10.2026 00:07:41 МСК, ID 326), 19 593 знака, четыре таблицы, схема. PR #14 merged `eeb73dbabdcdebe0fd659f284a8cb91b5a56bce1`, PASS exact `f6f1a20fd7dacd736418097fee023f68a720fb06`. Отпечатки всех прежних записей и метаданных совпали до/после; favicon404 исправлен локальными SVG/ICO. Издатель 1.2.0 исключает отдельную публикацию из часов очереди; 26 новых и 11 прежних сценариев пройдены. Evidence: `beget-{rollout,production-audit,live-checks,live-http-checks}.json`, `batch2-{preview-audit,editor-checks}.json` в private каталоге задачи.

Next action: следующий пакет оставшихся первоначальных материалов и безопасное объединение непересекающихся авторских пакетов Beget. Компилятор проверяет идентичность заданий, не допускает двух готовых версий и выход пути за каталог; шесть сценариев прошли в audit-only режиме без изменения canonical. Три автора дописывают оставшиеся тексты первоначального плана и 99 материалов Beget отдельными непересекающимися пакетами. Итоговая цель — 311, готовность всех материалов пока не заявляется. Production pause=1; системный таймер не установлен, отдельный точный запрос authority остаётся без ответа. Новые backups не создаются. Второй сайт не меняется. Временные файлы нужны для продолжающегося этапа, очистка под storage lock после завершения, контрольный срок 23.10.2026 18:00 МСК.

## Развёрнутые статьи и дополнительная серия Beget

Result: опубликован первый проверенный пакет: 44 новых SEO-черновика, расширения десяти прежних черновиков и всех десяти публичных статей. В каждом основной текст ≥10 000 видимых знаков без схем/источников/контактных блоков, практические таблицы, разборы процессов, индивидуальная редактируемая схема и SVG/CSV для скачивания. Повторяющийся общий текст не засчитывается в готовность. Прежние блоки публичных материалов сохраняются.

Current state: production содержит 154 принадлежащие проекту статьи (144 draft/10 public), из них 64 прошли новый аудит; минимум готовых — 11 914 знаков. PR #12 merged `9db8a9e`, независимый PASS exact `65806bfe19f0bdb607f3e9626e2e64ae60aab4a6`. Все 154 записи валидны в Gutenberg, десять публичных URL содержат таблицы/схемы, файлы скачиваются, новые черновики закрыты. Издатель прошёл 11 прежних и 25 новых сценариев, включая отдельные часы 72/24 и отпечаток заголовка/анонса/текста/обложки. При live-аудите обнаружен автоматический сброс дат создания десяти старых draft: исправлен updater (PR #13 merged `51b754e`, PASS exact `819b210212cf5687b48810a263be7cb2cd038494`), даты восстановлены по доказанным границам последовательного импорта. Первый общий before-digest не был записан до остановки, поэтому полное исходное сравнение всех метаданных не заявляется. Повторный импорт изменил ноль постов/метаданных; все 100 исходных статусов и плановых дат проверены по календарю. Схема не увеличивает счётчик основного текста. Настоящий MariaDB lock проверен двумя соединениями. Private evidence: `longform-{rollout,production-audit,date-repair,repeat-audit,live-editor-checks,live-http-checks}.json`, `search-mysql-lock-check.json`. При прямом просмотре SVG выявлен отдельный favicon404; локальный значок подготовлен в следующем пакете.

Next action: продолжать остальные статьи без снижения порога качества. По поручению владельца добавлены 100 Beget-тем (план, не готовые тексты), 76 официальных страниц проверены; итоговый план — 311 материалов. Первая полная статья 202 об ИИ-шлюзе (19 593 знака, четыре таблицы, схема) прошла preview: 96 валидных блоков, responsive 1440/390, партнёрский rel, SVG/CSV. Владелец отдельно выбрал её немедленную самостоятельную публикацию после review; она не входит в ежедневную очередь и не меняет её интервал. Остальные 99 — черновики с плановыми ежедневными местами 31.01–09.05.2027 после предыдущей сотни. Партнёрская ссылка `https://beget.com/p133852` подтверждена владельцем/HTTPS; технические ссылки прямые, партнёрский CTA sponsored/nofollow с раскрытием участия. Кабинет Beget в карте/ожидаемых защищённых файлах не найден, для контента он не нужен. API-расходов нет. Три автора продолжают прежний scope параллельно. Production pause=1, системный таймер ещё не установлен — требуется ранее запрошенная отдельная точная authority. Новых backups нет; временный профиль нужен для следующих материалов, очистка после этапа под storage lock, контрольный срок 23.10.2026 18:00 МСК.

## Светлая подача, анимация и расширение услуг — 1.4

Result: подготовлена новая главная sy3.ru по выбранному владельцем направлению Uplab: крупная типографика, светлый фон, проекты перед услугами, настоящий портрет и шесть основных направлений. Декоративные стрелки убраны; конечная анимация появления блоков учитывает reduced motion. Добавлены 12 посадочных страниц, включая 1С и BI: профильные подрядчики выполняют специализированную работу, Артём ведёт постановку задачи и интеграцию результата. Собственные внедрения 1С/BI и показатели клиентов не выдумываются.

Current state: опубликовано 22.10.2026 в 23:16 МСК после независимого PASS exact `7a0ecfd37f005ef89912993755095111a4bc4c7a`; PR #11 merged `03f5d08375cd59f400dbeb84d6d054cef86e5f49`. На production повторены девять сценариев 320/390/1440 px: нет переполнения, сломанных изображений, стрелок и JS/HTTP ошибок; при reduced motion нет анимации. Редактор: главная 108 валидных блоков, все 16 услуг валидны; about/контакты сохранены. Sitemap содержит 16 услуг, llms.txt — новые направления, feed — 25 кейсов. Отпечатки всех прежних нецелевых записей/метаданных совпали; 100 draft, 10 public и пауза очереди сохранены. Второй сайт не менялся. HTTPS проверен через настоящий домен/SNI/сертификат с локальным разрешением IP; независимая внешняя проверка не заявляется. Private evidence: `direction-{rollout,live-checks,live-editor-checks,live-http-checks}.json` в `/root/preview/sy3-wordpress-20261022/private/`.

Next action: три автора расширяют корпус до 211 статей по требованию ≥10 000 видимых знаков, таблиц и предметных схем; незавершённые и повторяющиеся тексты не считаются готовыми. Новая SEO-серия — отдельная очередь ежедневно после проверки, прежняя серия — раз в три дня. Таймер по-прежнему не установлен, очередь на паузе; второй сайт не меняется. Новые backups не создавать. Временный профиль нужен для проверки схем/статей; очистка после этапа под storage lock, контрольный срок 23.10.2026 18:00 МСК.

## Личный образ автора и доверие — sy3.ru 1.3

Result: 22.10.2026 опубликована главная с настоящим портретом Артёма, прямым обращением и понятным предложением «Сайты. CRM. ИИ.». На телефоне портрет и имя видны перед заголовком; на компьютере отдельная карточка автора. Фото и персональное обращение добавлены в блок знакомства, about, контакты, подпись статьи и боковой блок материалов. Договорённости об объёме, стоимости и приёмке объясняют порядок работы; отзывы и бизнес-показатели не выдумываются.

Current state: PR #9 merged `d1afbee430e4d069abfbe4752248474d1a2b218c`; независимый PASS exact `7f826c7f026403a22b81403311c33bfeffcda28a`. Портрет получен из публичного профиля https://t.me/moi_imperator по прямому указанию владельца; JPEG 320×320 без генерации/ретуши и увеличения в вёрстке. Локальный ассет не зависит от Telegram CDN. На production повторены 10 браузерных сценариев HTTPS на 1440/390 px: изображения и мобильное меню без переполнения и JS/HTTP ошибок. Production-редактор: главная 109 корректных Gutenberg-блоков; about 27, контакты 23, несохранённых изменений нет. Портрет совпадает с исходным SHA, CSS 1.3.0, sitemap/llms.txt/feed доступны, feed содержит 25 кейсов. Проверка HTTPS через локальное разрешение IP сохраняет настоящий домен/SNI/проверку сертификата; внешняя независимая проверка не заявляется. Updater `wordpress/tools/refine-author.php` обновил только содержимое трёх страниц после проверки хешей. Отпечатки всех прочих записей/метаданных и полей title/excerpt/status/slug целевых страниц совпали до/после; 100 черновиков и пауза очереди сохранены. Файлы второго сайта не менялись.

Next action: владелец оценивает опубликованную главную и личное представление; эффект на обращения ещё не измерен. Второй сайт и ожидающий отдельного разрешения таймер не затрагивать. Новые backups не создавались. Private QA: `/root/preview/sy3-wordpress-20261022/private/author-{rollout,live-checks,live-editor-checks,live-http-checks,cleanup}.json`. Собственные preview/browser остановлены; очистка точных временных файлов отложена — общий storage lock занят другой задачей. Контрольный срок 23.10.2026 18:00 МСК; Git, опубликованное фото, доступы и receipts сохраняются.

## Создание сайтов и веб-сервисов — отдельная услуга

Result: 22.10.2026 опубликовано четвёртое направление https://sy3.ru/services/web-development/: лендинги, корпоративные сайты, каталоги, магазины, личные кабинеты, бронирование и ИИ-приложения. Страница связывает предложение с шестью разрешёнными кейсами; CMS/стек описаны по реальным проектам. Главная, about, footer и llms.txt отражают компетенцию веб-разработки; на главной шесть выбранных проектов, включая Читай-ку и СТИЛСПЭЙС. Новая услуга первая в каталоге и на главной.

Current state: PR #7 merged `45fdd9260a4291a1d7362fe99cfd7ab6b3c9cbc4`; независимый PASS exact `62bad929ac9af9210028439c950dfe5c42b341f5`. Production: 4 услуги и 10 редакционных черновиков со связью с новым направлением. Пять браузерных сценариев HTTPS на 1440/390 px: главная, услуга и каталог, изображения и мобильное меню без переполнения и JS/HTTP ошибок. Проверены sitemap новой услуги, llms.txt, feed из 25 кейсов и CSS 1.2.1. HTTPS использует настоящий домен/SNI/сертификат с локальным разрешением IP; внешняя независимая проверка не заявляется. Scoped updater защитил ручные тексты/анонсы; изменены новая услуга, две страницы и связи 21 кейса/10 черновиков. Отпечатки текстов, заголовков, анонсов, статусов, дат, обложек и расписания всех 100 статей совпали до/после; очередь остаётся на паузе.

Next action: оценить новую страницу услуги и обращения по направлению веб-разработки. Второй сайт и ожидающее отдельного разрешения расписание не затрагивать. Новые backups не создавались по прямому указанию владельца. Private QA: `/root/preview/sy3-wordpress-20261022/private/web-service-{rollout,live-browser-checks,live-http-checks,cleanup}.json`. Временный браузер и preview остановлены; очистка точных собственных временных файлов отложена, общий storage lock занят PID 1565692. Контрольный срок 23.10.2026 18:00 МСК; Git, доступы, исходники и receipts сохраняются.

## Редакционная очередь: 100 черновиков в WordPress

Result: 22.10.2026 на sy3.ru загружены 100 новых экспертных статей (~23,5 тыс. слов), с 15 обложками, рубриками, анонсами, ссылками на разрешённые кейсы и обращением к Артёму. Смешанный календарь через 72 часа: 25.10.2026 10:00 МСК — 18.08.2027 10:00 МСК. Источники: 25 Share-кейсов и ограниченное исследование 94 GitHub-репозиториев, 24 безопасных алиаса. Прототипы и аудиты не названы внедрениями; закрытый клиент анонимизирован. Тексты, CSV и источники: `wordpress/editorial/`.

Current state: PR #5 merged `f4c8ae28c91de0485bdc7dc78d89f6f49aaf9bf5`; независимое PASS exact `176f41c1bc1305e4f752db75d482c1e6272c7e9a`. На production ровно 100 draft, 100 уникальных текстов с обложками, 0 новых publish/future. Прежние 10 публичных статей и 25 кейсов сохранены. Все 100 Gutenberg-документов валидны, календарь показывает 100 строк, закрытый предпросмотр с обложкой работает без JS/HTTP ошибок. Два публичных адреса черновиков возвращают 404, REST показывает прежние 10 статей. Издатель прошёл 11 изолированных сценариев; в SQLite заменён только транспорт MySQL lock. Плагин установлен, очередь приостановлена. Таймер НЕ установлен и НЕ включён: запрошено отдельное точное разрешение на два systemd unit, daemon-reload и enable по AGENTS.md; ответ ожидается.

Next action: по ответу владельца оставить 100 черновиков с очередью на паузе либо проверить настоящий MySQL lock, установить только `sy3-editorial.service/.timer`, включить расписание и обновить контекст. Новых публикаций до 25.10 не должно быть. Отдельная статья снимается с очереди в редакторе; общая пауза — «Записи → Календарь публикаций». Private evidence: `/root/preview/sy3-wordpress-20261022/private/editorial-{rollout,production-audit,live-browser-checks,cleanup}.*`. Полезные исследования сохранены в private/editorial-research; ненужные скриншоты и профиль прошлого этапа удалены под storage lock. Текущий preview/browser остановить после завершения; срок очистки 23.10.2026 18:00 МСК. Новые backups не создавались; доступы, исходные сайты и второй сайт не менялись.

## Дизайн sy3.ru 1.2 — опубликовано 22.10.2026

Result: обновлён только sy3.ru. Основной текст 18–20 px, новая главная с четырьмя выбранными проектами и прямым обращением в Telegram, 12 новых редакционных изображений, новая обложка дверного CRM-кейса, свежий публичный скриншот Маткласса и отдельная обложка каждой из 10 статей. Каталог 25 кейсов, тексты материалов и статусы сохранены. Подробности/промпты: `wordpress/docs/DESIGN.md`.

Current state: PR #3 принят как `878c32655f27a7a6dc7c0a81dac062fa715bbb3c`; независимое review PASS exact `e1b45f9faf0c744ab56fe4a7cd1058ca0aae769a`. Проверены 11 HTTPS-сценариев на 335/390/768/1440 px: главная, журнал, кейс, статья, услуги, контакты и меню, без переполнения, сломанных видимых изображений и ошибок JS/HTTP. Редактор production: 100 корректных блоков, clean. Sitemap, llms.txt, sales feed, каталог и CSS 1.2.0 возвращают 200. Проверки HTTPS используют домен/SNI и локальное разрешение IP; внешняя независимая проверка не заявляется.

Updater меняет только 15 целевых записей после общей предварительной проверки хешей, исходных/целевых заголовков, анонсов и обложек конкретных записей. Три негативных сценария ручных правок блокируются до записи. Личный сайт, Nginx, DNS, базы и доступы не менялись; новые бэкапы не создавались по прямому распоряжению владельца. Receipts: `/root/preview/sy3-wordpress-20261022/private/redesign-{rollout,live-checks,live-editor-checks,cleanup}.json`.

Next action: владелец оценивает новую главную и журнал; дальнейшая работа со вторым сайтом — после основного, по его приоритету. Эффект на обращения/продажи ещё не измерен. Предпросмотр остановлен. Очистка собственных временных браузерных файлов и reference/error screenshots отложена: общий storage lock занят другой задачей; контрольный срок 23.10.2026 18:00 МСК. Результаты проверок, графику, доступы и исходные сайты сохранять.

## WordPress: sy3.ru и личный сайт — опубликовано 2026-10-22

Result: sy3.ru and it-business-partner.ru now use separate WordPress 7.1.3 installations with MariaDB and Russian dashboards. Main site: 3 services, 10 journal articles, 25 AFFiNE Share cases and 23 native covers. Personal site: author/approach, four selected works on home, links to all 25 cases. Existing portfolio and blog routes have explicit redirects to matching current material. Source PR #1 merged as `532ea91c5b03d527eb98d6c1012d22207a62a82d`; independent reviewer passed exact head `bde81d80807c99446d6a11a4c70cef584f1efb47`. No paid API, DNS, mail or Telegram changes.

Production: `/var/www/sy3-wordpress`, `/var/www/itbp-wordpress`; separate MariaDB schemas/users `sy3_wp_20261022`, `itbp_wp_20261022`; existing PHP 8.4 FPM socket `/var/run/php-fpm-sy3-ru.sock`. Only two domain vhosts changed; TLS and existing includes retained. `/api/` on the personal domain still reaches its original Node service. Legacy images/videos are served from the new personal root. Original source directories and the Node service remain intact, including dirty/staged/untracked work.

Owner authority: explicitly approved one independent reviewer and this exact production scope after successful review/checks. Subsequently explicitly cancelled backups and ordered deletion. The current-task backup directory `/var/lib/bridge-sy9/backups/wordpress-two-sites-20261022` was deleted under the storage lock (88,149,698 bytes). No further backups were created; other projects, existing backup jobs, original sites, Git and receipts were preserved. This is a current-task override, not a global backup-policy change. A prior lock-guard error during backup creation is recorded in the private operational receipt; later shared-resource mutations acquired locks inside the mutating process.

Checked: clean official WordPress ZIP passed core checksums (the preview tar extraction had truncated long paths and was not used for production). Production HTTPS checks through loopback with actual domain/SNI/certificate verification: 64 HTML pages, 60 resources, 21 exact redirects, sitemap children, llms.txt and two 25-case feeds. Public-IP hairpin is unavailable from this host; the external web checker did not provide a reliable fresh response, so independent outside-network availability is not claimed. Main and personal browser layouts rendered with WebGL, loaded images and no overflow. Main editor: 112 valid blocks; authenticated REST draft creation and permanent deletion verified. Both admin logins and ru_RU verified. User collection requests are forced through WordPress context=edit, plus a REST-dispatch capability guard covering JSON body precedence; anonymous requests remain 401, XML-RPC remains blocked. Final domain configurations are in `wordpress/deploy/nginx/`.

Access: protected root-owned 0600 files `/root/.config/client-access/sy3.ru/wordpress.env` and `/root/.config/client-access/it-business-partner.ru/wordpress.env`; see `wordpress/docs/ACCESS_MAP.md`. No credentials in Git. Runtime config files are root:php-sy3 0640. Use HTTPS=on SERVER_PORT=443 for CLI content import so embedded block assets keep HTTPS. Re-import preserves editor text/status/cover choices; source hash conflicts stop instead of overwriting edits.

Next action: owner checks both live sites and begins editing through `/wp-admin/`; see `wordpress/docs/EDITOR.md`. Measure enquiries after launch; no traffic, sales or savings uplift is claimed. Private rollout/check receipts are under `/root/preview/sy3-wordpress-20261022/private/`. Temporary installers, original image downloads and QA profiles must be cleaned under the shared storage lock; review deadline 2026-10-23 18:00 Moscow, preserve receipts and useful editor work. Protected preview credentials are not disposable backups.

Operational note: during the earlier preview stage an erroneous Network.clearBrowserCookies cleared shared server Chrome cookies. All subsequent browser tests used owned isolated contexts; production authentication never modified shared cookies.

## Обзор
Сайт-визитка + блог для IT-business-partner.ru. SPA на React с бэкендом на Hono + tRPC, ORM — Drizzle + MySQL.

---

## Стек

| Layer | Tech |
|-------|------|
| Frontend | React 19, TypeScript, Vite 7, Tailwind CSS 3.4 |
| Backend | Hono 4.8, tRPC 11 |
| ORM | Drizzle ORM 0.45 + mysql2 |
| DB | MySQL |
| Build | esbuild (сериализация API), Vite |
| Animations | gsap, animejs, three (DataCloud), @react-three/fiber |
| UI | shadcn/ui (@radix-ui/*, class-variance-authority, tailwind-merge) |
| Dev | vitest, prettier, eslint |

---

## Структура проекта (корень = `app/`)

```
app/
├── api/                  # Бэкенд (Hono + tRPC)
│   ├── boot.ts           # Точка входа, сервер Hono, tRPC adapter
│   ├── context.ts        # tRPC контекст
│   ├── middleware.ts     # Middleware (auth, publicQuery)
│   ├── router.ts         # Главный роутер tRPC
│   ├── routers/
│   │   └── blog.ts       # Блог-роутер
│   ├── lib/
│   │   ├── env.ts        # Валидация env
│   │   ├── http.ts       # HTTP-хелперы
│   │   └── vite.ts       # serveStaticFiles для продакшена
│   └── queries/
│       └── connection.ts # Проверка подключения к БД
├── db/
│   ├── schema.ts         # Drizzle-схемы (posts)
│   ├── relations.ts
│   ├── seed.ts
│   └── migrations/
├── src/                  # Фронтенд
│   ├── App.tsx           # Главный компонент
│   ├── main.tsx          # Точка входа
│   ├── providers/        # tRPC провайдер
│   ├── components/       # UI-компоненты (Navigation, Typewriter, DataCloud и т.д.)
│   ├── sections/         # Секции страницы (Hero, About, Services и т.д.)
│   ├── pages/            # Маршруты (Home, Blog, BlogPost)
│   └── lib/              # Утилиты
├── contracts/            # tRPC контракты (если есть)
├── public/               # Статика
├── package.json
├── vite.config.ts
├── tsconfig.json
└── drizzle.config.ts
```

---

## Переменные окружения

Файл `.env.example`:
```
APP_ID=                 # Application ID
APP_SECRET=             # JWT signing secret
DATABASE_URL=           # MySQL connection string (mysql://user:pass@host:port/db)
```

---

## tRPC Роутеры

- `ping` — публичный query, возвращает `{ ok: true, ts }`
- `blog` — CRUD для постов (см. `api/routers/blog.ts`)

---

## Схема БД (Drizzle MySQL)

Таблица `posts`:
| Поле | Тип | Описание |
|------|-----|----------|
| id | serial PK | Автоинкремент |
| slug | varchar(255) unique | URL-идентификатор |
| title | varchar(255) | Заголовок |
| excerpt | varchar(500) | Аннотация |
| content | text | Текст поста |
| metaTitle | varchar(255) | SEO title |
| metaDescription | varchar(500) | SEO desc |
| keywords | varchar(500) | Ключевые слова |
| coverImage | varchar(500) | URL обложки |
| published | boolean default true | Опубликован |
| createdAt | timestamp defaultNow | Дата создания |
| updatedAt | timestamp onUpdateNow | Дата обновления |

---

## Скрипты

| Команда | Описание |
|---------|----------|
| `npm run dev` | Dev-сервер, порт 3000 |
| `npm run build` | Сборка (`vite build` + esbuild для API) |
| `npm run start` | Продакшен (`NODE_ENV=production node dist/boot.js`) |
| `npm run lint` | ESLint |
| `npm run check` | TypeScript check |
| `npm run format` | Prettier |
| `npm run test` | Vitest |
| `npm run db:generate` | Генерация миграций drizzle |
| `npm run db:migrate` | Применение миграций |
| `npm run db:push` | Пуш схемы в БД |

---

## Vite конфиг

- Сервер на порту **3000**
- Алиасы: `@` → `src/`, `@contracts` → `contracts/`, `@db` / `db` → `db/`
- Dev: Hono+TRPC через `@hono/vite-dev-server`
- Сборка фронта в `dist/public/`, бэкенд через esbuild в `dist/boot.js`

---

## Деплой

- Сервер: доступ по `ssh serv`
- Папка на сервере: `/путь/IT-business-partner.ru/`
- Git remote: `https://github.com/suyazov/IT-business-partner.ru.git`
