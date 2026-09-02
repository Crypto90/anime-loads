<?php
$configFile = '/config/config.json';
if (!is_dir('/config')) mkdir('/config', 0777, true);

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

    $config = [
        'base_dir' => $_POST['base_dir'] ?? '/usr/src/app',
        'jd_email' => $_POST['jd_email'] ?? '',
        'jd_password' => $_POST['jd_password'] ?? '',
        'jd_download_dir' => $jd_download_dir,
        'jd_extraction_dir' => $jd_extraction_dir,
        'main_storage_dir' => $main_storage_dir,
        'additional_dirs' => $valid_additional,
        'cat_anime_ger' => trim($_POST['cat_anime_ger'] ?? 'Anime (Ger)'),
        'cat_anime_jap' => trim($_POST['cat_anime_jap'] ?? 'Anime (Jap)'),
        'cat_filme_ger' => trim($_POST['cat_filme_ger'] ?? 'Filme'),
        'cat_filme_jap' => trim($_POST['cat_filme_jap'] ?? 'Filme (Jap)'),
        'cat_hentai' => trim($_POST['cat_hentai'] ?? 'Hentai'),
        'plex_host' => trim($_POST['plex_host'] ?? ''),
        'plex_token' => trim($_POST['plex_token'] ?? ''),
        'web_user' => $_POST['web_user'] ?? 'admin',
        'web_password' => $_POST['web_password'] ?? 'admin',
        'myjd_device' => trim($_POST['myjd_device'] ?? ''),
        'myjd_device_id' => trim($_POST['myjd_device_id'] ?? ''),
        'hoster' => (int)($_POST['hoster'] ?? 0),
        'al_user' => trim($_POST['al_user'] ?? ''),
        'al_password' => $_POST['al_password'] ?? '',
        'setup_complete' => true
    ];
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));

    // Sync credentials and settings to ani.json for the backend
    $aniFile = '/config/ani.json';
    if (file_exists($aniFile)) {
        $ani = json_decode(file_get_contents($aniFile), true);
        if (is_array($ani) && isset($ani['settings'])) {
            $ani['settings']['myjd_user'] = $config['jd_email'];
            $ani['settings']['myjd_pw'] = $config['jd_password'];
            $ani['settings']['myjd_device'] = $config['myjd_device'];
            $ani['settings']['hoster'] = $config['hoster'];
            $ani['settings']['al_user'] = $config['al_user'];
            $ani['settings']['al_pass'] = $config['al_password'];
            file_put_contents($aniFile, json_encode($ani, JSON_PRETTY_PRINT));
        }
    }

    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anime-Loads Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            color: #e0e0e0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .setup-card {
            background: rgba(25, 30, 40, 0.7);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 700px;
            position: relative;
            overflow: hidden;
        }

        .setup-title {
            font-weight: 800;
            background: -webkit-linear-gradient(45deg, #00c6ff, #0072ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 30px;
            text-align: center;
        }

        .step-container {
            display: none;
            animation: fadeIn 0.4s ease-in-out;
        }
        .step-container.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .form-label {
            font-weight: 600;
            color: #b8c6db;
        }

        .form-control {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.15);
            color: #fff;
            border-radius: 10px;
            padding: 12px 15px;
        }
        
        .form-control:focus {
            background: rgba(0,0,0,0.5);
            border-color: #00c6ff;
            color: #fff;
            box-shadow: 0 0 10px rgba(0, 198, 255, 0.3);
        }

        .btn-custom {
            background: linear-gradient(45deg, #00c6ff, #0072ff);
            border: none;
            color: #fff;
            font-weight: bold;
            border-radius: 10px;
            padding: 12px 25px;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 198, 255, 0.4);
            color: #fff;
        }
        
        .btn-outline-custom {
            background: transparent;
            border: 1px solid #00c6ff;
            color: #00c6ff;
            font-weight: bold;
            border-radius: 10px;
            padding: 12px 25px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-custom:hover {
            background: rgba(0, 198, 255, 0.1);
            color: #00c6ff;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step-dot {
            width: 12px;
            height: 12px;
            background: rgba(255,255,255,0.5);
            border-radius: 50%;
            margin: 0 8px;
            transition: all 0.3s;
        }

        .step-dot.active {
            background: #00c6ff;
            box-shadow: 0 0 10px #00c6ff;
            transform: scale(1.3);
        }

        .dir-item {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .validation-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .text-success-custom { color: #00e676; }
        .text-danger-custom { color: #ff5252; }
        .text-warning-custom { color: #ffd740; }

    </style>
</head>
<body>

<div class="container">
    <div class="setup-card mx-auto">
        <h2 class="setup-title">Anime-Loads Wizard</h2>
        
        <div class="step-indicator">
            <div class="step-dot active" id="dot-1"></div>
            <div class="step-dot" id="dot-2"></div>
            <div class="step-dot" id="dot-3"></div>
            <div class="step-dot" id="dot-4"></div>
        </div>

        <form method="POST" id="setupForm">
            
            <!-- Step 1: Web Interface -->
            <div class="step-container active" id="step-1">
                <h4 class="mb-4 text-center">Web Interface Login</h4>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="web_user" value="admin" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="web_password" placeholder="Choose a secure password" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Base Directory (Internal Container Path)</label>
                    <input type="text" class="form-control" name="base_dir" value="/usr/src/app" required>
                    <div class="form-text text-white-50">Advanced: Only change if you altered the Dockerfile.</div>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-custom" onclick="nextStep(2)">Next <i class="bi bi-arrow-right"></i></button>
                </div>
            </div>

            <!-- Step 2: Directories -->
            <div class="step-container" id="step-2">
                <h4 class="mb-4 text-center">Storage Directories</h4>
                
                <div class="mb-4">
                    <label class="form-label text-warning-custom"><i class="bi bi-hdd-fill"></i> Primary Download Directory (Required)</label>
                    <p class="text-white-50 small mb-2">Where JDownloader downloads the raw archives (e.g. your SSD Cache).</p>
                    
                    <?php if (!empty($available_mounts)): ?>
                    <p class="text-white-50 small mb-2">
                        Found mounts (click to fill): 
                        <?php foreach($available_mounts as $mount): ?>
                            <span class="badge bg-secondary cursor-pointer user-select-none me-1" style="cursor:pointer;" onclick="fillInput('input_primary_dir', '<?= htmlspecialchars($mount) ?>')"><?= htmlspecialchars($mount) ?></span>
                        <?php endforeach; ?>
                    </p>
                    <?php endif; ?>

                    <div class="dir-item mb-0" id="container_primary_dir">
                        <input type="text" class="form-control" name="jd_download_dir" placeholder="/downloads" onblur="verifyDir('primary_dir')" id="input_primary_dir" list="availableMounts" required>
                        <div class="validation-icon" id="icon_primary_dir"><i class="bi bi-question-circle text-white-50"></i></div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-info"><i class="bi bi-hdd-network-fill"></i> Extraction / Staging Directory (Optional)</label>
                    <p class="text-white-50 small mb-2">If you want JDownloader to extract to a different disk (e.g. SSD). The Auto-Mover will monitor this folder.<br><strong>If left empty:</strong> The Auto-Mover will monitor your Primary Download Directory instead.<br><strong>Important:</strong> You must configure JDownloader's Archive Extractor or Packagizer to point to this path manually!</p>
                    
                    <?php if (!empty($available_mounts)): ?>
                    <p class="text-white-50 small mb-2">
                        Found mounts (click to fill): 
                        <?php foreach($available_mounts as $mount): ?>
                            <span class="badge bg-secondary cursor-pointer user-select-none me-1" style="cursor:pointer;" onclick="fillInput('input_extraction_dir', '<?= htmlspecialchars($mount) ?>')"><?= htmlspecialchars($mount) ?></span>
                        <?php endforeach; ?>
                    </p>
                    <?php endif; ?>

                    <div class="dir-item mb-0" id="container_extraction_dir">
                        <input type="text" class="form-control" name="jd_extraction_dir" placeholder="/extraction" onblur="verifyDir('extraction_dir')" id="input_extraction_dir" list="availableMounts">
                        <div class="validation-icon" id="icon_extraction_dir"><i class="bi bi-question-circle text-white-50"></i></div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-success-custom"><i class="bi bi-hdd-network-fill"></i> Final Storage Directory (Optional)</label>
                    <p class="text-white-50 small mb-2">If set, enables the Auto-Mover cron job to move finished extracted folders here.</p>
                    
                    <?php if (!empty($available_mounts)): ?>
                    <p class="text-white-50 small mb-2">
                        Found mounts (click to fill): 
                        <?php foreach($available_mounts as $mount): ?>
                            <span class="badge bg-secondary cursor-pointer user-select-none me-1" style="cursor:pointer;" onclick="fillInput('input_main_storage_dir', '<?= htmlspecialchars($mount) ?>')"><?= htmlspecialchars($mount) ?></span>
                        <?php endforeach; ?>
                    </p>
                    <?php endif; ?>

                    <div class="dir-item mb-0" id="container_main_storage_dir">
                        <input type="text" class="form-control" name="main_storage_dir" placeholder="/video" onblur="verifyDir('main_storage_dir')" id="input_main_storage_dir" list="availableMounts">
                        <div class="validation-icon" id="icon_main_storage_dir"><i class="bi bi-question-circle text-white-50"></i></div>
                    </div>
                </div>
                
                <hr class="border-secondary mb-4">
                <label class="form-label"><i class="bi bi-folder-symlink-fill"></i> Backup / Additional Targets (Optional)</label>
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

                <div id="dir-list">
                    <!-- Additional Directory items will be injected here -->
                </div>
                
                <div class="mb-4 text-center">
                    <button type="button" class="btn btn-sm btn-outline-custom" onclick="addAdditionalDirField()">+ Add Backup Directory</button>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-custom" onclick="nextStep(1)"><i class="bi bi-arrow-left"></i> Back</button>
                    <button type="button" class="btn btn-custom" onclick="validateDirsAndNext()">Next <i class="bi bi-arrow-right"></i></button>
                </div>
            </div>

            <!-- Step 3: External Accounts -->
            <div class="step-container" id="step-3">
                <h4 class="mb-4 text-center">External Accounts</h4>
                
                <h5 class="text-primary mt-4 mb-3">Anime-Loads Login (Optional)</h5>
                <p class="text-white-50 small mb-4">
                    The bot can scrape Anime-Loads completely anonymously. However, anonymous scraping has drawbacks such as stricter limits and frequent captchas. 
                    <strong>Logging in</strong> with a free or VIP account provides higher scraping limits and may bypass some captchas automatically.
                </p>
                <div class="mb-3">
                    <label class="form-label">Anime-Loads Username</label>
                    <input type="text" class="form-control" name="al_user" placeholder="(Leave blank for anonymous scraping)">
                </div>
                <div class="mb-3">
                    <label class="form-label">Anime-Loads Password</label>
                    <input type="password" class="form-control" name="al_password" placeholder="(Leave blank if anonymous)">
                </div>

                <hr class="border-secondary my-4">

                <h5 class="text-primary mb-3">MyJDownloader Account (Required)</h5>
                <p class="text-white-50 mb-4">Your credentials are required to send downloaded links automatically to your JDownloader instance.</p>
                
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="jd_email" name="jd_email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" id="jd_password" name="jd_password" required>
                </div>
                <div class="mb-3 text-end">
                    <button type="button" class="btn btn-sm btn-outline-info" id="fetchJdBtn" onclick="fetchJdInstances()"><i class="bi bi-cloud-download"></i> Login & Fetch Instances</button>
                </div>
                <div class="mb-4" id="myjd_device_container">
                    <label class="form-label">Device Name</label>
                    <div id="myjd_device_wrapper">
                        <input type="text" class="form-control" id="myjd_device" name="myjd_device" placeholder="e.g. JDownloader@anime-loads" required>
                    </div>
                </div>
                
                <div id="jd-validation-msg" class="mb-3 text-center" style="display:none; font-weight:bold;"></div>

                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-custom" onclick="nextStep(2)"><i class="bi bi-arrow-left"></i> Back</button>
                    <button type="button" class="btn btn-custom" id="verifyJdBtn" onclick="verifyJdAndNext()">Verify & Next <i class="bi bi-arrow-right"></i></button>
                </div>
            </div>

            <!-- Step 4: Automation -->
            <div class="step-container" id="step-4">
                <h4 class="mb-4 text-center">Auto-Mover & Plex</h4>
                
                <div id="step4_disabled" style="display: none;" class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i> 
                    <strong>Auto-Mover Disabled:</strong> You did not configure a Final Storage Directory in Step 2, so the Auto-Mover and Plex integrations are disabled. You can skip these settings.
                </div>

                <div id="step4_content">
                    <p class="text-white-50 small mb-3">
                        <i class="bi bi-cloud-arrow-down-fill"></i> <strong>Scraping Preferences:</strong> Select your preferred file hoster.
                    </p>
                    <div class="mb-4">
                        <label class="form-label small">Preferred Hoster</label>
                        <select class="form-control form-control-sm" name="hoster">
                            <option value="0">DDownload</option>
                            <option value="1">Rapidgator</option>
                        </select>
                    </div>
                    <hr class="border-secondary mb-3">

                    <p class="text-white-50 small mb-3">
                        <i class="bi bi-info-circle-fill"></i> <strong>Category Subfolders:</strong> The Auto-Mover will automatically sort downloaded anime and movies into specific subfolders inside your <strong>Final Storage Directory</strong>. You can customize the names of these subfolders below.
                    </p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small">Category: Anime (Ger)</label>
                            <input type="text" class="form-control form-control-sm" name="cat_anime_ger" value="Anime (Ger)">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small">Category: Anime (Jap)</label>
                            <input type="text" class="form-control form-control-sm" name="cat_anime_jap" value="Anime (Jap)">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small">Category: Filme (Ger)</label>
                            <input type="text" class="form-control form-control-sm" name="cat_filme_ger" value="Filme">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small">Category: Filme (Jap)</label>
                            <input type="text" class="form-control form-control-sm" name="cat_filme_jap" value="Filme (Jap)">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label small">Category: Hentai</label>
                            <input type="text" class="form-control form-control-sm" name="cat_hentai" value="Hentai">
                        </div>
                    </div>

                    <hr class="border-secondary mb-3">
                    <p class="text-white-50 small mb-1"><i class="bi bi-play-circle-fill"></i> Plex Webhook (Optional)</p>
                    <p class="text-white-50 small mb-3">Trigger a library refresh in Plex automatically whenever the Auto-Mover finishes moving a new series.</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small">Plex Host (e.g. http://plex.server:32400)</label>
                            <input type="text" class="form-control form-control-sm" name="plex_host" id="plex_host" placeholder="">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small">Plex Token</label>
                            <input type="text" class="form-control form-control-sm" name="plex_token" id="plex_token" placeholder="">
                        </div>
                    </div>
                    <div class="mb-3 text-end">
                        <button type="button" class="btn btn-sm btn-outline-info" id="verifyPlexBtn" onclick="verifyPlex()"><i class="bi bi-play-circle"></i> Verify Plex Connection</button>
                    </div>
                    <div id="plex-validation-msg" class="mb-3 text-center" style="display:none; font-weight:bold;"></div>
                </div>

                <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-outline-custom" onclick="nextStep(3)"><i class="bi bi-arrow-left"></i> Back</button>
                    <button type="submit" class="btn btn-success">Complete Setup <i class="bi bi-check-circle"></i></button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
    let dirCount = 0;

    function nextStep(step) {
        // Validation when moving forward (except going back or custom validation steps like step 3)
        let currentStepEl = document.querySelector('.step-container.active');
        if (currentStepEl && step > parseInt(currentStepEl.id.replace('step-', ''))) {
            const requiredInputs = currentStepEl.querySelectorAll('input[required]');
            let allValid = true;
            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    allValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (!allValid) {
                alert('Please fill out all required fields before proceeding.');
                return;
            }
        }

        document.querySelectorAll('.step-container').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.step-dot').forEach(el => el.classList.remove('active'));
        
        document.getElementById('step-' + step).classList.add('active');
        document.getElementById('dot-' + step).classList.add('active');

        if (step === 4) {
            const mainStorage = document.getElementById('input_main_storage_dir').value.trim();
            if (!mainStorage) {
                document.getElementById('step4_disabled').style.display = 'block';
                document.getElementById('step4_content').style.display = 'none';
            } else {
                document.getElementById('step4_disabled').style.display = 'none';
                document.getElementById('step4_content').style.display = 'block';
            }
        }
    }

    function fillInput(id, path) {
        const input = document.getElementById(id);
        if (input) {
            input.value = path;
            verifyDir(id.replace('input_', ''));
        }
    }

    function fillOrAddDir(path) {
        const inputs = document.querySelectorAll('input[name="additional_dirs[]"]');
        for (let i = 0; i < inputs.length; i++) {
            if (inputs[i].value.trim() === '') {
                inputs[i].value = path;
                verifyDir(inputs[i].id.replace('input_', ''));
                return;
            }
        }
        addAdditionalDirField(path);
    }

    function addAdditionalDirField(value = '') {
        const id = 'additional_' + dirCount++;
        const html = `
            <div class="dir-item" id="container_${id}">
                <input type="text" class="form-control" name="additional_dirs[]" value="${value}" placeholder="/mnt/usb/video" onblur="verifyDir('${id}')" id="input_${id}" list="availableMounts">
                <div class="validation-icon" id="icon_${id}">
                    <i class="bi bi-question-circle text-white-50"></i>
                </div>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeDirField('${id}')"><i class="bi bi-trash"></i></button>
            </div>
        `;
        document.getElementById('dir-list').insertAdjacentHTML('beforeend', html);
        if(value !== '') verifyDir(id);
    }

    function removeDirField(id) {
        document.getElementById('container_' + id).remove();
    }

    async function verifyDir(id) {
        const input = document.getElementById('input_' + id);
        const icon = document.getElementById('icon_' + id);
        const val = input.value.trim();
        
        if (val === '') {
            if (id === 'primary_dir') {
                icon.innerHTML = '<i class="bi bi-x-circle-fill text-danger-custom"></i>';
            } else {
                icon.innerHTML = '<i class="bi bi-question-circle text-white-50"></i>';
            }
            return false;
        }

        icon.innerHTML = '<div class="spinner-border spinner-border-sm text-info" role="status"></div>';
        
        try {
            const formData = new FormData();
            formData.append('action', 'verify_dir');
            formData.append('dir', val);
            
            const res = await fetch('setup_ajax.php', { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                icon.innerHTML = '<i class="bi bi-check-circle-fill text-success-custom"></i>';
                return true;
            } else {
                icon.innerHTML = '<i class="bi bi-x-circle-fill text-danger-custom" title="'+data.error+'"></i>';
                return false;
            }
        } catch (e) {
            icon.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-warning-custom"></i>';
            return false;
        }
    }

    async function validateDirsAndNext() {
        const inputs = document.querySelectorAll('input[id^="input_"]');
        let allValid = true;
        
        for (let input of inputs) {
            const id = input.id.replace('input_', '');
            const val = input.value.trim();
            if (val === '') {
                if (id === 'primary_dir') {
                    allValid = false;
                    input.classList.add('is-invalid');
                }
                continue;
            }
            
            const isValid = await verifyDir(id);
            if (!isValid) {
                allValid = false;
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-invalid');
            }
        }
        
        if (allValid) {
            nextStep(3);
        } else {
            alert('Please ensure all specified directories exist and are valid inside the container.');
        }
    }

    async function verifyJdAndNext() {
        // Just standard validation here since we decoupled the fetching
        const device = document.getElementById('myjd_device');
        if (!device || !device.value.trim()) {
            alert("Please provide or select a Device Name.");
            return;
        }
        nextStep(4);
    }

    async function fetchJdInstances() {
        const email = document.getElementById('jd_email').value.trim();
        const pwd = document.getElementById('jd_password').value;
        const msg = document.getElementById('jd-validation-msg');
        const btn = document.getElementById('fetchJdBtn');
        const wrapper = document.getElementById('myjd_device_wrapper');
        
        if (!email || !pwd) {
            msg.style.display = 'block';
            msg.className = 'mb-3 text-center text-danger-custom';
            msg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Please enter email and password first.';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Fetching...';
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
                msg.className = 'mb-3 text-center text-success-custom';
                
                if (data.devices && data.devices.length > 0) {
                    msg.innerHTML = '<i class="bi bi-check-circle"></i> Login successful! Select your instance below.';
                    let selectHtml = `<select class="form-control" id="myjd_device" name="myjd_device" onchange="document.getElementById('myjd_device_id').value = this.options[this.selectedIndex].getAttribute('data-id')" required>`;
                    selectHtml += `<option value="" data-id="" disabled selected>Select an instance...</option>`;
                    data.devices.forEach(dev => {
                        selectHtml += `<option value="${dev.name}" data-id="${dev.id}">${dev.name}</option>`;
                    });
                    selectHtml += `</select>`;
                    selectHtml += `<input type="hidden" id="myjd_device_id" name="myjd_device_id" value="">`;
                    wrapper.innerHTML = selectHtml;
                } else {
                    const errMsg = data.error ? ` (${data.error})` : '';
                    msg.innerHTML = `<i class="bi bi-exclamation-triangle"></i> Login successful, but no instances found${errMsg}! Please type it manually.`;
                    wrapper.innerHTML = `<input type="text" class="form-control" id="myjd_device" name="myjd_device" placeholder="e.g. JDownloader@anime-loads" required><input type="hidden" id="myjd_device_id" name="myjd_device_id" value="">`;
                }
            } else {
                msg.style.display = 'block';
                msg.className = 'mb-3 text-center text-danger-custom';
                msg.innerHTML = '<i class="bi bi-x-circle"></i> Login Failed: ' + data.error;
            }
        } catch (e) {
            msg.className = "mb-3 text-center text-danger-custom";
            msg.innerHTML = '<i class="bi bi-x-circle"></i> Network error during verification.';
            msg.style.display = 'block';
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-download"></i> Login & Fetch Instances';
    }

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
                msg.className = "mb-3 text-center text-success-custom";
                msg.innerHTML = '<i class="bi bi-check-circle"></i> Plex webhook successfully reached!';
                msg.style.display = 'block';
            } else {
                msg.className = "mb-3 text-center text-danger-custom";
                msg.innerHTML = '<i class="bi bi-x-circle"></i> ' + (data.error || "Connection failed.");
                msg.style.display = 'block';
            }
        } catch (e) {
            msg.className = "mb-3 text-center text-warning-custom";
            msg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Network error during verification.';
            msg.style.display = 'block';
        }
        
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-circle"></i> Verify Plex Connection';
    }

    window.onload = () => {
        <?php if (!empty($jd_download_dir)): ?>
            document.getElementById('input_primary_dir').value = <?= json_encode($jd_download_dir) ?>;
            verifyDir('primary_dir');
        <?php else: ?>
            document.getElementById('input_primary_dir').value = '/downloads';
            verifyDir('primary_dir');
        <?php endif; ?>
        
        <?php if (!empty($jd_extraction_dir)): ?>
            document.getElementById('input_extraction_dir').value = <?= json_encode($jd_extraction_dir) ?>;
            verifyDir('extraction_dir');
        <?php endif; ?>
        
        <?php
        if (!empty($additional_dirs)) {
            foreach($additional_dirs as $d) {
                echo "addAdditionalDirField(" . json_encode($d) . ");\n";
            }
        }
        ?>
    };
</script>

</body>
</html>
