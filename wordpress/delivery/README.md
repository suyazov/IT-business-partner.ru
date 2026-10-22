# Доставка заявок SY3 Support

Только стандартная библиотека Python 3.10+. Скрипт устанавливается root вне webroot и исполняется root. Он не загружает WordPress, PHP, файлы приложения или код пользователя. CLI не принимает произвольные пути, API endpoints, токены или получателей.

## Интерфейс установки

Root install helper готовит `/root/.config/client-access/sy3.ru/lead-delivery.json`, root:root `0600`, без симлинков в пути. Содержимое — строго JSON-объект:

```json
{"version":1,"telegram_owner_id":100000001,"max_owner_id":200000001}
```

Числа выше — вымышленные примеры, не реальные получатели. Перед созданием helper должен сверить единственного Telegram-владельца с `/etc/sy3-support/projects.yml`, а MAX-владельца с `/etc/sy3-support/max-projects.yml` и `MAX_OWNER_ID` действующего MAX env. Dispatcher не вводит второй YAML-парсер и не получает владельца из заявки. Если registry меняется, root отдельно обновляет проверенную конфигурацию. MAX ID дополнительно сверяется при каждом запуске с `MAX_OWNER_ID` в env; для Telegram актуальность registry обеспечивает install helper.

Dispatcher читает только необходимые canonical файлы доступов, все root `0600`:

- `/root/.config/client-access/sy3-support/telegram-bot.env`: `TELEGRAM_BOT_TOKEN`;
- `/root/.config/client-access/sy3-support/max.env`: `MAX_BOT_TOKEN`, `MAX_OWNER_ID`;
- `/root/.config/client-access/sy3-support/max-ca.pem`: дополнительный доверенный CA MAX.

Env разбирается как данные: обычные присваивания, необязательный `export`, кавычки и комментарии. Shell expansion/source/eval отсутствуют. Не выводить env или реальные ID в отчёты. Секреты не копируются в этот каталог или Git. Settings не имеет repr с содержимым.

## Storage и права

| Путь относительно `/var/lib/sy3-wordpress/leads` | Владелец | Режим | Кто пишет |
| --- | --- | --- | --- |
| Сам каталог | root:php-sy3 | 0750 | Root управляет дочерними каталогами |
| `queue/` | root:php-sy3 | 0770 | PHP атомарно создаёт неизменяемые задания |
| `queue/<id>.json` | php-sy3 | 0600 | PHP один раз, затем без редактирования |
| `state/` | root:php-sy3 | 0750 | Только root |
| `state/<id>.json` | root:php-sy3 | 0640 | Только dispatcher, PHP может читать статус |
| `state/_worker.lock` | root:php-sy3 | 0640 | Flock единственного процесса |
| `state/_control.json` | root:php-sy3 | 0640 | Межзапусковые паузы API |
| `locks/`, `limits/` плагина | root:php-sy3 | 0770 | PHP; dispatcher их не читает |

UID/GID WordPress берутся из системной записи `php-sy3`. Владелец каталогов ожидается root, а не php-sy3. Dispatcher проверяет свои base/queue/state, не меняет права и не создаёт отсутствующие каталоги. Права не исправляются «на лету». Административный UI плагина может перечислять private queue и читать state, но не должен передавать файлы без проверки полномочий.

Обход пути выполняется через `openat`/directory FD с `O_NOFOLLOW`; обычные файлы проверяются через открытый FD: regular, один hardlink, ожидаемый UID/режим, предел размера, стабильность stat до/после чтения. FIFO открывается неблокирующим способом и отвергается. Имена заданий — только 64 lower-hex + `.json`. Предел исходного задания — 16 KiB. JSON с дубликатами ключей, NaN, чрезмерной вложенностью и неправильной схемой отклоняется.

## Контракт задания

Обязательны `version:1`, `id`, `created_at` (целый Unix timestamp), `contact` (непустая строка до 200 символов), `page` (HTTPS, ровно host `sy3.ru`, путь с `/`, до 2048 символов), `consent:true`.

