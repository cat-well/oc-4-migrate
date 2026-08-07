<?php
// 1C exchange entry — 'cart' in the path so the host's Cloudflare treats it as dynamic (its cache bypasses cart/checkout URLs); forwards to the Manline exchange controller.
chdir(__DIR__ . '/..');

$_GET['route'] = 'extension/manline/exchange';
$_GET['type'] = $_GET['type'] ?? '';
$_GET['mode'] = $_GET['mode'] ?? '';

require __DIR__ . '/../index.php';
