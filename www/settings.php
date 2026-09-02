<?php
$configFile = '/config/config.json';
if (!file_exists($configFile)) {
    header('Location: setup.php');
    exit;
}
$config = json_decode(file_get_contents($configFile), true);

session_start();
$conf_user = $config['web_user'] ?? 'admin';
$conf_pass = $config['web_password'] ?? 'admin';
if (!isset($_SESSION['user']) || $_SESSION['user'] !== $conf_user || $_SESSION['pass'] !== $conf_pass) {
    header('Location: index.php');
    exit;
}

function getContainerMounts() {
    $mounts = [];
    if (file_exists('/proc/mounts')) {
        $lines = file('/proc/mounts');
        foreach ($lines as $line) {
            $parts = explode(' ', $line);
            if (count($parts) >= 2) {
                $mountPoint = $parts[1];
                if (preg_match('#^/(proc|dev|sys|etc|run|var|tmp|usr|bin|sbin|lib|lib64|boot|home|root|opt|srv|mnt|media)(/|$)#', $mountPoint)) continue;
                if ($mountPoint === '/') continue;
                if (is_dir($mountPoint)) {
                    $mounts[] = $mountPoint;
                }
            }
        }
    }
    
    $dirs = array_filter(glob('/*'), 'is_dir');
    $exclude = ['/bin', '/boot', '/dev', '/etc', '/home', '/lib', '/lib64', '/media', '/mnt', '/opt', '/proc', '/root', '/run', '/sbin', '/srv', '/sys', '/tmp', '/usr', '/var'];
    $rootDirs = array_values(array_diff($dirs, $exclude));
    
    $all = array_unique(array_merge($mounts, $rootDirs));
    sort($all);
    return $all;
}
$available_mounts = getContainerMounts();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jd_download_dir = trim($_POST['jd_download_dir'] ?? '');
    $jd_extraction_dir = trim($_POST['jd_extraction_dir'] ?? '');
    $main_storage_dir = trim($_POST['main_storage_dir'] ?? '');
    $additional_dirs = $_POST['additional_dirs'] ?? [];
    $valid_additional = array_values(array_filter(array_map('trim', $additional_dirs), function($d) { return !empty($d); }));

    $config['jd_download_dir'] = $jd_download_dir;
    $config['jd_extraction_dir'] = $jd_extraction_dir;
    $config['main_storage_dir'] = $main_storage_dir;
    $config['additional_dirs'] = $valid_additional;

    $config['cat_anime_ger'] = trim($_POST['cat_anime_ger'] ?? 'Anime (Ger)');
    $config['cat_anime_jap'] = trim($_POST['cat_anime_jap'] ?? 'Anime (Jap)');
    $config['cat_filme_ger'] = trim($_POST['cat_filme_ger'] ?? 'Filme');
    $config['cat_filme_jap'] = trim($_POST['cat_filme_jap'] ?? 'Filme (Jap)');
    $config['cat_hentai'] = trim($_POST['cat_hentai'] ?? 'Hentai');
    $config['plex_host'] = trim($_POST['plex_host'] ?? '');
    $config['plex_token'] = trim($_POST['plex_token'] ?? '');
    $config['overseerr_url'] = rtrim(trim($_POST['overseerr_url'] ?? ''), '/');
    $config['overseerr_api_key'] = trim($_POST['overseerr_api_key'] ?? '');
    $config['overseerr_enabled'] = isset($_POST['overseerr_enabled']) ? true : false;
    $config['overseerr_lang'] = trim($_POST['overseerr_lang'] ?? 'german');
    $config['overseerr_res'] = trim($_POST['overseerr_res'] ?? '1080p');
    $config['jd_email'] = trim($_POST['jd_email'] ?? $config['jd_email']);
    
    if (!empty($_POST['jd_password'])) {
        $config['jd_password'] = $_POST['jd_password'];
    }

    $config['al_user'] = trim($_POST['al_user'] ?? $config['al_user'] ?? '');
    if (!empty($_POST['al_password'])) {
        $config['al_password'] = $_POST['al_password'];
    }

    $config['web_user'] = trim($_POST['web_user'] ?? $config['web_user'] ?? 'admin');
    $_SESSION['user'] = $config['web_user'];
    if (!empty($_POST['web_password'])) {
        $config['web_password'] = $_POST['web_password'];
        $_SESSION['pass'] = $config['web_password'];
    }

    $config['myjd_device'] = trim($_POST['myjd_device'] ?? $config['myjd_device'] ?? '');
    $config['myjd_device_id'] = trim($_POST['myjd_device_id'] ?? $config['myjd_device_id'] ?? '');
    $config['hoster'] = (int)($_POST['hoster'] ?? $config['hoster'] ?? 0);
    $config['timedelay'] = (int)($_POST['timedelay'] ?? $config['timedelay'] ?? 500);
    $config['pushover_token'] = trim($_POST['pushover_token'] ?? $config['pushover_token'] ?? '');
    $config['pushover_user'] = trim($_POST['pushover_user'] ?? $config['pushover_user'] ?? '');
    $config['pushbullet_apikey'] = trim($_POST['pushbullet_apikey'] ?? $config['pushbullet_apikey'] ?? '');

    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    
    // Sync credentials and settings to ani.json for the backend
    $aniFile = '/config/ani.json';
    if (file_exists($aniFile)) {
        $ani = json_decode(file_get_contents($aniFile), true);
        if (is_array($ani) && isset($ani['settings'])) {
            $ani['settings']['myjd_user'] = $config['jd_email'];
            if (!empty($_POST['jd_password'])) {
                $ani['settings']['myjd_pw'] = $config['jd_password'];
            }
            $ani['settings']['myjd_device'] = $config['myjd_device'];
            $ani['settings']['hoster'] = $config['hoster'];
            $ani['settings']['al_user'] = $config['al_user'];
            if (!empty($_POST['al_password'])) {
                $ani['settings']['al_pass'] = $config['al_password'];
            }
            $ani['settings']['timedelay'] = $config['timedelay'];
            $ani['settings']['pushover_token'] = $config['pushover_token'];
            $ani['settings']['pushover_user'] = $config['pushover_user'];
            $ani['settings']['pushbullet_apikey'] = $config['pushbullet_apikey'];
            file_put_contents($aniFile, json_encode($ani, JSON_PRETTY_PRINT));
        }
    }

    $success = true;
}