Необязательны `name` до 120, `message` до 2000 и `utm` — JSON object только с `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, каждое значение до 120. Пустые UTM — `{}`, не `[]`. Дополнительные ключи и управляющие символы отвергаются; newline/tab разрешены только в сообщении. ID внутри JSON должен совпасть с именем файла. Ссылка из задания никогда не запрашивается по сети.

Формат уведомления plain text: стабильный `SY3-<первые12символовIDвверхнемрегистре>`, имя, контакт, сообщение, страница и присутствующие UTM. Ни `parse_mode`, ни MAX `format` не отправляются. Превью ссылок отключено. Полный контакт и имя сохраняются; очень длинные page/UTM/message сокращаются с `…`, чтобы уведомление не превышало 3900 даже в UTF-16 units. Полная исходная заявка остаётся в private queue, не в state и не в журнале.

## Подтверждение, повторы и сбои

Для каждого канала собственные `retry`, `sending`, `sent`, `failed`, `uncertain`, число резервирований, время следующей попытки и короткая фиксированная причина. `sending` записывается атомарно с fsync до HTTP. Подтверждённый `sent` сохраняет только message ID, не тело ответа. `sent` не повторяется из-за отказа второго канала.

Telegram подтверждён только при HTTP 200, `ok:true`, положительном integer `result.message_id`. MAX — HTTP 200 и непустом допустимом `message.body.mid`. Произвольное наличие `message` не считается достаточным. HTTP 429 ставит повтор: Telegram `parameters.retry_after`, иначе числовой `Retry-After`, иначе 60 секунд. Задержка ограничена 1–3600 секунд и применяется также к другим заданиям того же канала.

Явные 400/401/403/404/405/409/410/413/415/422 становятся `failed`; их не повторяют автоматически. Ошибка проверки TLS — `failed`, без ослабления проверки сертификата. Тайм-аут, неизвестная сеть, редирект, HTTP 5xx, повреждённый или неожиданный ответ — `uncertain`. После аварийного завершения старое `sending` переходит в `uncertain`, без слепого повтора. Это намеренно допускает ручную сверку даже тогда, когда запрос в действительности не ушёл. Exactly-once у внешнего API не обещается.

Перед отправкой второго канала повторно проверяется digest задания. Изменение байтов того же ID блокирует дальнейшую доставку и сохраняет прежние receipts. Некорректный state, включая JSON null, не считается отсутствующим и не сбрасывает подтверждения. Нельзя удалять state ради повтора: это разрушает защиту от повторной доставки. Для `uncertain` владелец сверяет уведомление по стабильному номеру; административная процедура принудительного повтора отдельно, в этот CLI не включена.

MAX использует `https://platform-api2.max.ru/messages?user_id=<owner>` с Authorization header. Telegram — фиксированный HTTPS Bot API `sendMessage`. Оба клиента используют проверку hostname/сертификата; MAX добавляет canonical CA к системному trust store. Redirects запрещены, environment proxies отключены. Секретный Telegram URL и необработанные API errors/response bodies не попадают в stdout/journal.

Flock не допускает двух workers. Между MAX-запросами этого dispatcher — минимум 0.55 секунды после предыдущего завершения, включая разные jobs и запуски. При hard crash остаётся более длинный защитный интервал. Если существующий Support отдельно отправляет тому же MAX-владельцу, общий лимит сервиса может всё равно вернуть 429; dispatcher соблюдает полученный cooldown. Чужой Support runtime не меняется.

## CLI и проверки

`python3 dispatcher.py --check` проверяет только локальную конфигурацию, права и TLS contexts: без HTTP, без создания state. Это не проверка действительности токена, доступности API или возможности бота писать владельцу.

`python3 dispatcher.py --once` обрабатывает максимум 20 готовых к попытке jobs с общим бюджетом 75 секунд. Каждая сетевая попытка дополнительно ограничена общим alarm-deadline до 12 секунд, включая чтение ответа. Бюджет проверяется между действиями; зависшее локальное файловое хранилище может задержать системный I/O и требует внешнего systemd timeout. Ответ CLI — только агрегированные счётчики новых действий текущего запуска, не полный накопленный статус очереди.

Offline verification:

```text
python3 -m unittest -v test_dispatcher
```

Тесты используют временные каталоги, вымышленные токены/получателей и fake transport. Проверяются partial success + 429, независимость каналов, повторный запуск, crash → uncertain, реальный flock двух процессов, изменение задания, сроки и лимит 20 jobs, MAX cadence/cooldown, проверенный TLS, отсутствие proxy/redirect, malformed/schema/size/symlink/hardlink/FIFO, защита state, локальный check без транспорта и отсутствие секретов в ошибках. Команды production `--check`/`--once` автор этого пакета не запускал.

## Проверенные источники интерфейсов

- [SY3 Support current accepted MAX client](https://github.com/suyazov/sy3-support/blob/0151bfe409d51d2af0f53e0e49b2345cab77aa37/src/sy3_support/max_support.py).
- [SY3 Support MAX deployment context](https://github.com/suyazov/sy3-support/blob/0151bfe409d51d2af0f53e0e49b2345cab77aa37/docs/MAX_SUPPORT.md).
- [MAX POST messages](https://dev.max.ru/docs-api/methods/POST/messages), [Message object](https://dev.max.ru/docs-api/objects/Message).
- [Telegram sendMessage](https://core.telegram.org/bots/api#sendmessage), [ResponseParameters](https://core.telegram.org/bots/api#responseparameters).
