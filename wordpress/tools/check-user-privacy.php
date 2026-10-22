<?php
/** Read-only REST regression: wp eval-file .../check-user-privacy.php */
if (!defined('WP_CLI') || !WP_CLI) exit(1);
$previous = get_current_user_id();
try {
    wp_set_current_user(0);
    foreach ([false, true] as $json_body) {
        $request = new WP_REST_Request('GET', '/wp/v2/users');
        $request->set_query_params(['context' => 'edit', '_fields' => 'id,name']);
        if ($json_body) {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body('{"context":"view"}');
        }
        if (rest_do_request($request)->get_status() !== 401) WP_CLI::error('Anonymous user collection exposed.');
    }
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    if (!$admins) WP_CLI::error('No administrator available for permission check.');
    wp_set_current_user((int) $admins[0]);
    $request = new WP_REST_Request('GET', '/wp/v2/users');
    $request->set_query_params(['context' => 'view', '_fields' => 'id,name']);
    if (rest_do_request($request)->get_status() !== 200) WP_CLI::error('Administrator author selector blocked.');
    WP_CLI::success('Anonymous/query and JSON-body collection denied; administrator author selector allowed. No records changed.');
} finally {
    wp_set_current_user($previous);
}
