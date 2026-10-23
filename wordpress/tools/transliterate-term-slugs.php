<?php
/** CAS migration of taxonomy slugs, with a retained legacy URL map. */
if (!defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') exit(1);
$production = wp_get_environment_type() === 'production' && realpath(ABSPATH) === '/var/www/sy3-wordpress' && home_url('/') === 'https://sy3.ru/';
$preview = wp_get_environment_type() !== 'production' && realpath(ABSPATH) === '/root/preview/sy3-wordpress-20261022/public' && home_url('/') === 'http://127.0.0.1:8186/';
if (get_option('sy3_site_mode') !== 'expertise' || (!$production && !$preview)) WP_CLI::error('Unexpected site identity.');
if ($production && (getenv('SY3_ALLOW_PRODUCTION_IMPORT') !== '1' || !is_ssl())) WP_CLI::error('Production authority and HTTPS required.');
$module = dirname(__DIR__) . '/plugin/sy3-latin-slugs.php';
if (function_exists('sy3_latin_text')) {
    $loaded = (new ReflectionFunction('sy3_latin_text'))->getFileName();
    if (!$loaded || !is_file($loaded) || !hash_equals(hash_file('sha256', $module), hash_file('sha256', $loaded))) WP_CLI::error('Loaded URL module differs from reviewed source.');
} else require_once $module;
global $wpdb;
$transaction = false;
try {
    $option = 'sy3_legacy_term_urls';
    $prior = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $option));
    $map = $prior === null ? [] : maybe_unserialize($prior);
    if (!is_array($map)) throw new RuntimeException('Unknown redirect map preserved.');
    $paths = [];
    foreach ($map as $entry) {
        if (!sy3_valid_legacy_term_route($entry) || isset($paths[rtrim($entry['path'], '/')])) throw new RuntimeException('Invalid or duplicate legacy route preserved.');
        $target = get_term($entry['term_id'], $entry['taxonomy']);
        if (!$target || is_wp_error($target)) throw new RuntimeException('Legacy route target missing.');
        $paths[rtrim($entry['path'], '/')] = true;
    }
    $terms = get_terms(['taxonomy' => ['category','post_tag','sy3_case_topic'], 'hide_empty' => false]);
    if (is_wp_error($terms)) throw new RuntimeException('Cannot read taxonomy.');
    $plan = []; $claimed = [];
    foreach ($terms as $term) {
        $decoded = rawurldecode($term->slug);
        if (!preg_match('/[А-Яа-яЁёІіЇїЄєҐґЎў]/u', $decoded)) continue;
        $new = sanitize_title(sy3_latin_text($decoded));
        if ($new === '' || !preg_match('/^[a-z0-9_-]+$/D', $new)) throw new RuntimeException('Unexpected transliteration.');
        $key = $term->taxonomy . ':' . $new;
        $collision = get_term_by('slug', $new, $term->taxonomy);
        if (($collision && $collision->term_id !== $term->term_id) || isset($claimed[$key])) throw new RuntimeException('Slug collision; preserve both terms.');
        $claimed[$key] = true;
        $old_url = get_term_link($term);
        if (is_wp_error($old_url)) throw new RuntimeException('Cannot derive legacy URL.');
        $entry = ['term_id' => (int) $term->term_id, 'taxonomy' => $term->taxonomy, 'old' => $term->slug, 'new' => $new, 'path' => rawurldecode((string) wp_parse_url($old_url, PHP_URL_PATH))];
        if (!sy3_valid_legacy_term_route($entry) || isset($paths[rtrim($entry['path'], '/')])) throw new RuntimeException('Invalid or conflicting legacy route.');
        $paths[rtrim($entry['path'], '/')] = true;
        $plan[] = $entry;
    }
    if (count(array_unique(array_column($plan, 'term_id'))) !== count($plan)) throw new RuntimeException('Shared term identity needs separate handling.');
    usort($plan, fn($a,$b) => $a['term_id'] <=> $b['term_id']);
    $hash = hash('sha256', wp_json_encode($plan));
    if (getenv('SY3_IMPORT_DRY_RUN') === '1' || !$plan) {
        WP_CLI::line(wp_json_encode(['action' => $plan ? 'would-update' : 'already-current', 'count' => count($plan), 'plan_sha256' => $hash, 'changes' => $plan, 'legacy_routes' => count($map)])); return;
    }
    if ($production && !hash_equals($hash, (string) getenv('SY3_LATIN_PLAN_SHA'))) throw new RuntimeException('Reviewed production plan hash required.');
    if ($production) {
        $engines = $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS WHERE Name IN (%s,%s)', $wpdb->terms, $wpdb->options), ARRAY_A);
        if (count($engines) !== 2 || array_filter($engines, fn($r) => strcasecmp($r['Engine'] ?? '', 'InnoDB') !== 0)) throw new RuntimeException('Transactional tables required.');
    }
    $before = $wpdb->get_results("SELECT * FROM {$wpdb->terms} ORDER BY term_id", ARRAY_A);
    if ($wpdb->last_error) throw new RuntimeException('Cannot inspect term rows.');
    if ($wpdb->query('START TRANSACTION') === false) throw new RuntimeException('Cannot begin transaction.');
    $transaction = true;
    foreach ($plan as $entry) {
        $changed = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->terms} SET slug=%s WHERE term_id=%d AND BINARY slug=%s", $entry['new'], $entry['term_id'], $entry['old']));
        if ($changed !== 1) throw new RuntimeException('Term compare-and-swap failed.');
        $map[] = $entry;
    }
    $encoded = maybe_serialize($map);
    if ($prior === null) $saved = $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')", $option, $encoded));
    else $saved = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=%s", $encoded, $option, $prior));
    if ($saved !== 1) throw new RuntimeException('Redirect map compare-and-swap failed.');
    $expected = $before;
    foreach ($expected as &$row) foreach ($plan as $entry) if ((int) $row['term_id'] === $entry['term_id']) $row['slug'] = $entry['new'];
    unset($row);
    $after = $wpdb->get_results("SELECT * FROM {$wpdb->terms} ORDER BY term_id", ARRAY_A);
    if ($wpdb->last_error || $expected !== $after || $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $option)) !== $encoded) throw new RuntimeException('Migration read-back mismatch.');
    if ($wpdb->query('COMMIT') === false) throw new RuntimeException('Cannot commit transaction.');
    $transaction = false;
    foreach ($plan as $entry) clean_term_cache($entry['term_id'], $entry['taxonomy']);
    foreach ([$option,'alloptions','notoptions'] as $key) wp_cache_delete($key, 'options');
    WP_CLI::line(wp_json_encode(['action' => 'updated', 'count' => count($plan), 'plan_sha256' => $hash, 'changes' => $plan, 'legacy_routes' => count($map)]));
} catch (Throwable $error) {
    if ($transaction) $wpdb->query('ROLLBACK');
    WP_CLI::error($error->getMessage());
}
