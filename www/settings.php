<?php
$configFile = '/config/config.json';
if (!file_exists($configFile)) {
    header('Location: setup.php');
    exit;
}
$config = json_decode(file_get_contents($configFile), true);

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
    $config['jd_email'] = trim($_POST['jd_email'] ?? $config['jd_email']);
    
    if (!empty($_POST['jd_password'])) {
        $config['jd_password'] = $_POST['jd_password'];
    }

    $config['web_user'] = trim($_POST['web_user'] ?? $config['web_user'] ?? 'admin');
    if (!empty($_POST['web_password'])) {
        $config['web_password'] = $_POST['web_password'];
    }

    $config['myjd_device'] = trim($_POST['myjd_device'] ?? $config['myjd_device'] ?? '');
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
    <style>
        body { background-color: #121212; color: #e0e0e0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .settings-card { background: rgba(30, 30, 30, 0.85); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 30px; margin-top: 50px; margin-bottom: 50px; max-width: 800px; box-shadow: 0 8px 32px rgba(0,0,0,0.5); }
        .btn-custom { background: linear-gradient(45deg, #0d6efd, #0dcaf0); border: none; color: white; font-weight: 500; }
        .btn-custom:hover { background: linear-gradient(45deg, #0b5ed7, #0bacce); color: white; }
    </style>
</head>
<body>
<div class="container">
    <div class="settings-card mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-gear-fill text-primary"></i> Settings</h2>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house"></i> Back to Home</a>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> Settings saved successfully!</div>
        <?php endif; ?>

        <form method="POST">
            <h5 class="text-primary mb-3">Storage Directories</h5>
            <div class="mb-3">
                <label class="form-label text-warning"><i class="bi bi-hdd-fill"></i> Primary Download Directory</label>
                <input type="text" class="form-control" name="jd_download_dir" value="<?= htmlspecialchars($jd_download_dir) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-info"><i class="bi bi-hdd-network-fill"></i> Extraction / Staging Directory</label>
                <input type="text" class="form-control" name="jd_extraction_dir" value="<?= htmlspecialchars($jd_extraction_dir) ?>">
            </div>
            
            <h5 class="text-primary mt-4 mb-3">Auto-Mover & Plex</h5>
            <div class="mb-3">
                <label class="form-label text-success"><i class="bi bi-hdd-network-fill"></i> Final Storage Directory (Auto-Mover Target)</label>
                <input type="text" class="form-control" name="main_storage_dir" value="<?= htmlspecialchars($main_storage_dir) ?>">
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

            <h5 class="text-primary mt-4 mb-3">MyJDownloader Account</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label small">Email</label>
                    <input type="text" class="form-control form-control-sm" name="jd_email" id="jd_email" value="<?= htmlspecialchars($config['jd_email'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label small">Password</label>
                    <input type="password" class="form-control form-control-sm" name="jd_password" id="jd_password" placeholder="(Leave blank to keep unchanged)">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label small">Device Name</label>
                    <input type="text" class="form-control form-control-sm" name="myjd_device" id="myjd_device" value="<?= htmlspecialchars($config['myjd_device'] ?? '') ?>">
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
            <div id="dir-list"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="addAdditionalDirField()">+ Add Backup Directory</button>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-custom btn-lg"><i class="bi bi-save"></i> Save Settings</button>
            </div>
        </form>
    </div>
</div>

<script>
    let dirCount = 0;
    function addAdditionalDirField(value = '') {
        const id = 'additional_' + dirCount++;
        const html = `
            <div class="d-flex mb-2" id="container_${id}">
                <input type="text" class="form-control form-control-sm me-2" name="additional_dirs[]" value="${value}">
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

    async function verifyJD() {
        const email = document.getElementById('jd_email').value.trim();
        const pass = document.getElementById('jd_password').value;
        const msg = document.getElementById('jd-validation-msg');
        const btn = document.getElementById('verifyJdBtn');

        if(!email || !pass) {
            alert("Please enter JD email and password to verify. If you are keeping your password unchanged (left blank), please enter it just for this verification step.");
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verifying...';
        msg.style.display = 'none';

        try {
            const formData = new FormData();
            formData.append('action', 'verify_jd');
            formData.append('email', email);
            formData.append('password', pass);
            
            const res = await fetch('setup_ajax.php', { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                msg.className = "mb-3 text-success-custom";
                msg.innerHTML = '<i class="bi bi-check-circle"></i> Authentication Successful!';
                msg.style.display = 'block';
            } else {
                msg.className = "mb-3 text-danger-custom";
                msg.innerHTML = '<i class="bi bi-x-circle"></i> ' + (data.error || "Authentication Failed");
                msg.style.display = 'block';
            }
        } catch (e) {
            msg.className = "mb-3 text-warning-custom";
            msg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Network error during verification.';
            msg.style.display = 'block';
        }
        
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-shield-check"></i> Verify Credentials';
    }
</script>
</body>
</html>
