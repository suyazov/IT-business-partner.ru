<?php defined('ABSPATH') || exit; do_action('sy3_before_footer'); ?>
<?php if (!sy3_personal()): ?>
<footer class="site-footer footer-sy3">
    <div class="wrap footer-main">
        <div class="footer-intro"><a class="footer-brand" href="<?php echo esc_url(home_url('/')); ?>">сутри</a><p>Сайты, CRM и автоматизация<br>под задачи бизнеса.</p></div>
        <div class="footer-contact"><h2>Начнём с вашей задачи.</h2><p>Расскажите, что хотите запустить или улучшить.</p><div class="footer-messengers"><a class="footer-messenger" href="https://t.me/sy3_support_bot?start=site_sy3">Написать в Telegram</a><a class="footer-messenger footer-messenger-outline" href="https://max.ru/id263514840335_1_bot?start=site_sy3">Написать в MAX</a></div><a class="footer-email" href="mailto:help@sy3.ru">help@sy3.ru</a></div>
    </div>
    <nav class="wrap footer-services" aria-label="Основные услуги"><a href="<?php echo esc_url(home_url('/services/web-development/')); ?>">Создание сайтов</a><a href="<?php echo esc_url(home_url('/services/website-support/')); ?>">Поддержка сайтов</a><a href="<?php echo esc_url(home_url('/services/business-analysis/')); ?>">Бизнес-анализ</a><a href="<?php echo esc_url(home_url('/services/bitrix24/')); ?>">Внедрение Битрикс24</a></nav>
    <div class="wrap footer-legal"><div class="footer-identity"><p>ИП Суязов Артем Анатольевич</p><p>ИНН 263514840335 · ОГРНИП 320265100039682</p></div>
        <details class="footer-requisites"><summary>Реквизиты для договора и оплаты</summary><dl>
            <dt>Наименование</dt><dd>ИП Суязов Артем Анатольевич</dd>
            <dt>ИНН</dt><dd>263514840335</dd>
            <dt>ОГРНИП</dt><dd>320265100039682</dd>
            <dt>Расчётный счёт</dt><dd>40802810700000190480</dd>
            <dt>Корреспондентский счёт</dt><dd>30101810645374525068</dd>
            <dt>Банк</dt><dd>ООО «ОЗОН Банк»</dd>
            <dt>БИК</dt><dd>044525068</dd>
            <dt>ИНН банка</dt><dd>9703077050</dd>
            <dt>Юридический адрес банка</dt><dd>РФ, 123112, г. Москва, вн.тер.г. Муниципальный Округ Пресненский, Пресненская наб., дом 10, этаж 19</dd>
        </dl></details>
    </div>
    <div class="wrap footer-bottom"><span>© <?php echo esc_html(wp_date('Y')); ?> Сутри</span><?php wp_nav_menu(['theme_location' => 'footer', 'container' => false, 'fallback_cb' => false, 'depth' => 1]); ?></div>
</footer>
<?php else: ?>
<footer class="site-footer"><div class="wrap footer-top"><div><a class="footer-brand" href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sy3_label()); ?></a><p>Технологии, которые помогают<br>вести бизнес.</p></div><div><p class="eyebrow">Обсудим вашу задачу</p><a class="footer-email" href="mailto:artem.suyazov@gmail.com">artem.suyazov@gmail.com</a><p><a href="tel:+79064790552">+7 (906) 479-05-52</a> · <a href="https://t.me/moi_imperator">Telegram</a></p></div></div>
<?php if (!sy3_personal()): ?><nav class="wrap service-shortcuts" aria-label="Основные услуги"><a class="text-link" href="<?php echo esc_url(home_url('/services/web-development/')); ?>">Создание сайтов</a><a class="text-link" href="<?php echo esc_url(home_url('/services/website-support/')); ?>">Поддержка сайтов</a><a class="text-link" href="<?php echo esc_url(home_url('/services/business-analysis/')); ?>">Бизнес-анализ</a><a class="text-link" href="<?php echo esc_url(home_url('/services/bitrix24/')); ?>">Внедрение Битрикс24</a></nav><?php endif; ?>
<div class="wrap footer-wordmark" aria-hidden="true"><?php echo sy3_personal() ? 'на связи.' : 'сутри'; ?></div>
<div class="wrap footer-bottom"><span>© <?php echo esc_html(wp_date('Y')); ?> ИП Суязов Артем Анатольевич</span><?php wp_nav_menu(['theme_location' => 'footer', 'container' => false, 'fallback_cb' => false, 'depth' => 1]); ?><a href="<?php echo esc_url(sy3_personal() ? sy3_main_url() : 'https://it-business-partner.ru/'); ?>"><?php echo sy3_personal() ? 'Сайт услуг Сутри' : 'Личный сайт Артёма'; ?></a></div></footer>
<?php endif; ?>
<?php wp_footer(); ?></body></html>
