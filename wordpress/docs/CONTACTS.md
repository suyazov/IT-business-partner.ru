# Контакты sy3.ru

Основной сайт принимает обращения через `https://t.me/sy3_support_bot?start=site_sy3` и `https://max.ru/id263514840335_1_bot?start=site_sy3`. Публичные username сверены read-only `getMe`. Метка `site_sy3` обозначает источник, не даёт посетителю прав на служебные команды.

Public inbox из `suyazov/sy3-support` head `2f929477436476ef20869c45e122d08df4bfc35b` включён после отдельного разрешения владельца в обоих bot services. Readiness PASS: свежий Telegram poll, MAX health и политика приёма проверены. По одному синтетическому уведомлению владельцу в Telegram и MAX получили подтверждение доставки. Это проверяет готовность и канал уведомления; полный roundtrip от нового внешнего посетителя до владельца пока не выполнен. Обращение посетителя не даёт прав запускать Bridge, инструменты, AI или привязку групп.

Footer 1.4.3 опубликован на sy3.ru из reviewed head `c4d8637c5395c2bdbbdbbcd2c1ced997af11d5a5` / merge `2fdd91662519951c1aa104021340b909e1436f0a`. Он не выводит номер телефона, гигантский wordmark или ссылку на личный сайт. В нём есть обе кнопки, email, услуги, ИНН/ОГРНИП и нативный раскрывающийся блок полных реквизитов. Все девять полей переданы владельцем для публикации; сверка по банковским/государственным реестрам не выполнялась.

Восемь проверок развёрнутой версии на `/` и `/contacts/` при ширинах 320/390/768/1440 px подтвердили отсутствие телефона, ссылки на личный сайт и wordmark, раскрытие девяти реквизитов клавиатурой, отсутствие overflow, JS-ошибок и неуспешных HTTP-загрузок. Desktop footer — около 466 px. Проверка production HTTPS через локальный loopback не доказывает внешнюю доступность сайта.

`wordpress/tools/update-bot-contacts.php` применён в production: изменены только body и seed hash owned страниц 4 (`home`, фактическая `page_on_front`) и 6 (`contacts`). Exact before/after SHA256, ID/slug/status/seed и InnoDB защищают ручные правки; prepared CAS-запросы выполняются в общей транзакции с read-back и проверкой прочих полей/metadata. Даты, заголовки, excerpt, статусы, revisions, остальные post/meta, редакционная пауза и часы публикации сохранились; личный сайт не изменён. Повторный запуск не пишет. Root/home/environment и production flag обязательны; private preview SQLite adapter также прошёл применение и повтор.

```sh
HTTPS=on SERVER_PORT=443 SY3_IMPORT_DRY_RUN=1 SY3_ALLOW_PRODUCTION_IMPORT=1 wp --path=/var/www/sy3-wordpress --allow-root eval-file wordpress/tools/update-bot-contacts.php
```

Rollout evidence на сервере: `private/footer-messengers-rollout.json`, `private/footer-live-checks.json`, `private/public-inbox-readiness.json`, `private/public-inbox-owner-delivery.json`. Production обновлён после независимого review и отдельного разрешения. Новых backups нет: прежний код опубликован в Git, восстановление обоих старых seed bodies из Git проверено с `HTTPS=on SERVER_PORT=443`. Собственные PHP 8186 и Chrome 9339 остановлены. Очистка воспроизводимых QA-файлов отложена: storage lock занят чужим PID 2194724, срок проверки — 23 октября 2026, 18:00 МСК, точный список в `private/final-cleanup-retention.json`. Receipts, Git, доступы и полезные изменения сохраняются.

Личный сайт не публиковался в этом scope; его footer и контакты сохранены. Подготовленные web-form sender, `sy3-leads.timer` и editorial activation не включены и требуют отдельных разрешений. Устаревший private wrapper `deploy-lead-forms.py` безусловно завершается с SUPERSEDED, handoff имеет статус `superseded_blocked`: для будущего включения нужен новый reviewed form head с footer merge `2fdd916…`, чтобы не восстановить старые контакты. Новая публикация ботов и подвала не является разрешением запускать этот sender или снимать редакционную паузу.
