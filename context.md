# IT-business-partner.ru — Project Context

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
