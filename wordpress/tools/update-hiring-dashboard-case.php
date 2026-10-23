<?php
/** One reviewed case and one native media attachment; never run the site-wide importer. */
if (!defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') exit(1);
if (realpath(ABSPATH) !== '/var/www/sy3-wordpress' || wp_get_environment_type() !== 'production' || home_url('/') !== 'https://sy3.ru/' || get_option('sy3_site_mode') !== 'expertise' || !is_ssl()) throw new RuntimeException('Exact production site required.');
require_once dirname(__DIR__) . '/content/blocks.php';
global $wpdb;
$private = '/root/preview/sy3-wordpress-20261022/private/editorial-refactor-20261023/';
$case = json_decode(file_get_contents(dirname(__DIR__) . '/content/cases.json'), true, 512, JSON_THROW_ON_ERROR)[0];
if ($case['slug'] !== 'hiring-funnel-dashboard') throw new RuntimeException('Exact case required.');
$body = sy3_sections($case['sections']);
if (hash('sha256', $body) !== '16b88a3012e363406bebffade671a1d3869c75a6aba797c46e18182461cd8be9') throw new RuntimeException('Reviewed body differs.');
$hash = static fn($value) => hash('sha256', wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$read = static function (bool $lock = false) use ($wpdb): array {
    $suffix = $lock ? ' FOR UPDATE' : '';
    $p = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE ID=574" . $suffix, ARRAY_A);
    $m = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id=574 ORDER BY meta_id" . $suffix, ARRAY_A);
    if ($wpdb->last_error || !$p || !is_array($m) || $p['post_type'] !== 'sy3_case' || $p['post_name'] !== 'hiring-funnel-dashboard' || $p['post_status'] !== 'publish') throw new RuntimeException('Case identity/read failed.');
    return ['post' => $p, 'meta' => $m];
};
$values = ['_sy3_label' => $case['label'], '_sy3_description' => $case['excerpt'], '_sy3_proof' => maybe_serialize($case['proof'])];
$metaValue = static function (array $rows, string $key): ?string {
    $found = array_values(array_filter($rows, static fn($r) => $r['meta_key'] === $key));
    if (count($found) > 1) throw new RuntimeException('Duplicate owned metadata.');
    return $found ? $found[0]['meta_value'] : null;
};
$protected = static function (array $record) use ($hash): void {
    $p = $record['post']; unset($p['post_title'], $p['post_excerpt'], $p['post_content']);
    $m = array_values(array_filter($record['meta'], static fn($r) => !in_array($r['meta_key'], ['_sy3_label','_sy3_description','_sy3_proof','_thumbnail_id'], true)));
    if ($hash($p) !== '91413230cdd9740d1793a6a5ebe2fda756dfab32b3929c976bd561c99c8638be' || $hash($m) !== '5562502c0155245567215b38bbfa84db5dd3414e46712b74f131425756f1c642') throw new RuntimeException('Protected fields or metadata differ.');
};
$cover = $case['cover'];
$source = dirname(__DIR__) . '/content/media/' . basename($cover['file']);
if ($cover['sha256'] !== '8dad3d2f8ab344ab7fe19de053b696b0fce84c732e89bac6fd4b8d4b3a3a746c' || !is_file($source) || !hash_equals($cover['sha256'], hash_file('sha256', $source))) throw new RuntimeException('Reviewed cover differs.');
$record = $read(); $protected($record);
if ($record['post']['post_content'] === $body) {
    $attachment = (int) $metaValue($record['meta'], '_thumbnail_id');
    foreach ($values as $key => $value) if ($metaValue($record['meta'], $key) !== $value) throw new RuntimeException('Partial case metadata update.');
    $file = $attachment ? get_attached_file($attachment) : false;
    if ($record['post']['post_title'] !== $case['title'] || $record['post']['post_excerpt'] !== $case['excerpt'] || !$file || !is_file($file) || hash_file('sha256', $file) !== $cover['sha256'] || get_post_meta($attachment, '_sy3_media_hash', true) !== $cover['sha256']) throw new RuntimeException('Partial case or media update.');
    clean_post_cache(574); clean_post_cache($attachment);
    if ($read() !== $record || get_post_field('post_content', 574, 'raw') !== $body || (int) get_post_meta(574, '_thumbnail_id', true) !== $attachment) throw new RuntimeException('Idempotent cache/read-back failed.');
    WP_CLI::line(wp_json_encode(['status' => 'ALREADY_CURRENT', 'writes' => 0, 'id' => 574, 'attachment' => $attachment])); return;
}
if ($hash($record['post']) !== 'ef348072c797cff96d5e1674f678392d1a74744051c27dd17da0426fbc1f5557' || $hash($record['meta']) !== 'a2de43e632d740fb1ad97b72a7f8ad5d0c01cdf1a2fca80e50d4e15894db1c0d') throw new RuntimeException('Concurrent/editor changes preserved.');
if (getenv('SY3_HIRING_CASE_APPLY') !== '1') {
    WP_CLI::line(wp_json_encode(['status' => 'DRY_RUN_READY', 'writes' => 0, 'id' => 574, 'body_after' => hash('sha256', $body), 'cover' => $cover['sha256']])); return;
}
$head = getenv('SY3_HIRING_REVIEWED_HEAD');
$review = json_decode(file_get_contents($private . 'hiring-case-review.json'), true, 512, JSON_THROW_ON_ERROR);
if (!$head || ($review['verdict'] ?? '') !== 'PASS' || ($review['exact_head'] ?? '') !== $head) throw new RuntimeException('Exact-head independent review required.');
$git = static function (array $arguments): string {
    $process = proc_open(array_merge(['git', '-C', dirname(__DIR__, 2)], $arguments), [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot inspect reviewed checkout.');
    fclose($pipes[0]); $output = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $output === false) throw new RuntimeException('Git input inspection failed.');
    return $output;
};
if (!preg_match('/^[a-f0-9]{40}$/D', $head) || trim($git(['rev-parse','HEAD'])) !== $head || trim($git(['status','--porcelain'])) !== '') throw new RuntimeException('Clean exact reviewed checkout required.');
foreach (['content/cases.json','content/blocks.php','tools/update-hiring-dashboard-case.php','content/media/hiring-dashboard-overview.webp'] as $relative) {
    if (file_get_contents(dirname(__DIR__) . '/' . $relative) !== $git(['show', $head . ':wordpress/' . $relative])) throw new RuntimeException('Reviewed input differs from Git.');
}
$pid = (int) getenv('SY3_HIRING_LOCK_PID'); $held = [];
foreach (glob('/proc/' . $pid . '/fd/*') ?: [] as $fd) {
    $path = @readlink($fd);
    if (!in_array($path, ['/run/lock/kimi-projects/sy3.ru.lock','/run/lock/kimi-projects/suyazov-IT-business-partner.ru.lock'], true)) continue;
    $info = @file_get_contents('/proc/' . $pid . '/fdinfo/' . basename($fd));
    if ($info && str_contains($info, 'FLOCK') && str_contains($info, 'WRITE')) $held[basename($path)] = true;
}
if (count($held) !== 2) throw new RuntimeException('Both project locks required.');
foreach (['hiring-dashboard-current.webp' => 'aabe0a2667af8da4efec42a0733aa270f0e50e7f69f64cd608347d1e7b7113b6', 'hiring-dashboard-stage.webp' => '3ff2c1acc7b54364aaf43a34dafccb35090f29d82f414fdb62c677eacaeaa75f'] as $file => $sha) {
    $path = get_theme_file_path('assets/' . $file);
    if (!is_file($path) || hash_file('sha256', $path) !== $sha) throw new RuntimeException('Inline image not deployed.');
}
$engines = $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS WHERE Name IN (%s,%s)', $wpdb->posts, $wpdb->postmeta), ARRAY_A);
if (count($engines) !== 2 || array_filter($engines, static fn($r) => strcasecmp($r['Engine'] ?? '', 'InnoDB') !== 0)) throw new RuntimeException('InnoDB required.');
$audit = static function (int $attachment = 0) use ($wpdb, $hash): array {
    $skip = $attachment ? '574,' . $attachment : '574';
    $posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE ID NOT IN ($skip) ORDER BY ID", ARRAY_A);
    $meta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id NOT IN ($skip) ORDER BY meta_id", ARRAY_A);
    $relations = $wpdb->get_results("SELECT * FROM {$wpdb->term_relationships} ORDER BY object_id,term_taxonomy_id", ARRAY_A);
    $options = $wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name IN ('sy3_editorial_paused','sy3_editorial_last_publication','sy3_search_last_publication') ORDER BY option_name", ARRAY_A);
    if ($wpdb->last_error || !is_array($posts) || !is_array($meta) || !is_array($relations) || !is_array($options)) throw new RuntimeException('Protected audit failed.');
    return ['posts' => $hash($posts), 'meta' => $hash($meta), 'relationships' => $hash($relations), 'editorial' => $options];
};
$beforeAudit = $audit();
$receipt = ['status' => 'PREFLIGHT_PASS', 'id' => 574, 'head' => $head, 'body_before' => hash('sha256', $record['post']['post_content']), 'body_after' => hash('sha256', $body), 'before' => $beforeAudit, 'backups_created' => false];
$save = static function () use (&$receipt, $private): void {
    $path = $private . 'hiring-case-apply.json';
    if (file_put_contents($path, wp_json_encode($receipt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) === false || !chmod($path, 0600)) throw new RuntimeException('Receipt write failed.');
};
$save(); $transaction = false; $committed = false; $attachment = 0;
try {
    if ($wpdb->query('START TRANSACTION') === false) throw new RuntimeException('Cannot start transaction.'); $transaction = true;
    if ($read(true) !== $record) throw new RuntimeException('Concurrent case change preserved.');
    // Native WordPress media, one original file; no intermediate derivatives required.
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $upload = wp_upload_bits(basename($cover['file']), null, file_get_contents($source));
    if ($upload['error']) throw new RuntimeException('Cover upload failed.');
    $receipt['uploaded_file'] = $upload['file']; $save();
    $attachment = wp_insert_attachment(['post_mime_type' => 'image/webp', 'post_title' => $cover['alt'], 'post_excerpt' => $cover['caption'], 'post_status' => 'inherit', 'post_author' => (int) $record['post']['post_author']], $upload['file'], 574, true);
    if (is_wp_error($attachment)) throw new RuntimeException('Attachment creation failed.');
    $receipt['attachment'] = $attachment; $save();
    add_filter('intermediate_image_sizes_advanced', '__return_empty_array');
    try { $metadata = wp_generate_attachment_metadata($attachment, $upload['file']); } finally { remove_filter('intermediate_image_sizes_advanced', '__return_empty_array'); }
    if (empty($metadata['width']) || empty($metadata['height'])) throw new RuntimeException('Image metadata failed.');
    wp_update_attachment_metadata($attachment, $metadata);
    update_post_meta($attachment, '_sy3_media_hash', $cover['sha256']);
    update_post_meta($attachment, '_wp_attachment_image_alt', $cover['alt']);
    if (!add_post_meta(574, '_thumbnail_id', $attachment, true)) throw new RuntimeException('Thumbnail ownership changed.');
    foreach ($values as $key => $value) {
        $old = $metaValue($record['meta'], $key);
        if ($old === null || $wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value=%s WHERE post_id=574 AND meta_key=%s AND BINARY meta_value=%s", $value, $key, $old)) !== 1) throw new RuntimeException('Metadata CAS failed.');
    }
    if ($wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_title=%s,post_excerpt=%s,post_content=%s WHERE ID=574 AND BINARY post_content=%s", $case['title'], $case['excerpt'], $body, $record['post']['post_content'])) !== 1) throw new RuntimeException('Case CAS failed.');
    $saved = $read(true); $protected($saved);
    if ($saved['post']['post_title'] !== $case['title'] || $saved['post']['post_excerpt'] !== $case['excerpt'] || $saved['post']['post_content'] !== $body || (int) $metaValue($saved['meta'], '_thumbnail_id') !== $attachment) throw new RuntimeException('Case read-back mismatch.');
    foreach ($values as $key => $value) if ($metaValue($saved['meta'], $key) !== $value) throw new RuntimeException('Metadata read-back mismatch.');
    if ($audit($attachment) !== $beforeAudit || hash_file('sha256', get_attached_file($attachment)) !== $cover['sha256']) throw new RuntimeException('Protected audit or image mismatch.');
    if ($wpdb->query('COMMIT') === false) throw new RuntimeException('Commit failed.'); $transaction = false; $committed = true;
    clean_post_cache(574); clean_post_cache($attachment);
    $receipt['status'] = 'APPLIED_VERIFIED'; $receipt['protected_records_preserved'] = true; $save();
    WP_CLI::line(wp_json_encode($receipt));
} catch (Throwable $error) {
    $rolledBack = !$transaction || $wpdb->query('ROLLBACK') !== false;
    clean_post_cache(574); if ($attachment) clean_post_cache($attachment);
    $receipt['status'] = $committed ? 'COMMITTED_NEEDS_INSPECTION' : ($rolledBack ? 'ROLLED_BACK' : 'ROLLBACK_UNCONFIRMED');
    $receipt['retention'] = 'Preserve any uploaded file outside Git for scoped recovery; inspect by 2026-10-24 18:00 Europe/Moscow under storage lock. Do not delete receipts.';
    try { $save(); } catch (Throwable $receiptError) { WP_CLI::warning($committed ? 'Database commit succeeded; receipt requires inspection.' : 'Receipt could not be saved; inspect database before retry.'); }
    throw $error;
}
