<?php
/**
 * Plugin Name: SY3 Measurement
 * Description: Настройки Яндекс Метрики и подтверждения сайта в поисковых сервисах для sy3.ru.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SY3_Measurement {
    const OPTION = 'sy3_measurement_settings';
    const GROUP = 'sy3_measurement';
    const PAGE = 'sy3-measurement';

    private static $printed = false;

    public static function boot() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_menu', array(__CLASS__, 'register_page'));
        add_action('wp_head', array(__CLASS__, 'render_head'), 3);
    }

    private static function defaults() {
        return array('counter_id' => '', 'yandex_verification' => '', 'google_verification' => '');
    }

    public static function valid_value($field, $value) {
        if (!is_string($value)) {
            return false;
        }
        if ($value === '') {
            return true;
        }
        if ($field === 'counter_id') {
            // At most 15 decimal digits: safely representable as a JavaScript Number.
            return preg_match('/\A[1-9][0-9]{0,14}\z/D', $value) === 1;
        }
        if ($field === 'yandex_verification') {
            return preg_match('/\A[a-fA-F0-9]{16}\z/D', $value) === 1;
        }
        if ($field === 'google_verification') {
            return preg_match('/\A[A-Za-z0-9_-]{20,200}\z/D', $value) === 1;
        }
        return false;
    }

    public static function options() {
        $result = self::defaults();
        $saved = get_option(self::OPTION, array());
        if (!is_array($saved)) {
            return $result;
        }
        foreach ($result as $field => $empty) {
            if (isset($saved[$field]) && self::valid_value($field, $saved[$field])) {
                $result[$field] = $saved[$field];
            }
        }
        return $result;
    }

    public static function sanitize($input) {
        $result = self::options();
        if (!is_array($input)) {
            add_settings_error(self::GROUP, 'invalid_settings', 'Настройки не сохранены: ожидаются отдельные поля.');
            return $result;
        }
        $labels = array(
            'counter_id' => 'Номер счётчика Метрики',
            'yandex_verification' => 'Код Яндекс Вебмастера',
            'google_verification' => 'Код Google Search Console',
        );
        foreach ($labels as $field => $label) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $value = is_string($input[$field]) ? trim($input[$field]) : null;
            if ($value !== null && self::valid_value($field, $value)) {
                $result[$field] = $value;
            } else {
                add_settings_error(self::GROUP, 'invalid_' . $field,
                    $label . ': неверный формат. Предыдущее значение сохранено. Вставьте только значение, без HTML-кода.');
            }
        }
        return $result;
    }

    public static function register_settings() {
        register_setting(self::GROUP, self::OPTION, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize'),
            'default' => self::defaults(),
            'show_in_rest' => false,
        ));
        add_settings_section('sy3_measurement_main', 'Счётчик и подтверждение сайта',
            array(__CLASS__, 'render_intro'), self::PAGE);
        $fields = array(
            'counter_id' => array('Номер счётчика Яндекс Метрики',
                'Только положительный номер, до 15 цифр. Пустое поле отключает счётчик.', 15),
            'yandex_verification' => array('Яндекс Вебмастер',
                'Только content метатега yandex-verification: 16 шестнадцатеричных символов.', 16),
            'google_verification' => array('Google Search Console',
                'Только content метатега google-site-verification: 20–200 букв, цифр, дефисов или подчёркиваний.', 200),
        );
        foreach ($fields as $field => $details) {
            add_settings_field($field, $details[0], array(__CLASS__, 'render_field'), self::PAGE,
                'sy3_measurement_main', array('field' => $field, 'description' => $details[1],
                    'maxlength' => $details[2], 'label_for' => 'sy3-measurement-' . $field));
        }
    }

    public static function register_page() {
        add_options_page('Аналитика и поиск SY3', 'Аналитика и поиск SY3', 'manage_options',
            self::PAGE, array(__CLASS__, 'render_page'));
    }

    public static function render_intro() {
        echo '<p>Код выводится только на публичных HTTPS-страницах sy3.ru. Пустые поля ничего не подключают. '
            . 'Сохранение кодов не подтверждает права в кабинетах сервисов.</p>';
    }

    public static function render_field($args) {
        $options = self::options();
        $field = $args['field'];
        if (!array_key_exists($field, $options)) {
            return;
        }
        printf('<input type="text" class="regular-text" id="%s" name="%s[%s]" value="%s" maxlength="%d" autocomplete="off" spellcheck="false"%s>',
            esc_attr('sy3-measurement-' . $field), esc_attr(self::OPTION), esc_attr($field),
            esc_attr($options[$field]), (int) $args['maxlength'], $field === 'counter_id' ? ' inputmode="numeric"' : '');
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Недостаточно прав.', '', array('response' => 403));
        }
        echo '<div class="wrap"><h1>Аналитика и поиск SY3</h1>';
        settings_errors(self::GROUP);
        echo '<form action="options.php" method="post">';
        settings_fields(self::GROUP);
        do_settings_sections(self::PAGE);
        submit_button('Сохранить настройки');
        echo '</form></div>';
    }

    public static function production_request() {
        if (is_admin() || !is_ssl() || is_feed() || is_trackback() || is_preview()
            || is_customize_preview() || (defined('REST_REQUEST') && REST_REQUEST)
            || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)
            || (defined('DOING_AJAX') && DOING_AJAX) || (defined('DOING_CRON') && DOING_CRON)) {
            return false;
        }
        if (function_exists('wp_get_environment_type') && wp_get_environment_type() !== 'production') {
            return false;
        }
        $home = wp_parse_url(home_url('/'));
        if (!is_array($home) || ($home['scheme'] ?? '') !== 'https'
            || ($home['host'] ?? '') !== 'sy3.ru' || ($home['path'] ?? '/') !== '/'
            || isset($home['port']) || isset($home['user']) || isset($home['pass'])
            || isset($home['query']) || isset($home['fragment'])) {
            return false;
        }
        // Deliberately do not trust forwarded host/proto headers or allow preview aliases.
        return isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'sy3.ru';
    }

    public static function render_head() {
        if (self::$printed || !self::production_request()) {
            return;
        }
        self::$printed = true;
        $options = self::options();
        if ($options['yandex_verification'] !== '') {
            echo '<meta name="yandex-verification" content="' . esc_attr($options['yandex_verification']) . '">' . "\n";
        }
        if ($options['google_verification'] !== '') {
            echo '<meta name="google-site-verification" content="' . esc_attr($options['google_verification']) . '">' . "\n";
        }
        if ($options['counter_id'] === '') {
            return;
        }
        $counter = wp_json_encode($options['counter_id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($counter === false) {
            return;
        }
        ?>
<script id="sy3-yandex-metrica">
(function () {
    'use strict';
    if (window.location.protocol !== 'https:' || window.location.hostname !== 'sy3.ru' || window.location.port !== '') {
        return;
    }
    (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
    m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
    (window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym');
    window.ym(Number(<?php echo $counter; // Validated decimal string, JSON encoded. ?>), 'init', {
        accurateTrackBounce: true,
        trackLinks: true,
        webvisor: false,
        clickmap: false,
        trackHash: false,
        childIframe: false,
        ecommerce: false,
        disableYtm: true
    });
}());
</script>
        <?php
    }
}

SY3_Measurement::boot();
