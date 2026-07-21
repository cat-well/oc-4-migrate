<?php
/**
 * 1C exchange — compatibility entry point.
 *
 * 1C is configured to call this exact URL, so the filename is kept for backward
 * compatibility only. There is no legacy code here: this just forwards to the
 * Manline exchange controller through the normal OpenCart front controller.
 */
chdir(__DIR__ . '/..');

$_GET['route'] = 'extension/manline/exchange';
$_GET['type'] = $_GET['type'] ?? '';
$_GET['mode'] = $_GET['mode'] ?? '';

require __DIR__ . '/../index.php';
