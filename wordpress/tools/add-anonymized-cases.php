<?php
/** Insert only two reviewed anonymous cases; existing editor-owned records are immutable. */
if (!defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') exit(1);
$production = wp_get_environment_type() === 'production' && realpath(ABSPATH) === '/var/www/sy3-wordpress' && home_url('/') === 'https://sy3.ru/';
$preview = wp_get_environment_type() !== 'production' && realpath(ABSPATH) === '/root/preview/sy3-wordpress-20261022/public' && home_url('/') === 'http://127.0.0.1:8186/';
if (get_option('sy3_site_mode') !== 'expertise' || (!$production && !$preview)) WP_CLI::error('Unexpected site identity.');
if ($production && (getenv('SY3_ALLOW_PRODUCTION_IMPORT') !== '1' || !is_ssl())) WP_CLI::error('Production authority and HTTPS required.');
require_once dirname(__DIR__) . '/content/blocks.php';
$slugs = ['hiring-funnel-dashboard', 'crm-hr-requirements'];
$all = json_decode(file_get_contents(dirname(__DIR__) . '/content/cases.json'), true, 512, JSON_THROW_ON_ERROR);
$definitions = array_values(array_filter($all, fn($item) => in_array($item['slug'], $slugs, true)));
if (array_column($definitions, 'slug') !== $slugs) WP_CLI::error('Expected exactly two reviewed definitions in order.');
global $wpdb;
$transaction = false; $created = [];
try {
    $owner = $production ? get_user_by('login', 'artem_admin') : get_user_by('id', 1);
    if (!$owner || !user_can($owner, 'manage_options')) throw new RuntimeException('Expected existing site owner.');
    $author = (int) $owner->ID;
    if ($production) {
        $engines = $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS WHERE Name IN (%s,%s,%s,%s)', $wpdb->posts, $wpdb->postmeta, $wpdb->term_relationships, $wpdb->term_taxonomy), ARRAY_A);
        if (count($engines) !== 4 || array_filter($engines, fn($r) => strcasecmp($r['Engine'] ?? '', 'InnoDB') !== 0)) throw new RuntimeException('Transactional tables required.');
    }
    $plans = [];
    foreach ($definitions as $index => $item) {
        $term = get_term_by('name', $item['topic'], 'sy3_case_topic');
        if (!$term) throw new RuntimeException('Existing case topic required.');
        $related = [];
        foreach ($item['related'] as $key) {
            if (!str_starts_with($key, 'service:')) throw new RuntimeException('Unexpected related type.');
            $service = get_page_by_path(substr($key, 8), OBJECT, 'sy3_service');
            if (!$service || $service->post_status !== 'publish') throw new RuntimeException('Expected public related service.');
            $related[] = $service->ID;
        }
        if (!empty($item['source']) || !empty($item['affineDocId']) || !empty($item['cover'])) throw new RuntimeException('Anonymous definitions must not expose private sources or import media.');
        if (!is_file(dirname(__DIR__) . '/theme/assets/' . $item['art'] . '.svg')) throw new RuntimeException('Missing case illustration.');
        $body = sy3_sections($item['sections']);
        $fields = ['post_type' => 'sy3_case', 'post_name' => $item['slug'], 'post_title' => $item['title'], 'post_excerpt' => $item['excerpt'], 'post_content' => $body, 'post_author' => $author, 'menu_order' => $index - 2, 'comment_status' => 'closed', 'ping_status' => 'closed'];
        $meta = ['_sy3_seed' => 'case:' . $item['slug'], '_sy3_seed_hash' => hash('sha256', $body), '_sy3_label' => $item['label'], '_sy3_art' => $item['art'], '_sy3_description' => $item['excerpt'], '_sy3_proof' => $item['proof'], '_sy3_related' => implode(',', $related)];
        $plans[] = ['fields' => $fields, 'meta' => $meta, 'term' => (int) $term->term_id];
    }
    $inspect = static function (array $plan): ?int {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT p.* FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_sy3_seed' WHERE p.post_name=%s OR m.meta_value=%s", $plan['fields']['post_name'], $plan['meta']['_sy3_seed']), ARRAY_A);
        if ($wpdb->last_error || count($rows) > 1) throw new RuntimeException('Duplicate case identity preserved.');
        if (!$rows) return null;
        $row = $rows[0]; $id = (int) $row['ID'];
        if ($row['post_status'] !== 'publish') throw new RuntimeException('Existing case status preserved.');
        foreach ($plan['fields'] as $key => $value) if ((string) $row[$key] !== (string) $value) throw new RuntimeException('Existing case edits preserved: ' . $key);
        foreach ($plan['meta'] as $key => $value) if (get_post_meta($id, $key, true) !== $value) throw new RuntimeException('Existing case metadata preserved.');
        $terms = wp_get_object_terms($id, 'sy3_case_topic', ['fields' => 'ids']);
        if (is_wp_error($terms) || array_map('intval', $terms) !== [$plan['term']]) throw new RuntimeException('Existing case taxonomy preserved.');
        return $id;
    };
    $report = [];
    foreach ($plans as $plan) $report[] = ['slug' => $plan['fields']['post_name'], 'id' => $inspect($plan)];
    $dry = getenv('SY3_IMPORT_DRY_RUN') === '1';
    if ($dry || !in_array(null, array_column($report, 'id'), true)) {
        WP_CLI::line(wp_json_encode(['dry_run' => $dry, 'action' => in_array(null, array_column($report, 'id'), true) ? 'would-create' : 'already-current', 'cases' => $report])); return;
    }
    $max = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");
    $audit = static function () use ($wpdb, $max): array {
        $result = [];
        foreach (['posts' => ["ID <= $max", 'ID'], 'postmeta' => ["post_id <= $max", 'meta_id'], 'term_relationships' => ["object_id <= $max", 'object_id,term_taxonomy_id']] as $key => [$where, $order]) {
            $table = $wpdb->$key;
            $rows = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY $order", ARRAY_A);
            if ($wpdb->last_error) throw new RuntimeException('Protected data audit failed.');
            $result[$key] = hash('sha256', wp_json_encode($rows));
        }
        foreach (['sy3_editorial_paused', 'sy3_editorial_last_publication', 'sy3_search_last_publication', 'sy3_editorial_last_check'] as $key) $result[$key] = get_option($key);
        return $result;
    };
    $before = $audit();
    if ($wpdb->query('START TRANSACTION') === false) throw new RuntimeException('Cannot begin transaction.');
    $transaction = true;
    foreach ($plans as $i => $plan) {
        if ($id = $inspect($plan)) { $report[$i]['id'] = $id; continue; }
        $id = wp_insert_post(wp_slash($plan['fields'] + ['post_status' => 'draft']), true);
        if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        $created[] = $id;
        foreach ($plan['meta'] as $key => $value) if (!add_post_meta($id, $key, wp_slash($value), true)) throw new RuntimeException('Cannot add owned metadata.');
        $assigned = wp_set_object_terms($id, [$plan['term']], 'sy3_case_topic');
        if (is_wp_error($assigned)) throw new RuntimeException($assigned->get_error_message());
        $published = wp_update_post(['ID' => $id, 'post_status' => 'publish'], true);
        if (is_wp_error($published)) throw new RuntimeException($published->get_error_message());
        clean_post_cache($id);
        if ($inspect($plan) !== $id) throw new RuntimeException('New case read-back failed.');
        $report[$i]['id'] = $id;
    }
    if ($before !== $audit()) throw new RuntimeException('Unrelated records or editorial clocks changed.');
    if ($wpdb->query('COMMIT') === false) throw new RuntimeException('Cannot commit transaction.');
    $transaction = false;
    foreach ($created as $id) clean_post_cache($id);
    WP_CLI::line(wp_json_encode(['action' => 'created', 'cases' => $report, 'protected_unchanged' => true]));
} catch (Throwable $error) {
    if ($transaction) $wpdb->query('ROLLBACK');
    foreach ($created as $id) clean_post_cache($id);
    WP_CLI::error($error->getMessage());
}
