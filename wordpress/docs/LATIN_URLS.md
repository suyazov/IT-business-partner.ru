# Транслит адресов

На основном сайте модуль sy3-latin-slugs.php подключён из content plugin 1.0.1. Русские, украинские и белорусские буквы преобразуются в латиницу при сохранении slug и новых имён файлов. Названия, заголовки и тексты остаются на языке автора. Личный режим исключён. Существующие статьи, страницы, кейсы и файлы уже имеют латинские адреса и не переименовываются.

`tools/transliterate-term-slugs.php` переводит 34 кириллических slug категорий и направлений кейсов, включая рубрики черновиков; ID, имена, описания, родительские связи, term_taxonomy и назначения сохраняются. Изменение ограничено terms.slug и функциональной картой старых URL sy3_legacy_term_urls (autoload=no). Коллизия с другим термином, дублирование ID или неизвестная карта останавливают миграцию. Production требует точного site identity/HTTPS/authority и SHA256 просмотренного dry-run плана. CAS и проверка ожидаемого набора terms выполняются в транзакции; повтор ничего не пишет.

Старые адреса получают 301 на текущий get_term_link по ID. Сохраняются query arguments, pagination и feed suffix; произвольные дочерние пути остаются 404. Только GET/HEAD, same-site safe redirect, без redirect loop. Карта — действующие данные маршрутизации, её сохраняют при очистке временных файлов. Переименование родительского термина позднее учитывается в целевом get_term_link.

Пример: `/case-topics/сайты-и-wordpress/` → `/case-topics/sayty-i-wordpress/`. Остальные направления: `crm-i-zayavki`, `ii`, `integratsii-i-instrumenty`. Рубрики журнала аналогично используют `integratsii`, `protsessy`, `ii`.

Проверено: preview34 старых URL301→200, точные ASCII Location; GET/HEAD, page2+utm, feed, неверный suffix404; create migration+idempotence; будущие slug, percent-encoded ввод, filename и personal exclusion. До миграции production нет кириллических post slugs или attached file names. Production dry-run план: SHA256 `64f22e30b9bb88446aced7068879916edc9379d0879d5a8c4afd9560920c6f84`. Подробности: private/latin-{production-plan,preview-plan,preview-migration,preview-checks}.json; exact-head review и live delivery — в PR. Backups не создаются.

```sh
HTTPS=on SERVER_PORT=443 SY3_ALLOW_PRODUCTION_IMPORT=1 SY3_IMPORT_DRY_RUN=1 wp --path=/var/www/sy3-wordpress --allow-root eval-file wordpress/tools/transliterate-term-slugs.php
```