$jd_download_dir = $config['jd_download_dir'] ?? '';
$jd_extraction_dir = $config['jd_extraction_dir'] ?? '';
$main_storage_dir = $config['main_storage_dir'] ?? '';
$additional_dirs = $config['additional_dirs'] ?? [];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Anime-Loads</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* --- Premium Obsidian Theme --- */
        body { background-color: #0d1117; color: #e6edf3; font-family: 'Inter', -apple-system, sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        
        .settings-card { 
            background-color: #161b22; 
            border: 1px solid #30363d; 
            border-radius: 12px; 
            padding: 40px; 
            margin-top: 50px; 
            margin-bottom: 50px; 
            max-width: 850px; 
            box-shadow: 0 8px 24px rgba(0,0,0,0.2); 
        }

        h5.text-primary {
            color: #58a6ff !important;
            font-weight: 600;
            border-bottom: 1px solid #30363d;
            padding-bottom: 10px;
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-label {
            color: #a1aab5;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
        }

        .form-control, .form-select {
            background-color: #0d1117;
            border: 1px solid #30363d;
            color: #e6edf3;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus, .form-select:focus {
            background-color: #0d1117;
            border-color: #58a6ff;
            color: #e6edf3;
            box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.3);
        }

        .btn-custom { 
            background-color: #238636; 
            border: 1px solid rgba(240,246,252,0.1); 
            color: #ffffff; 
            font-weight: 500; 
            border-radius: 6px;
            padding: 0.75rem 1.5rem;
            transition: 0.2s;
        }
        .btn-custom:hover { background-color: #2ea043; color: white; border-color: rgba(240,246,252,0.1); }
        
        .btn-outline-secondary {
            color: #a1aab5;
            border-color: #30363d;
        }
        .btn-outline-secondary:hover {
            background-color: #30363d;
            color: #e6edf3;
            border-color: #8b949e;
        }

        .btn-outline-info {
            color: #58a6ff;
            border-color: #30363d;
        }
        .btn-outline-info:hover {
            background-color: #30363d;
            border-color: #58a6ff;
            color: #58a6ff;
        }
        
        .text-success-custom { color: #3fb950; }
        .text-danger-custom { color: #f85149; }
        .text-warning-custom { color: #d29922; }
        
        .input-group-sm > .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .input-group-sm > .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        /* Micro-animations */
        .form-control, .btn {
            transition: all 0.2s ease-in-out;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="settings-card mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="test.php" class="btn btn-outline-info btn-sm me-2"><i class="bi bi-activity"></i> Diagnostics</a>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house"></i> Back to Home</a>
            </div>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> Settings saved successfully!</div>
        <?php endif; ?>

        <form method="POST">
            <h5 class="text-primary mb-3">Storage Directories</h5>
            
            <?php if (!empty($available_mounts)): ?>
            <p class="text-white-50 small mb-2">
                Found mounts (click to fill): 
                <?php foreach($available_mounts as $mount): ?>
                    <span class="badge bg-secondary cursor-pointer user-select-none me-1" style="cursor:pointer;" onclick="fillInput('input_jd_download_dir', '<?= htmlspecialchars($mount) ?>')"><?= htmlspecialchars($mount) ?></span>
                <?php endforeach; ?>
            </p>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-hdd-fill"></i> Primary Download Directory</label>
                <input type="text" class="form-control" name="jd_download_dir" id="input_jd_download_dir" value="<?= htmlspecialchars($jd_download_dir) ?>" list="availableMounts" required>
            </div>
            
            <?php if (!empty($available_mounts)): ?>
            <p class="text-white-50 small mb-2">
                Found mounts (click to fill): 
                <?php foreach($available_mounts as $mount): ?>
                    <span class="badge bg-secondary cursor-pointer user-select-none me-1" style="cursor:pointer;" onclick="fillInput('input_jd_extraction_dir', '<?= htmlspecialchars($mount) ?>')"><?= htmlspecialchars($mount) ?></span>
                <?php endforeach; ?>
            </p>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-hdd-network-fill"></i> Extraction / Staging Directory</label>
                <input type="text" class="form-control" name="jd_extraction_dir" id="input_jd_extraction_dir" value="<?= htmlspecialchars($jd_extraction_dir) ?>" list="availableMounts">
            </div>
            
            <h5 class="text-primary mt-4 mb-3">Auto-Mover & Plex</h5>
            
            <?php if (!empty($available_mounts)): ?>
            <p class="text-white-50 small mb-2">
                Found mounts (click to fill): 
                <?php foreach($available_mounts as $mount): ?>
                    <span class="badge bg-secondary cursor-pointer user-select-none me-1" style="cursor:pointer;" onclick="fillInput('input_main_storage_dir', '<?= htmlspecialchars($mount) ?>')"><?= htmlspecialchars($mount) ?></span>
                <?php endforeach; ?>
            </p>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-hdd-network-fill"></i> Final Storage Directory (Auto-Mover Target)</label>
                <input type="text" class="form-control" name="main_storage_dir" id="input_main_storage_dir" value="<?= htmlspecialchars($main_storage_dir) ?>" list="availableMounts">
            </div>
            <p class="text-white-50 small mt-3 mb-2">
                <i class="bi bi-info-circle-fill"></i> <strong>Category Subfolders:</strong> The Auto-Mover will automatically sort downloaded anime and movies into specific subfolders inside your Final Storage Directory. You can customize the names of these subfolders below.
            </p>
            <div class="row">
                <div class="col-md-6 mb-2">
                    <label class="form-label small">Category: Anime (Ger)</label>
                    <input type="text" class="form-control form-control-sm" name="cat_anime_ger" value="<?= htmlspecialchars($config['cat_anime_ger'] ?? 'Anime (Ger)') ?>">
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label small">Category: Anime (Jap)</label>
                    <input type="text" class="form-control form-control-sm" name="cat_anime_jap" value="<?= htmlspecialchars($config['cat_anime_jap'] ?? 'Anime (Jap)') ?>">
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label small">Category: Filme (Ger)</label>
                    <input type="text" class="form-control form-control-sm" name="cat_filme_ger" value="<?= htmlspecialchars($config['cat_filme_ger'] ?? 'Filme') ?>">
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label small">Category: Filme (Jap)</label>
                    <input type="text" class="form-control form-control-sm" name="cat_filme_jap" value="<?= htmlspecialchars($config['cat_filme_jap'] ?? 'Filme (Jap)') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small">Category: Hentai</label>
                    <input type="text" class="form-control form-control-sm" name="cat_hentai" value="<?= htmlspecialchars($config['cat_hentai'] ?? 'Hentai') ?>">
                </div>
            </div>
            <p class="text-white-50 small mt-3 mb-2"><i class="bi bi-play-circle-fill"></i> <strong>Plex Webhook (Optional):</strong> Trigger a library refresh in Plex automatically whenever the Auto-Mover finishes moving a new series.</p>
            <div class="row mt-2">
                <div class="col-md-6 mb-3">
                    <label class="form-label small">Plex Host (e.g. http://192.168.1.100:32400)</label>
                    <input type="text" class="form-control form-control-sm" name="plex_host" id="plex_host" value="<?= htmlspecialchars($config['plex_host'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small">Plex Token</label>
                    <input type="password" class="form-control form-control-sm" name="plex_token" id="plex_token" value="<?= htmlspecialchars($config['plex_token'] ?? '') ?>">
                </div>
            </div>
            <div class="mb-3">
                <button type="button" class="btn btn-sm btn-outline-info" id="verifyPlexBtn" onclick="verifyPlex()"><i class="bi bi-play-circle"></i> Verify Plex Connection</button>
            </div>
            <div id="plex-validation-msg" class="mb-3 text-success-custom" style="display:none; font-weight:bold;"></div>

            <h5 class="text-primary mt-4 mb-3"><i class="bi bi-collection-play me-2"></i>Overseerr / Jellyseerr Integration (Optional)</h5>
            <p class="text-white-50 small mb-2">Automatically poll your Overseerr or Jellyseerr instance every 15 minutes and queue newly requested anime series into the download queue.</p>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small">Overseerr / Jellyseerr URL</label>
                    <input type="text" class="form-control form-control-sm" name="overseerr_url" id="overseerr_url" placeholder="http://192.168.1.100:5055" value="<?= htmlspecialchars($config['overseerr_url'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small">API Key</label>
                    <input type="password" class="form-control form-control-sm" name="overseerr_api_key" id="overseerr_api_key" placeholder="Enter API Key" value="<?= htmlspecialchars($config['overseerr_api_key'] ?? '') ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label small">Default Audio</label>
                    <select class="form-control form-control-sm" name="overseerr_lang">
                        <option value="german" <?= ($config['overseerr_lang'] ?? 'german') === 'german' ? 'selected' : '' ?>>German (Deutsch)</option>
                        <option value="japanese" <?= ($config['overseerr_lang'] ?? '') === 'japanese' ? 'selected' : '' ?>>Japanese (Japanisch)</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label small">Default Resolution</label>
                    <select class="form-control form-control-sm" name="overseerr_res">
                        <option value="1080p" <?= ($config['overseerr_res'] ?? '1080p') === '1080p' ? 'selected' : '' ?>>1080p</option>
                        <option value="720p" <?= ($config['overseerr_res'] ?? '') === '720p' ? 'selected' : '' ?>>720p</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3 d-flex align-items-center pt-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="overseerr_enabled" name="overseerr_enabled" <?= !empty($config['overseerr_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="overseerr_enabled">Enable Auto-Sync</label>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <button type="button" class="btn btn-sm btn-outline-info" id="verifyOverseerrBtn" onclick="verifyOverseerr()"><i class="bi bi-link-45deg"></i> Verify Overseerr Connection</button>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="resetOverseerrBtn" onclick="resetOverseerrSync()"><i class="bi bi-arrow-counterclockwise"></i> Reset Sync History</button>
            </div>
            <div id="overseerr-validation-msg" class="mb-3 text-success-custom" style="display:none; font-weight:bold;"></div>

            <h5 class="text-primary mt-4 mb-3">Anime-Loads Login (Optional)</h5>
            <p class="text-white-50 small mb-4">
                The bot can scrape Anime-Loads completely anonymously. However, anonymous scraping has drawbacks such as stricter limits and frequent captchas. 
                <strong>Logging in</strong> with a free or VIP account provides higher scraping limits and may bypass some captchas automatically.
            </p>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small">Username</label>
                    <input type="text" class="form-control form-control-sm" name="al_user" value="<?= htmlspecialchars($config['al_user'] ?? '') ?>" placeholder="(Leave blank for anonymous)">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small">Password</label>
                    <input type="password" class="form-control form-control-sm" name="al_password" placeholder="(Leave blank to keep unchanged)">
                </div>
            </div>

            <h5 class="text-primary mt-4 mb-3">MyJDownloader Account</h5>
            <p class="text-white-50 small mb-3">Your credentials are required to send downloaded links automatically to your JDownloader instance.</p>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label small">Email</label>
                    <input type="text" class="form-control form-control-sm" name="jd_email" id="jd_email" value="<?= htmlspecialchars($config['jd_email'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label small">Password</label>
                    <input type="password" class="form-control form-control-sm" name="jd_password" id="jd_password" placeholder="(Leave blank to keep unchanged)">
                </div>
                <div class="col-md-4 mb-3" id="myjd_device_container">
                    <label class="form-label small">Device Name</label>
                    <div class="input-group input-group-sm">
                            <div id="myjd_device_wrapper" style="flex: 1;">
                                <input type="text" class="form-control form-control-sm w-100" name="myjd_device" id="myjd_device" value="<?= htmlspecialchars($config['myjd_device'] ?? '') ?>">
                                <input type="hidden" name="myjd_device_id" id="myjd_device_id" value="<?= htmlspecialchars($config['myjd_device_id'] ?? '') ?>">
                            </div>
                        <button class="btn btn-outline-info" type="button" id="fetchJdBtn" onclick="fetchJdInstances()" title="Fetch Instances"><i class="bi bi-cloud-download"></i></button>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <button type="button" class="btn btn-sm btn-outline-info" id="verifyJdBtn" onclick="verifyJD()"><i class="bi bi-shield-check"></i> Verify Credentials</button>
            </div>
            <div id="jd-validation-msg" class="mb-3 text-success-custom" style="display:none; font-weight:bold;"></div>
            
            <h5 class="text-primary mt-4 mb-3">Scraping Preferences</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small">Preferred Hoster</label>
                    <select class="form-control form-control-sm" name="hoster">
                        <option value="0" <?= (isset($config['hoster']) && $config['hoster'] == 0) ? 'selected' : '' ?>>DDownload</option>
                        <option value="1" <?= (isset($config['hoster']) && $config['hoster'] == 1) ? 'selected' : '' ?>>Rapidgator</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small">Scrape Delay (ms)</label>
                    <input type="number" class="form-control form-control-sm" name="timedelay" value="<?= htmlspecialchars($config['timedelay'] ?? 500) ?>">
                </div>
            </div>

            <h5 class="text-primary mt-4 mb-3">Push Notifications</h5>
            <p class="text-white-50 small mb-2">Optional setup for mobile notifications on download success/failure.</p>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label small">Pushover Token</label>
                    <input type="text" class="form-control form-control-sm" name="pushover_token" value="<?= htmlspecialchars($config['pushover_token'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label small">Pushover User</label>
                    <input type="text" class="form-control form-control-sm" name="pushover_user" value="<?= htmlspecialchars($config['pushover_user'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label small">Pushbullet API Key</label>
                    <input type="password" class="form-control form-control-sm" name="pushbullet_apikey" value="<?= htmlspecialchars($config['pushbullet_apikey'] ?? '') ?>">
                </div>
            </div>

            <h5 class="text-primary mt-4 mb-3">Web Interface Login</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small">Username</label>
                    <input type="text" class="form-control form-control-sm" name="web_user" value="<?= htmlspecialchars($config['web_user'] ?? 'admin') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small">Password</label>
                    <input type="password" class="form-control form-control-sm" name="web_password" placeholder="(Leave blank to keep unchanged)">
                </div>
            </div>

            <h5 class="text-primary mt-4 mb-3">Backup / Additional Targets</h5>
            <p class="text-white-50 small mb-3">Add additional paths (like external USB drives) here. The built-in Folder Manager tool allows you to easily move your finished anime folders to these disks to free up space on your main drive.</p>
            
            <?php if (!empty($available_mounts)): ?>
            <p class="text-white-50 small mb-3">
                Found mounts (click to add): 
                <?php foreach($available_mounts as $mount): ?>
                    <span class="badge bg-secondary cursor-pointer user-select-none me-1" style="cursor:pointer;" onclick="fillOrAddDir('<?= htmlspecialchars($mount) ?>')"><?= htmlspecialchars($mount) ?></span>
                <?php endforeach; ?>
            </p>
            <?php endif; ?>

            <datalist id="availableMounts">
            <?php foreach($available_mounts as $mount): ?>
                <option value="<?= htmlspecialchars($mount) ?>"></option>
            <?php endforeach; ?>
            </datalist>

            <div id="dir-list"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="addAdditionalDirField()">+ Add Backup Directory</button>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-custom btn-lg"><i class="bi bi-save"></i> Save Settings</button>
            </div>
        </form>
    </div>
</div>

<script>
    function fillInput(id, path) {
        const input = document.getElementById(id);
        if (input) {
            input.value = path;
        }
    }

    function fillOrAddDir(path) {
        const inputs = document.querySelectorAll('input[name="additional_dirs[]"]');
        for (let i = 0; i < inputs.length; i++) {
            if (inputs[i].value.trim() === '') {
                inputs[i].value = path;
                return;
            }
        }
        addAdditionalDirField(path);
    }

    let dirCount = 0;
    function addAdditionalDirField(value = '') {
        const id = 'additional_' + dirCount++;
        const html = `
            <div class="d-flex mb-2" id="container_${id}">
                <input type="text" class="form-control form-control-sm me-2" name="additional_dirs[]" value="${value}" list="availableMounts">
                <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('container_${id}').remove()"><i class="bi bi-trash"></i></button>
            </div>
        `;
        document.getElementById('dir-list').insertAdjacentHTML('beforeend', html);
    }
    
    window.onload = () => {
        const additionalDirs = <?= json_encode($additional_dirs) ?>;
        if (additionalDirs.length === 0) {
            addAdditionalDirField();
        } else {
            additionalDirs.forEach(dir => addAdditionalDirField(dir));
        }
    };

    async function verifyPlex() {
        const host = document.getElementById('plex_host').value.trim();
        const token = document.getElementById('plex_token').value.trim();
        const msg = document.getElementById('plex-validation-msg');
        const btn = document.getElementById('verifyPlexBtn');

        if(!host || !token) {
            alert("Please enter both Plex Host and Plex Token to verify.");
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verifying...';
        msg.style.display = 'none';

        try {
            const formData = new FormData();
            formData.append('action', 'verify_plex');
            formData.append('host', host);
            formData.append('token', token);
            
            const res = await fetch('setup_ajax.php', { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                msg.className = "mb-3 text-success-custom";
                msg.innerHTML = '<i class="bi bi-check-circle"></i> Plex webhook successfully reached!';
                msg.style.display = 'block';
            } else {
                msg.className = "mb-3 text-danger-custom";
                msg.innerHTML = '<i class="bi bi-x-circle"></i> ' + (data.error || "Connection failed.");
                msg.style.display = 'block';
            }
        } catch (e) {
            msg.className = "mb-3 text-warning-custom";
            msg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Network error during verification.';
            msg.style.display = 'block';
        }
        
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-circle"></i> Verify Plex Connection';
    }

    async function verifyOverseerr() {
        const url = document.getElementById('overseerr_url').value.trim();
        const apiKey = document.getElementById('overseerr_api_key').value.trim();
        const msg = document.getElementById('overseerr-validation-msg');
        const btn = document.getElementById('verifyOverseerrBtn');

        if(!url || !apiKey) {
            alert("Please enter both Overseerr URL and API Key to verify.");
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verifying...';
        msg.style.display = 'none';

        try {
            const formData = new FormData();
            formData.append('action', 'verify_overseerr');
            formData.append('url', url);
            formData.append('api_key', apiKey);
            
            const res = await fetch('setup_ajax.php', { method: 'POST', body: formData });
            const data = await res.json();
            
            if(data.success) {
                msg.className = "mb-3 text-success-custom";
                msg.innerHTML = '<i class="bi bi-check-circle"></i> Connection successful!' + (data.version ? ' (Version: ' + data.version + ')' : '');
                msg.style.display = 'block';
            } else {
                msg.className = "mb-3 text-danger-custom";
                msg.innerHTML = '<i class="bi bi-x-circle"></i> ' + (data.error || 'Verification failed.');
                msg.style.display = 'block';
            }
        } catch (e) {
            msg.className = "mb-3 text-warning-custom";
            msg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Network error during verification.';
            msg.style.display = 'block';
        }
        
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-link-45deg"></i> Verify Overseerr Connection';
    }

    async function resetOverseerrSync() {
        if (!confirm("Are you sure you want to reset Overseerr sync history? This will allow previously queued or ignored requests from Overseerr to be re-evaluated.")) return;
        const btn = document.getElementById('resetOverseerrBtn');
        const msg = document.getElementById('overseerr-validation-msg');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Resetting...';

        try {
            const formData = new FormData();
            formData.append('action', 'reset_overseerr_sync');
            const res = await fetch('setup_ajax.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                msg.className = "mb-3 text-info";
                msg.innerHTML = '<i class="bi bi-check-circle"></i> ' + data.message;
                msg.style.display = 'block';
            } else {
                msg.className = "mb-3 text-danger-custom";
                msg.innerHTML = '<i class="bi bi-x-circle"></i> ' + (data.error || 'Failed to reset.');
                msg.style.display = 'block';
            }
        } catch (e) {
            msg.className = "mb-3 text-warning-custom";
            msg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Network error.';
            msg.style.display = 'block';
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Reset Sync History';
    }

    async function verifyJD() {
        // Since we decoupled fetching from verification in setup, this can just verify the text/select is populated
        const device = document.getElementById('myjd_device');
        const msg = document.getElementById('jd-validation-msg');
        
        if (!device || !device.value.trim()) {
            msg.className = "mb-3 text-danger-custom";
            msg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Please provide a Device Name.';
            msg.style.display = 'block';
            return;
        }
        
        msg.className = "mb-3 text-success-custom";
        msg.innerHTML = '<i class="bi bi-check-circle"></i> Ready to save.';
        msg.style.display = 'block';
    }

    async function fetchJdInstances() {
        const email = document.getElementById('jd_email').value.trim();
        const pwd = document.getElementById('jd_password').value;
        const msg = document.getElementById('jd-validation-msg');
        const btn = document.getElementById('fetchJdBtn');
        const wrapper = document.getElementById('myjd_device_wrapper');
        const currentDevice = document.getElementById('myjd_device').value;
        
        if (!email) {
            msg.style.display = 'block';
            msg.className = 'mb-3 text-danger-custom';
            msg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Please enter email first.';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        msg.style.display = 'none';

        try {
            const fd = new FormData();
            fd.append('action', 'verify_jd');
            fd.append('email', email);
            fd.append('password', pwd);

            const response = await fetch('setup_ajax.php', { method: 'POST', body: fd });
            const data = await response.json();

            if (data.success) {
                msg.style.display = 'block';
                msg.className = 'mb-3 text-success-custom';
                
                if (data.devices && data.devices.length > 0) {
                    msg.innerHTML = '<i class="bi bi-check-circle"></i> Login successful! Select your instance below.';
                    let selectHtml = `<select class="form-control form-control-sm w-100" id="myjd_device" name="myjd_device" onchange="document.getElementById('myjd_device_id').value = this.options[this.selectedIndex].getAttribute('data-id')">`;
                    selectHtml += `<option value="" data-id="" disabled>Select an instance...</option>`;
                    data.devices.forEach(dev => {
                        const selected = (dev.name === currentDevice) ? 'selected' : '';
                        selectHtml += `<option value="${dev.name}" data-id="${dev.id}" ${selected}>${dev.name}</option>`;
                    });
                    selectHtml += `</select>`;
                    selectHtml += `<input type="hidden" id="myjd_device_id" name="myjd_device_id" value="">`;
                    wrapper.innerHTML = selectHtml;
                    // Trigger onchange to set the hidden input if one is selected
                    const sel = document.getElementById('myjd_device');
                    if(sel.selectedIndex >= 0) {
                        document.getElementById('myjd_device_id').value = sel.options[sel.selectedIndex].getAttribute('data-id');
                    }
                } else {
                    const errMsg = data.error ? ` (${data.error})` : '';
                    msg.innerHTML = `<i class="bi bi-exclamation-triangle"></i> Login successful, but no instances found${errMsg}! Please type it manually.`;
                    wrapper.innerHTML = `<input type="text" class="form-control form-control-sm w-100" name="myjd_device" id="myjd_device" value="${currentDevice}"><input type="hidden" id="myjd_device_id" name="myjd_device_id" value="">`;
                }
            } else {
                msg.style.display = 'block';
                msg.className = 'mb-3 text-danger-custom';
                msg.innerHTML = '<i class="bi bi-x-circle"></i> Fetch Failed: ' + data.error;
            }
        } catch (e) {
            msg.className = "mb-3 text-danger-custom";
            msg.innerHTML = '<i class="bi bi-x-circle"></i> Network error during fetch.';
            msg.style.display = 'block';
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-download"></i>';
    }
</script>
</body>
</html>
