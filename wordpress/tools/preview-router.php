<?php
/** Router for an owner-local preview only. Never install in the public vhost. */
header('X-Robots-Tag: noindex, nofollow, noarchive');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('#(^|/)\.|wp-config\.php|/private/|\.sqlite#i', $path)) {
    http_response_code(404); exit('Not found');
}
$public = getenv('SY3_PREVIEW_PUBLIC');
if (!$public || !is_file($public . '/wp-load.php')) { http_response_code(503); exit('Preview root unavailable'); }
if ($path !== '/' && is_file($public . $path)) return false;
require $public . '/index.php';
