<?php
/** Isolated-preview integration checks. Creates and deletes only marked fixtures. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() === 'production') exit('Preview only.');
require_once ABSPATH . 'wp-admin/includes/post.php';
require_once __DIR__.'/editorial-test-fixture.php';
$fixtures = []; $assertions = 0;
$old_last = get_option('sy3_editorial_last_publication', null);
$old_pause=get_option('sy3_editorial_paused',null);
$check = function ($test, $message) use (&$assertions) { if (!$test) throw new RuntimeException($message); $assertions++; };
$lock_filter = function ($query) { return preg_match('/^SELECT (GET_LOCK|RELEASE_LOCK)\(/', $query) ? 'SELECT 1' : $query; };
// SQLite preview has no MySQL locks; only the lock transport is substituted.
add_filter('query', $lock_filter);
$restrict = function ($query) use (&$fixtures) { if ($query->get('post_type') === 'post' && $fixtures) $query->set('post__in', $fixtures); };
add_action('pre_get_posts', $restrict);
try {
    update_option('sy3_editorial_paused',false);
    delete_option('sy3_editorial_last_publication');
    $cover = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1])[0]->ID;
    $now = time() - 10 * DAY_IN_SECONDS;
    for ($n = 0; $n < 3; $n++) {
        $id = wp_insert_post(['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Editorial isolated fixture ' . $n, 'post_content' => sy3_editorial_fixture_content(), 'meta_input' => ['_sy3_editorial_batch' => SY3_EDITORIAL_BATCH, '_sy3_editorial_enabled' => '1', '_sy3_editorial_at' => $now + $n * 60, '_sy3_editorial_test_fixture' => '1']]);
        $fixtures[] = $id; set_post_thumbnail($id, $cover);
        update_post_meta($id,'_sy3_editorial_reviewed_hash',sy3_editorial_review_hash($id));
    }
    $check(sy3_editorial_publish_due($now - 1)['status'] === 'nothing_due', 'Future drafts must stay private');
    $result = sy3_editorial_publish_due($now); $check($result['status'] === 'published' && $result['id'] === $fixtures[0], 'First due draft');
    $check(get_post_status($fixtures[0]) === 'publish', 'Published status persisted');
    $check(get_post_status($fixtures[1]) === 'draft', 'No batch release');
    $check(sy3_editorial_publish_due($now + 3600)['status'] === 'interval', '72h cadence');
    // Simulate process failure before the cadence option was stored.
    delete_option('sy3_editorial_last_publication');
    $check(sy3_editorial_publish_due($now + 3600)['status'] === 'interval', 'Recover cadence from published post');
    update_post_meta($fixtures[1], '_sy3_editorial_enabled', '0');
    $editor = get_user_by('login', 'sy3_preview_editor');
    update_post_meta($fixtures[2], '_edit_lock', time() . ':' . $editor->ID);
    $check(sy3_editorial_publish_due($now + SY3_EDITORIAL_INTERVAL)['status'] === 'being_edited', 'Do not publish a draft while the author is editing');
    delete_post_meta($fixtures[2], '_edit_lock');
    delete_post_thumbnail($fixtures[2]);
    $check(sy3_editorial_publish_due($now + SY3_EDITORIAL_INTERVAL)['status'] === 'incomplete', 'Missing cover blocks publication');
    set_post_thumbnail($fixtures[2], $cover);
    $result = sy3_editorial_publish_due($now + SY3_EDITORIAL_INTERVAL); $check($result['status'] === 'published' && $result['id'] === $fixtures[2], 'Disabled draft is skipped: ' . wp_json_encode($result));
    $check(get_post_status($fixtures[1]) === 'draft', 'Manual opt-out respected');
    $check(sy3_editorial_publish_due($now + 100 * DAY_IN_SECONDS)['status'] === 'nothing_due', 'Long downtime does not release disabled content');
    WP_CLI::success($assertions . ' checks passed; fixture-only preview; MySQL lock transport substituted.');
} finally {
    remove_action('pre_get_posts', $restrict); remove_filter('query', $lock_filter);
    foreach ($fixtures as $id) if (get_post_meta($id, '_sy3_editorial_test_fixture', true) === '1') wp_delete_post($id, true);
    if ($old_last === null) delete_option('sy3_editorial_last_publication'); else update_option('sy3_editorial_last_publication', $old_last, false);
    if($old_pause===null)delete_option('sy3_editorial_paused');else update_option('sy3_editorial_paused',$old_pause,false);
}
