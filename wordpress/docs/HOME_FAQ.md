# FAQ главной

Семь вопросов отражают решения покупателя перед обращением: старт без ТЗ, стоимость/сроки, развитие существующих систем, личная ответственность и исполнители, критерии приёмки, отдельное ТЗ для своей команды, сопровождение после запуска. Ответы от первого лица опираются на принятый процесс work-process.php и подтверждённые услуги. Нет обещаний бесплатного аудита, фиксированных сроков, SLA или измеримой экономии.

`content/home-faq.php` — единая фабрика нативного раздела, её использует home.php при будущих импортах. Текущая публикация выполняется отдельным `tools/update-home-faq.php`: точный site identity/HTTPS/authority, home ID4/page_on_front/owned seed, before/after body SHA, исходный и новый FAQ SHA, единственная точная замена раздела. Два prepared CAS UPDATE изменяют только body и _sy3_seed_hash в транзакции; остальные поля и metadata проверяются read-back. Ручные правки сохраняются через отказ от записи, повтор ничего не пишет. About, статьи, личный сайт и другие разделы главной не переимпортируются.

Исходный production body воспроизводится factory из принятого Git8d07f6b: SHA `f15b168e4d014f2fd5b79887e870ad3e9e3a4ff1ffdcbd290efa54768060bccd`. Новых backups нет. FAQ section SHA после правки `1dc28e93d73ce7726cedf9089812d90f574cb2c6787ce17121b9948988c0cf45`; production body SHA `5d4535fc03f8dec3eba29a02f27d4f2cbbd005abda8583a4af1413f36942cab8`.

Проверено: preview apply+idempotence, production dry-run; все семь раскрытых ответов при320/390/1440 без overflow/обрезки, native details открывается Enter, JS ошибок нет. Независимая редакторская оценка — PASS; независимое exact-head review и delivery — в PR. Evidence private/home-faq-*.json.
