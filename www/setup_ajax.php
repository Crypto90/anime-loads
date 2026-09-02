<?php
header('Content-Type: application/json');

$configFile = '/config/config.json';
if (file_exists($configFile)) {
    session_start();
    $config = json_decode(file_get_contents($configFile), true);
    $conf_user = $config['web_user'] ?? 'admin';
    $conf_pass = $config['web_password'] ?? 'admin';
    if (!isset($_SESSION['user']) || $_SESSION['user'] !== $conf_user || !isset($_SESSION['pass']) || $_SESSION['pass'] !== $conf_pass) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

if (!isset($_POST['action'])) {
    echo json_encode(['success' => false, 'error' => 'No action specified']);
    exit;
}

$action = $_POST['action'];

if ($action === 'verify_dir') {
    $dir = $_POST['dir'] ?? '';
    if (empty($dir)) {
        echo json_encode(['success' => false, 'error' => 'Directory path is empty']);
        exit;
    }
    
    if (is_dir($dir)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Directory does not exist']);
    }
    exit;
}

if ($action === 'verify_jd') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Email or password missing']);
        exit;
    }

    // Escape arguments for safety
    $cmd = sprintf('python3 /var/www/html/jd_verify.py %s %s 2>&1', escapeshellarg($email), escapeshellarg($password));
    $output = shell_exec($cmd);
    
    if ($output === null) {
         echo json_encode(['success' => false, 'error' => 'Failed to execute validation script']);
         exit;
    }

    // Pass the JSON output directly from python
    echo $output;
    exit;
}

if ($action === 'verify_plex') {
    $host = rtrim($_POST['host'] ?? '', '/');
    $token = $_POST['token'] ?? '';
    
    if (empty($host) || empty($token)) {
        echo json_encode(['success' => false, 'error' => 'Host or token missing']);
        exit;
    }

    $url = $host . '/library/sections/all/refresh?X-Plex-Token=' . urlencode($token);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Plex returns 200 OK on successful refresh trigger, or 401 Unauthorized
    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Plex verification failed (HTTP ' . $httpCode . ')']);
    }
    exit;
}

if ($action === 'verify_overseerr') {
    $url = rtrim($_POST['url'] ?? '', '/');
    $api_key = $_POST['api_key'] ?? '';
    
    if (empty($url) || empty($api_key)) {
        echo json_encode(['success' => false, 'error' => 'URL or API key missing']);
        exit;
    }

    $ch = curl_init($url . '/api/v1/status');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Api-Key: ' . $api_key]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $json = json_decode($response, true);
        $version = $json['version'] ?? '';
        echo json_encode(['success' => true, 'version' => $version]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Overseerr verification failed (HTTP ' . $httpCode . ')']);
    }
    exit;
}

if ($action === 'reset_overseerr_sync') {
    $syncedFile = '/config/overseerr_synced.json';
    if (file_exists($syncedFile)) {
        @unlink($syncedFile);
    }
    echo json_encode(['success' => true, 'message' => 'Sync history reset. Next sync will re-evaluate all open requests.']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;
