<?php
session_start();

$configFile = '/config/config.json';
if (!file_exists($configFile)) {
    http_response_code(403);
    exit('Unauthorized');
}

$config = json_decode(file_get_contents($configFile), true);
$conf_user = $config['web_user'] ?? 'admin';
$conf_pass = $config['web_password'] ?? 'admin';

if (!isset($_SESSION['user']) || $_SESSION['user'] !== $conf_user || !isset($_SESSION['pass']) || $_SESSION['pass'] !== $conf_pass) {
    http_response_code(401);
    exit('Unauthorized');
}

$file = basename($_GET['file'] ?? '');
if (empty($file) || !preg_match('/^(backup|pre_restore)_[0-9_\-]+\.zip$/', $file)) {
    http_response_code(400);
    exit('Invalid backup file name.');
}

$backupPath = '/config/backups/' . $file;
if (!is_file($backupPath)) {
    http_response_code(404);
    exit('Backup file not found.');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($backupPath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($backupPath);
exit;
