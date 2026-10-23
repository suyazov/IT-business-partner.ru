# Реестр подготовленных версий

`CONTENT_INVENTORY.csv` сохраняет исходный аудит 360 объектов/3070 поверхностей и overlay десяти пилотных тел. Полные версии следующих пакетов перечислены в **BATCH_INDEX.json**: ID, тип, slug, baseline/candidate SHA256 и точная ссылка на HTML либо JSON Pointer. Решения и сравнения до/после — в REPORT.md соответствующего пакета. Эти документы дополняют друг друга.

Индекс строится из **одного точного опубликованного Git commit**, указанного внутри JSON. Он не читает незавершённые файлы авторов, сеть или CMS. Проверяет identity и исходный hash по приватному read-only baseline, новый hash по Git blob, допустимый путь/тип файла, уникальность ID и существующую декларацию длины статьи. Содержимое baseline, alt, произвольные metadata и секреты в индекс не копируются.

Количество знаков в индексе — декларация manifest. Фактические вызовы действующего `sy3_editorial_quality()`, browser/native validation и независимое review имеют отдельные receipts. Индекс их не заменяет, не создаёт publisher approval и не разрешает запись в WordPress. `body_not_prepared` означает отсутствие отдельной новой body-версии в данном commit; это не вердикт по всем title/meta/UI поверхностям объекта. Пустое тело страницы журнала и privacy-тексты не переписываются ради покрытия.

Воспроизведение после fetch принятого source commit:

```sh
python3 -B wordpress/tools/editorial_batch_index.py "$SY3_EDITORIAL_BASELINE" --commit "$SY3_ACCEPTED_SOURCE_SHA"
```

Обе переменные задаются оператором: приватный baseline и полный 40-символьный SHA. Инструмент записывает только фиксированный `wordpress/docs/editorial/BATCH_INDEX.json`; production и публикационные очереди не затрагивает. После новых принятых пакетов индекс обновляется тем же способом.

Проверка инструмента: валидный срез, девять отрицательных identity/hash/path/policy случаев и отсутствие приватных metadata в выходе. Evidence: private/editorial-refactor-20261023/batch-index-regression.json. READER_TEST_NOT_RUN; техническое прохождение не доказывает коммерческий эффект текста.
