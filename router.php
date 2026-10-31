<?php
// Router untuk uji lokal dengan "php -S" (tidak dipakai di Hostinger).
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$f = __DIR__ . '/app' . $p;
if ($p !== '/' && is_file($f) && !preg_match('#^/(lib|halaman|data|konten)/#', $p) && !str_ends_with($p, '.php')) return false;
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/app/index.php';
