<?php
/** Preview integration fixture, never customer-facing content. */
if(wp_get_environment_type()==='production')exit('Preview only.');
require_once __DIR__.'/../content/blocks.php';
function sy3_editorial_fixture_content(): string {
    return sy3_p(str_repeat('Тестовая запись проверяет очередь и не представляет редакционный материал. ',200))
        .sy3_b('table','<figure class="wp-block-table"><table class="has-fixed-layout"><tbody><tr><td>Вход</td><td>Проверка</td><td>Выход</td></tr></tbody></table></figure>')
        .sy3_g(sy3_p('Изолированная схема для проверки издателя.'),'process-map');
}
