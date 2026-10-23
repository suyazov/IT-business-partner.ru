<?php
/** One-time, additive case-tag rollout. Never rewrites content or proof metadata. */
if (!defined('WP_CLI') || !WP_CLI || realpath(ABSPATH) !== '/var/www/sy3-wordpress' || wp_get_environment_type() !== 'production' || home_url('/') !== 'https://sy3.ru/' || get_option('sy3_site_mode') !== 'expertise' || !is_ssl()) throw new RuntimeException('Exact production site required.');
global $wpdb;
$root = dirname(__DIR__, 2);
$private = '/root/preview/sy3-wordpress-20261022/private/editorial-refactor-20261023/';
$manifest = json_decode(file_get_contents(dirname(__DIR__) . '/content/case-tags.json'), true, 512, JSON_THROW_ON_ERROR);
$taxonomy = 'sy3_case_tag';
if ($manifest['taxonomy'] !== $taxonomy || count($manifest['cases']) !== 27 || count($manifest['terms']) !== 26) throw new RuntimeException('Unexpected manifest.');
$expected = [];
foreach ($manifest['terms'] as $slug => $name) if (!preg_match('/^[a-z0-9-]+$/D', $slug) || !is_string($name) || $name === '') throw new RuntimeException('Invalid tag.');
foreach ($manifest['cases'] as $slug => $tags) {
    $post = get_page_by_path($slug, OBJECT, 'sy3_case');
    if (!$post || $post->post_status !== 'publish' || !$tags || array_diff($tags, array_keys($manifest['terms'])) || count(array_unique($tags)) !== count($tags)) throw new RuntimeException('Invalid case mapping.');
    sort($tags); $expected[(int) $post->ID] = $tags;
}
ksort($expected);
$state = static function () use ($wpdb, $taxonomy): array {
    $terms = $wpdb->get_results($wpdb->prepare("SELECT t.term_id,t.name,t.slug,tt.term_taxonomy_id FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE tt.taxonomy=%s ORDER BY t.slug", $taxonomy), ARRAY_A);
    $links = $wpdb->get_results($wpdb->prepare("SELECT r.object_id,t.slug FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=r.term_taxonomy_id JOIN {$wpdb->terms} t ON t.term_id=tt.term_id WHERE tt.taxonomy=%s ORDER BY r.object_id,t.slug", $taxonomy), ARRAY_A);
    if ($wpdb->last_error || !is_array($terms) || !is_array($links)) throw new RuntimeException('Tag state read failed.');
    return ['terms' => $terms, 'links' => $links];
};
$initial = $state();
$current = [];
foreach ($initial['links'] as $link) $current[(int) $link['object_id']][] = $link['slug'];
ksort($current);
$names = array_column($initial['terms'], 'name', 'slug'); $plannedNames = $manifest['terms']; ksort($names); ksort($plannedNames);
$already = $names === $plannedNames && $current === $expected;
if (!$already && ($initial['terms'] || $initial['links'])) throw new RuntimeException('Existing editor-owned tags preserved; manual reconciliation required.');
if (getenv('SY3_CASE_TAGS_APPLY') !== '1') {
    echo wp_json_encode(['status' => $already ? 'ALREADY_CURRENT' : 'DRY_RUN_READY', 'cases' => count($expected), 'terms' => count($plannedNames), 'writes' => 0]) . "\n"; return;
}
$head = getenv('SY3_CASE_TAGS_HEAD');
$review = json_decode(file_get_contents($private . 'case-tags-review.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^[a-f0-9]{40}$/D', $head ?: '') || ($review['verdict'] ?? '') !== 'PASS' || ($review['exact_head'] ?? '') !== $head) throw new RuntimeException('Exact-head review required.');
$git = static function (array $args) use ($root): string {
    $p = proc_open(array_merge(['git', '-C', $root], $args), [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    if (!is_resource($p)) throw new RuntimeException('Git failed.');
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($p) !== 0 || $out === false) throw new RuntimeException('Git inspection failed.'); return $out;
};
if (trim($git(['rev-parse','HEAD'])) !== $head || trim($git(['status','--porcelain'])) !== '') throw new RuntimeException('Clean reviewed checkout required.');
foreach (['content/case-tags.json','tools/seed-case-tags.php','plugin/sy3-content.php'] as $file) if (file_get_contents(dirname(__DIR__) . '/' . $file) !== $git(['show', $head . ':wordpress/' . $file])) throw new RuntimeException('Reviewed input mismatch.');
if (!taxonomy_exists($taxonomy) || get_taxonomy($taxonomy)->hierarchical || get_taxonomy($taxonomy)->object_type !== ['sy3_case'] || get_taxonomy($taxonomy)->rewrite['slug'] !== 'case-tags' || hash_file('sha256', WP_PLUGIN_DIR . '/sy3-content/sy3-content.php') !== hash_file('sha256', dirname(__DIR__) . '/plugin/sy3-content.php')) throw new RuntimeException('Reviewed taxonomy plugin must be deployed first.');
$pid = (int) getenv('SY3_CASE_TAGS_LOCK_PID'); $held = [];
foreach (glob('/proc/' . $pid . '/fd/*') ?: [] as $fd) {
    $path = @readlink($fd); $info = @file_get_contents('/proc/' . $pid . '/fdinfo/' . basename($fd));
    if (in_array($path, ['/run/lock/kimi-projects/sy3.ru.lock','/run/lock/kimi-projects/suyazov-IT-business-partner.ru.lock'], true) && $info && str_contains($info, 'FLOCK') && str_contains($info, 'WRITE')) $held[basename($path)] = true;
}
if (count($held) !== 2) throw new RuntimeException('Both project locks required.');
$tables = [$wpdb->posts, $wpdb->postmeta, $wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships];
$engines = $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS WHERE Name IN (%s,%s,%s,%s,%s)', ...$tables), ARRAY_A);
if (count($engines) !== 5 || array_filter($engines, static fn($r) => strcasecmp($r['Engine'] ?? '', 'InnoDB') !== 0)) throw new RuntimeException('Transactional tables required.');
$oldTermIds = array_map('intval', $wpdb->get_col("SELECT term_id FROM {$wpdb->terms} ORDER BY term_id"));
$audit = static function () use ($wpdb, $taxonomy, $oldTermIds): array {
    $queries = [
        'posts' => "SELECT * FROM {$wpdb->posts} ORDER BY ID",
        'metadata' => "SELECT * FROM {$wpdb->postmeta} ORDER BY meta_id",
        'other_taxonomies' => $wpdb->prepare("SELECT * FROM {$wpdb->term_taxonomy} WHERE taxonomy<>%s ORDER BY term_taxonomy_id", $taxonomy),
        'other_relationships' => $wpdb->prepare("SELECT r.* FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=r.term_taxonomy_id WHERE tt.taxonomy<>%s ORDER BY r.object_id,r.term_taxonomy_id", $taxonomy),
        'existing_terms' => "SELECT * FROM {$wpdb->terms} WHERE term_id IN (" . implode(',', $oldTermIds ?: [0]) . ") ORDER BY term_id",
        'editorial' => "SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name IN ('sy3_editorial_paused','sy3_editorial_last_publication','sy3_search_last_publication','sy3_site_mode','home','siteurl') ORDER BY option_name",
    ];
    $result = [];
    foreach ($queries as $key => $sql) { $rows = $wpdb->get_results($sql, ARRAY_A); if ($wpdb->last_error || !is_array($rows)) throw new RuntimeException('Protected audit failed.'); $result[$key] = hash('sha256', serialize($rows)); }
    return $result;
};
$before = $audit(); $tx = false; $committed = false; $createdTerms = [];
$receipt = ['status' => 'PREFLIGHT_PASS', 'head' => $head, 'cases' => count($expected), 'terms' => count($plannedNames), 'protected_before' => $before, 'backups_created' => false];
$save = static function () use ($private, &$receipt): void {
    $p = $private . 'case-tags-apply.json'; if (file_put_contents($p, wp_json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false || !chmod($p, 0600)) throw new RuntimeException('Receipt write failed.');
};
$save();
try {
    if ($wpdb->query('START TRANSACTION') === false) throw new RuntimeException('Cannot start transaction.'); $tx = true;
    if ($wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE ID IN (" . implode(',', array_keys($expected)) . ') ORDER BY ID FOR UPDATE') === null || $wpdb->last_error || $state() !== $initial || $audit() !== $before) throw new RuntimeException('Concurrent change preserved.');
    if (!$already) {
        $ids = [];
        foreach ($manifest['terms'] as $slug => $name) {
            $term = wp_insert_term($name, $taxonomy, ['slug' => $slug]);
            if (is_wp_error($term)) throw new RuntimeException('Cannot create reviewed tag: ' . $slug);
            $ids[$slug] = (int) $term['term_id'];
            $createdTerms[] = $ids[$slug];
        }
        foreach ($expected as $id => $tags) if (is_wp_error(wp_set_object_terms($id, array_map(static fn($s) => $ids[$s], $tags), $taxonomy, false))) throw new RuntimeException('Cannot assign reviewed tags.');
    }
    $after = $state(); $links = [];
    foreach ($after['links'] as $link) $links[(int) $link['object_id']][] = $link['slug']; ksort($links);
    $namesAfter = array_column($after['terms'], 'name', 'slug'); ksort($namesAfter);
    if ($namesAfter !== $plannedNames || $links !== $expected || $audit() !== $before) throw new RuntimeException('Tag result or protected data mismatch.');
    if ($wpdb->query('COMMIT') === false) throw new RuntimeException('Commit failed.'); $tx = false; $committed = true;
    clean_object_term_cache(array_keys($expected), 'sy3_case'); clean_term_cache(array_column($after['terms'], 'term_id'), $taxonomy);
    flush_rewrite_rules(false);
    if ($state() !== $after || $audit() !== $before || !array_filter(array_keys(get_option('rewrite_rules', [])), static fn($r) => str_starts_with($r, 'case-tags/'))) throw new RuntimeException('Committed tag read-back or routes failed.');
    $receipt += ['protected_after' => $before, 'relationships' => count($after['links']), 'content_writes' => 0, 'soft_rewrite_flush' => true];
    $receipt['status'] = $already ? 'ALREADY_CURRENT_VERIFIED' : 'APPLIED_VERIFIED'; $save(); echo wp_json_encode($receipt) . "\n";
} catch (Throwable $e) {
    if ($tx) $wpdb->query('ROLLBACK');
    clean_object_term_cache(array_keys($expected), 'sy3_case');
    if ($createdTerms) clean_term_cache($createdTerms, $taxonomy);
    $receipt['status'] = $committed ? 'COMMITTED_NEEDS_VERIFICATION' : 'ROLLED_BACK'; $receipt['error'] = $e->getMessage(); $save(); throw $e;
}
