# Безопасная карта доступов

| Сервис | URL | Владелец | Доступ | Защищённое хранилище | Статус |
| --- | --- | --- | --- | --- | --- |
| WordPress sy3.ru preview | `http://127.0.0.1:8186` | Артём / root operator | локальная панель редактора | `/root/.config/client-access/sy3.ru/wordpress-preview.env` | создан; первый пароль заменён после попадания в вывод WP-CLI |
| WordPress личного сайта preview | `http://127.0.0.1:8187` | Артём / root operator | локальная панель редактора | `/root/.config/client-access/it-business-partner.ru/wordpress-preview.env` | создан, вывод секретов подавлен |

Каталоги хранилища `0700`, файлы `0600`, владелец `root:root`. Секреты в source, Issue/PR, промпты и тексты сайтов не копируются. Локальные preview-учётные данные нельзя переносить в production. Боевые доступы созданы отдельно при разрешённой публикации; подробности ниже.

Локальные SQLite-базы размещены в `private/` вне public root соответствующих стендов. Стенды привязаны только к loopback, не являются опубликованными сайтами. Срок проверки необходимости удержания: 2026-10-23 18:00 МСК. При очистке сначала сохранить полезные редакторские изменения; секретные файлы не удалять как временные backup.

## Production — 22 октября 2026

| Сервис | Панель | Логин | Владелец | Защищённое хранилище | Статус |
| --- | --- | --- | --- | --- | --- |
| sy3.ru WordPress / MariaDB | `https://sy3.ru/wp-admin/` | `artem_admin` | Артём / root operator | `/root/.config/client-access/sy3.ru/wordpress.env` | установлен; вход, редактор и REST-сохранение проверены |
| Личный WordPress / MariaDB | `https://it-business-partner.ru/wp-admin/` | `artem_admin` | Артём / root operator | `/root/.config/client-access/it-business-partner.ru/wordpress.env` | установлен; вход проверен |

Для каждого сайта сгенерированы отдельные новые пароль администратора, пароль базы и salts. Preview-пароли не переносились. Файлы root:root 0600, каталоги 0700; секреты в Git/чат не выводились. wp-config.php — root:php-sy3 0640. Резервные копии текущего переноса удалены по прямой команде владельца; исходные сайты сохранены.

## Аналитика и поиск — 24 октября 2026

| Сервис | Панель | Владелец / логин | Защищённое хранилище | Статус |
| --- | --- | --- | --- | --- |
| Яндекс Метрика / Вебмастер | `https://metrika.yandex.ru/`, `https://webmaster.yandex.ru/` | Артём; сохранённый логин Incan | `/root/.config/client-access/sy3.ru/yandex.env` | Существующий счётчик55563139 включён; tag.js/watch HTTP200. Права https://sy3.ru подтверждены в кабинете24.10. Sitemap обнаружен из robots.txt; ручное добавление временно недоступно из-за read-only инструмента Яндекса. |
| Google Search Console | `https://search.google.com/search-console` | Артём; сохранённый логин artem.suyazov | `/root/.config/client-access/sy3.ru/google.env` | Google отклонил автоматический вход. Открыта отдельная серверная вкладка для ручной авторизации; verification token и приёмка Search Console ещё не получены. |
| eLama | `https://account.elama.global` | Артём | `/root/.config/client-access/sy3.ru/elama.env` | Доступ сохранён ранее; вход в этой задаче не проверялся. |
| Настройки кода WordPress | `https://sy3.ru/wp-admin/options-general.php?page=sy3-measurement` | administrator WordPress | WordPress option `sy3_measurement_settings` | Сохранены counter ID и Yandex verification content. Google-поле пустое. Пароли сервисов этот плагин не принимает. |
| Доставка формы | закрытая server-side конфигурация | root operator | `/root/.config/client-access/sy3.ru/lead-delivery.json` | Fixed-owner delivery в Telegram/MAX проверена. Конфигурация ссылается на существующие защищённые bot credentials, без копирования токенов в Git. |

Файлы доступов остаются root-only вне Git; пароли не дублируются в этой карте. Сохранение verification content и подтверждение в кабинете — разные шаги. Текущее состояние — `context.md`, реализация — `MEASUREMENT.md`.
