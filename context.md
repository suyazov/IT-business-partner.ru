# IT-business-partner.ru — Project Context

## WordPress: sy3.ru и личный сайт — опубликовано 2026-10-22

Result: sy3.ru and it-business-partner.ru now use separate WordPress 7.1.3 installations with MariaDB and Russian dashboards. Main site: 3 services, 10 journal articles, 25 AFFiNE Share cases and 23 native covers. Personal site: author/approach, four selected works on home, links to all 25 cases. Existing portfolio and blog routes have explicit redirects to matching current material. Source PR #1 merged as `532ea91c5b03d527eb98d6c1012d22207a62a82d`; independent reviewer passed exact head `bde81d80807c99446d6a11a4c70cef584f1efb47`. No paid API, DNS, mail or Telegram changes.

Production: `/var/www/sy3-wordpress`, `/var/www/itbp-wordpress`; separate MariaDB schemas/users `sy3_wp_20261022`, `itbp_wp_20261022`; existing PHP 8.4 FPM socket `/var/run/php-fpm-sy3-ru.sock`. Only two domain vhosts changed; TLS and existing includes retained. `/api/` on the personal domain still reaches its original Node service. Legacy images/videos are served from the new personal root. Original source directories and the Node service remain intact, including dirty/staged/untracked work.

Owner authority: explicitly approved one independent reviewer and this exact production scope after successful review/checks. Subsequently explicitly cancelled backups and ordered deletion. The current-task backup directory `/var/lib/bridge-sy9/backups/wordpress-two-sites-20261022` was deleted under the storage lock (88,149,698 bytes). No further backups were created; other projects, existing backup jobs, original sites, Git and receipts were preserved. This is a current-task override, not a global backup-policy change. A prior lock-guard error during backup creation is recorded in the private operational receipt; later shared-resource mutations acquired locks inside the mutating process.

Checked: clean official WordPress ZIP passed core checksums (the preview tar extraction had truncated long paths and was not used for production). Production HTTPS checks through loopback with actual domain/SNI/certificate verification: 64 HTML pages, 60 resources, 21 exact redirects, sitemap children, llms.txt and two 25-case feeds. Public-IP hairpin is unavailable from this host; the external web checker did not provide a reliable fresh response, so independent outside-network availability is not claimed. Main and personal browser layouts rendered with WebGL, loaded images and no overflow. Main editor: 112 valid blocks; authenticated REST draft creation and permanent deletion verified. Both admin logins and ru_RU verified. User collection requests are forced through WordPress context=edit and its list_users capability check; anonymous requests remain 401, XML-RPC remains blocked. Final domain configurations are in `wordpress/deploy/nginx/`.

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
