# IT-business-partner.ru — Project Context

## WordPress: sy3.ru и личный сайт — 2026-10-22

Owner requested redesign of both sites today with WordPress as CMS and a focus on CRM, integrations and AI automation. Implementation is in `wordpress/`; the prior React/Hono runtime is preserved. `sy3.ru` is the commercial/expert-content destination; `it-business-partner.ru` presents the author and collaboration format.

Result: one custom WordPress theme, a content plugin, 3 service pages, 8 new articles, 25 case studies selected from the owner's ready AFFiNE portfolio, 23 native covers, author/contact/data pages. Homepage features six cases; the main catalogue has four topic categories. Personal homepage links four selected works and its portfolio links all 25 main-site cases. Original 8 legacy records and 3 blog posts are preserved in source; seeded local archives are drafts pending editorial review. Matching old portfolio slugs redirect to main-site cases. See `wordpress/docs/PORTFOLIO_SOURCE.md` for source identities, Share selection, owner-confirmed Matklass permission, and two unavailable/incorrect covers. Quantitative claims retain their source limits; no new client-system audit or lead/traffic growth is claimed.

Current state: isolated branch/worktree and two local WordPress previews; production directories, Nginx, DNS and production databases have not changed. Portfolio HTTP smoke passed for 61 HTML pages, 60 local resources, 8 exact redirects, sitemap children, llms.txt and matching 25-case feeds. Browser checks covered the catalogue at 1440px and a case at 390px: images loaded, no horizontal overflow or JavaScript exceptions. Native media are imported once by SHA-256; editor-selected covers are preserved. Earlier checks covered both mobile menus, native WebGL motion/pause/reduced-motion/fallback and 112 valid homepage blocks. The live personal checkout has staged/dirty/untracked work and must be preserved; it is not recoverable solely from published Git. Source preparation is not evidence of production rollout. Independent review, exact rollout authority, bounded backup/rollback, old blog-address decision and live acceptance remain separate gates.

Design direction: owner deferred publication and selected i.moscow/techlab, uplab.ru, spectre.website and tactic.team as references. The revised candidate uses a dark graphite/cyan hero, large Manrope typography, a native WebGL sculpture with pause/reduced-motion/static SVG fallback, numbered service rows, larger case illustrations and a light personal-site variation. Fonts are self-hosted under OFL. Reference assets and proprietary fonts were not copied. See `wordpress/docs/DESIGN.md`.

Next action: show the revised previews and obtain the owner's design feedback. Production rollout and a separate independent Codex reviewer remain unauthorised after the owner's explicit choice to change design/content first. The exact rollout plan remains in `wordpress/docs/ROLLOUT.md`. The implementation executor is Codex; no Kimi or Bridge execution was launched for this work.

Cleanup: no production rollback copies were made. Working source and local CMS previews are retained for review, with a cleanup/review date of 2026-10-23 18:00 Moscow time. Private AFFiNE source caches, original cover downloads, contact sheets and QA scripts under `/root/preview/sy3-wordpress-20261022/private/` are temporary review material with the same deadline. Shared storage mutation lock is held by another task; it must not be removed. Reproducible preview data may be removed only under the storage lock after review and preservation of editor changes. Protected access files, unrelated dirty work, receipts and Git history are not disposable backups.

Operational note: an erroneous `Network.clearBrowserCookies` command during the first editor check cleared cookies in the shared server Chrome profile. Reauthentication may be needed in that profile. Further browser checks use an owned isolated browser context and dispose only that context.

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
