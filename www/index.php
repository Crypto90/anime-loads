<?php
$configFile = '/config/config.json';
if (!file_exists($configFile)) {
    header('Location: setup.php');
    exit;
}
$config = json_decode(file_get_contents($configFile), true);
$base_dir = $config['base_dir'] ?? '/usr/src/app';

// Standardized Persistent File Paths
$jsonFile = file_exists('/config/ani.json') ? '/config/ani.json' : ($base_dir . '/ani.json');
$pausedFile = file_exists('/config/ani_paused.json') ? '/config/ani_paused.json' : ($base_dir . '/ani_paused.json');
$queueFile = is_dir('/config') ? '/config/queue.txt' : 'queue.txt';
$requestLogFile = is_dir('/config') ? '/config/requestlog.txt' : 'requestlog.txt';

error_reporting(E_ERROR | E_PARSE);
set_time_limit(0);
header('Access-Control-Allow-Origin: *');
session_start();

function liveExecuteCommand($cmd, $echoLive = false) {
    while (@ob_end_flush());
    $proc = popen("$cmd 2>&1 ; echo Exit status : $?", 'r');
    $live_output = "";
    $complete_output = "";
    while (!feof($proc)) {
        $live_output = fread($proc, 4096);
        $complete_output = $complete_output . $live_output;
        if ($echoLive) { echo "$live_output"; }
        @flush();
    }
    pclose($proc);
    preg_match('/[0-9]+$/', $complete_output, $matches);
    return array('exit_status' => intval($matches[0] ?? 0), 'output' => str_replace("Exit status : " . ($matches[0] ?? ''), '', $complete_output));
}

// --- Authentication & Session Validation ---
$user = $_POST['user'] ?? '';
$pass = $_POST['pass'] ?? '';
$userGET = $_GET['user'] ?? '';
$passGET = $_GET['pass'] ?? '';

$conf_user = $config['web_user'] ?? 'admin';
$conf_pass = $config['web_password'] ?? 'admin';

$is_authenticated = false;
if (($user === $conf_user && $pass === $conf_pass) || 
    ($userGET === $conf_user && $passGET === $conf_pass) || 
    (isset($_SESSION['user']) && $_SESSION['user'] === $conf_user && $_SESSION['pass'] === $conf_pass)) {
    $_SESSION['user'] = $conf_user;
    $_SESSION['pass'] = $conf_pass;
    $is_authenticated = true;
}

// Local Queue Processing Cron Exemption
if (isset($_GET['processqueue']) && $_GET['processqueue'] != '') {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocal = in_array($clientIp, ['127.0.0.1', '::1']);
    if (!$is_authenticated && !$isLocal) {
        http_response_code(403);
        die("Forbidden");
    }

    $pids = trim(shell_exec("ps ux | grep 'download_anime.py' | grep -v grep"));
    if ($pids == '') {
        $file = $queueFile;
        $contents = file_exists($file) ? file_get_contents($file) : '';
        $lines = array_values(array_filter(explode("\n", $contents), function($l) { return trim($l) !== ''; }));
        if (count($lines) > 0) {
            $firstLine = trim(array_shift($lines));
            file_put_contents($file, count($lines) > 0 ? implode("\n", $lines) . "\n" : "");
            
            if ($firstLine != '') {
                $firstLineParameterArray = explode(";", $firstLine);
                $animeTitel = $firstLineParameterArray[0] ?? '';
                $languageselect = $firstLineParameterArray[1] ?? 'german';
                $resolutionselect = $firstLineParameterArray[2] ?? '1080p';
                $forceAnimeResult = $firstLineParameterArray[3] ?? '0';
                $forceAnimeRelease = $firstLineParameterArray[4] ?? '0';
                $skipEpisodes = $firstLineParameterArray[5] ?? '0';
                $DRYRUN = $firstLineParameterArray[6] ?? '0';
                
                file_put_contents($base_dir . '/manualOutput.log', '');
                $cmd = 'cd ' . escapeshellarg($base_dir) . ' && PATH=/usr/local/bin:$PATH python3 -u download_anime.py ' . escapeshellarg($animeTitel) . ' ' . escapeshellarg($languageselect) . ' ' . escapeshellarg($resolutionselect) . ' ' . escapeshellarg($forceAnimeResult) . ' ' . escapeshellarg($forceAnimeRelease) . ' ' . escapeshellarg($skipEpisodes) . ' ' . escapeshellarg($DRYRUN) . ' > ' . escapeshellarg($base_dir . '/manualOutput.log') . ' 2>&1 &';
                liveExecuteCommand($cmd);
                
                if (strpos($animeTitel, 'http') !== false && strpos($animeTitel, '/media/') !== false) {
                    $animeTitel = explode("/media/", $animeTitel)[1];
                }
                
                $statusMsg = ($DRYRUN == '1') ? "DRY RUN (KEIN DOWNLOAD) Prozess gestartet [" . $animeTitel . "]... Es dauert etwa 60 Sekunden bis es weiter geht..." : "Prozess gestartet [" . $animeTitel . "]... Es dauert etwa 60 Sekunden bis es weiter geht...";
                file_put_contents($base_dir . "/manualOutput.log", $statusMsg);
            }
        }
    }
    die();
}

// Block unauthenticated access to actions and dashboard
if (!$is_authenticated) {
    if (isset($_POST['action']) || isset($_GET['action']) || isset($_GET['unmonitor']) || isset($_GET['downloader']) || isset($_GET['killrequest'])) {
        http_response_code(401);
        die("Unauthorized");
    }
    ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anime-Loads Downloader - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #0d1117; color: #e6edf3; font-family: 'Inter', -apple-system, sans-serif; }
        .panel { background-color: #161b22; border: 1px solid #30363d; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center vh-100" style="background-color: #0d1117;">
    <div class="panel" style="width: 400px;">
        <div class="p-4 border-bottom border-secondary bg-black text-center" style="border-radius: 12px 12px 0 0;">
            <h4 class="mb-0 text-white fw-bold"><i class="bi bi-cloud-arrow-down-fill text-primary me-2"></i>Anime-Loads</h4>
        </div>
        <div class="p-4">
            <form method="POST" action="index.php">
                <div class="mb-3">
                    <label class="mb-2">Username</label>
                    <input type="text" name="user" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="mb-2">Password</label>
                    <input type="password" name="pass" class="form-control" required>
                </div>
                <button type="submit" name="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

// --- Authenticated Actions & API Endpoints ---

if (isset($_GET['action']) && $_GET['action'] == "logout") {
	session_destroy();
	header("Refresh:0; url=index.php");
	die();
}

// Background Cover Scraper
if (isset($_POST['action']) && $_POST['action'] == "scrape_cover") {
    $urlName = basename($_POST['urlName'] ?? '');
    $url = 'https://www.anisearch.de/anime/index/page-1?char=all&text=' . urlencode($urlName) . '&smode=1&sort=date&order=asc&view=2&kev=7478ce6e';
    $options = array(
        CURLOPT_RETURNTRANSFER => 1, 
        CURLOPT_USERAGENT      => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36",  
        CURLOPT_FOLLOWLOCATION => true,   
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_REFERER => 'https://www.anisearch.de/',
    );
    $ch = curl_init($url); curl_setopt_array($ch, $options);
    $htmlContent = curl_exec($ch); curl_close($ch);
    
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($htmlContent);
    libxml_clear_errors();

    $detailsRedirectCoverURL = '';
    $gotElement = $doc->getElementById("details-cover");
    if ($gotElement != NULL) { $detailsRedirectCoverURL = $gotElement->getAttribute('src'); }
    
    $resultsCoverURL = '';
    $xpath = new DomXPath($doc);
    $images = [];
    foreach ($xpath->query("//th[contains(@class, 'showpop')]") as $img) {
        if ($img->hasAttribute('data-tooltip')) {
            preg_match('/< *img[^>]*src *= *["\']?([^"\']*)/i', $img->getAttribute('data-tooltip'), $match);
            if (isset($match[1])) $images[] = trim($match[1], '\'" ');
        }
    }
    if (isset($images[0]) && $images[0] != NULL) { $resultsCoverURL = $images[0]; }

    $coverToDisplay = ($detailsRedirectCoverURL != '') ? $detailsRedirectCoverURL : $resultsCoverURL;

    if ($coverToDisplay != '') {
        $file = fopen('./anime_cover/'.$urlName.'.png', 'w');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $coverToDisplay);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_FILE, $file);
        curl_exec($ch); curl_close($ch); fclose($file);
        echo "OK";
    } else {
        echo "Error";
    }
    die();
}

// Redownload Episode Manager Endpoint
if (isset($_POST['action']) && $_POST['action'] == 'redownload_episode') {
    $package = $_POST['package'];
    $ep = intval($_POST['ep']);
    $force = isset($_POST['force']) && $_POST['force'] == '1';

    $foundAnime = null;
    $targetFile = null;

    // Check active file
    if (file_exists($jsonFile)) {
        $data = json_decode(file_get_contents($jsonFile), true);
        if (isset($data['anime'])) {
            foreach ($data['anime'] as &$arr) {
                if ($arr['customPackage'] === $package) {
                    if (!in_array($ep, $arr['missing'])) { $arr['missing'][] = $ep; sort($arr['missing']); }
                    $foundAnime = $arr;
                    $targetFile = $jsonFile;
                    break;
                }
            }
            if ($targetFile) file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
        }
    }

    // Check paused file if not found
    if (!$foundAnime && file_exists($pausedFile)) {
        $dataP = json_decode(file_get_contents($pausedFile), true);
        if (isset($dataP['anime'])) {
            foreach ($dataP['anime'] as &$arr) {
                if ($arr['customPackage'] === $package) {
                    if (!in_array($ep, $arr['missing'])) { $arr['missing'][] = $ep; sort($arr['missing']); }
                    $foundAnime = $arr;
                    $targetFile = $pausedFile;
                    break;
                }
            }
            if ($targetFile === $pausedFile) file_put_contents($pausedFile, json_encode($dataP, JSON_PRETTY_PRINT));
        }
    }

    if ($force && $foundAnime) {
        $lang = strpos($package, 'japanese') !== false ? 'japanese' : 'german';
        $res = strpos($package, '720p') !== false ? '720p' : '1080p';
        $title = $foundAnime['name'];
        $titleEscaped = escapeshellarg($title);

        $pids = trim(shell_exec("ps ux | grep 'download_anime.py' | grep -v grep"));
        if ($pids != '') {
            $formDataLine = "$title;$lang;$res;0;0;0;0\n";
            file_put_contents($queueFile, $formDataLine, FILE_APPEND);
            file_put_contents($requestLogFile, $formDataLine, FILE_APPEND);
            die("QUEUED");
        } else {
            $logFile = $base_dir . '/manualOutput.log';
            file_put_contents($logFile, "FORCE DOWNLOAD TRIGGERED: Episode {$ep} of {$title}...\nEs dauert etwa 60 Sekunden bis es weiter geht...\n");
            $cmd = 'cd ' . escapeshellarg($base_dir) . ' && PATH=/usr/local/bin:$PATH python3 -u download_anime.py ' . $titleEscaped . ' ' . escapeshellarg($lang) . ' ' . escapeshellarg($res) . ' 0 0 0 0 >> ' . escapeshellarg($logFile) . ' 2>&1 &';
            liveExecuteCommand($cmd);
        }
    }
    die("OK");
}

// Toggle Pause Status Endpoint
if (isset($_POST['action']) && $_POST['action'] == 'toggle_pause') {
    $package = $_POST['package'];
    
    if (!file_exists($pausedFile)) file_put_contents($pausedFile, json_encode(['anime' => []]));
    
    $data = file_exists($jsonFile) ? (json_decode(file_get_contents($jsonFile), true) ?: ['anime' => []]) : ['anime' => []];
    $pausedData = file_exists($pausedFile) ? (json_decode(file_get_contents($pausedFile), true) ?: ['anime' => []]) : ['anime' => []];
    
    $found = false;
    // 1. Is it active? Move to paused.
    if (isset($data['anime'])) {
        foreach ($data['anime'] as $k => $arr) {
            if ($arr['customPackage'] === $package) {
                $arr['paused'] = true;
                $pausedData['anime'][] = $arr;
                unset($data['anime'][$k]);
                $data['anime'] = array_values($data['anime']);
                $found = true;
                break;
            }
        }
    }
    
    // 2. Is it paused? Move to active.
    if (!$found && isset($pausedData['anime'])) {
        foreach ($pausedData['anime'] as $k => $arr) {
            if ($arr['customPackage'] === $package) {
                unset($arr['paused']);
                $data['anime'][] = $arr;
                unset($pausedData['anime'][$k]);
                $pausedData['anime'] = array_values($pausedData['anime']);
                break;
            }
        }
    }
    
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    file_put_contents($pausedFile, json_encode($pausedData, JSON_PRETTY_PRINT));
    die("OK");
}

// --- JDownloader Native Proxy Endpoints ---
if (isset($_GET['action']) && $_GET['action'] == "jd_data") {
    header('Content-Type: application/json');
    $cmd = 'cd ' . escapeshellarg($base_dir) . ' && python3 jd_backend.py status 2>/dev/null';
    $output = shell_exec($cmd);
    
    if ($output && strpos($output, 'global') !== false) {
        echo $output;
    } else {
        echo json_encode(["global" => ["speed" => 0, "status" => "Offline", "hasUpdate" => false, "speedLimit" => 0], "packages" => []]);
    }
    die();
}

$jd_actions = ['jd_start', 'jd_pause', 'jd_stop', 'jd_delete', 'jd_reset', 'jd_force', 'jd_extract', 'jd_resume', 'jd_enable', 'jd_disable', 'jd_update', 'jd_speedlimit'];
if (isset($_POST['action']) && in_array($_POST['action'], $jd_actions)) {
    $action = $_POST['action'];
    $pyAction = "";
    $pyArg = "";
    
    if ($action == 'jd_start')  $pyAction = "start";
    if ($action == 'jd_pause')  $pyAction = "pause";
    if ($action == 'jd_stop')   $pyAction = "stop";
    if ($action == 'jd_update') $pyAction = "update";
    
    if (in_array($action, ['jd_delete', 'jd_reset', 'jd_force', 'jd_extract', 'jd_resume', 'jd_enable', 'jd_disable'])) {
        $cmdMap = [
            'jd_delete' => 'delete', 'jd_reset' => 'reset', 'jd_force' => 'force',
            'jd_extract' => 'extract', 'jd_resume' => 'resume', 'jd_enable' => 'enable', 'jd_disable' => 'disable'
        ];
        $pyAction = $cmdMap[$action];
        $pyArg = preg_replace('/[^0-9,]/', '', $_POST['id'] ?? '');
    }

    if ($action == 'jd_speedlimit') {
        $pyAction = "speedlimit";
        $pyArg = strval(intval($_POST['limit'] ?? 0));
    }
    
    if ($pyAction !== "") {
        $cmd = 'cd ' . escapeshellarg($base_dir) . ' && python3 jd_backend.py ' . escapeshellarg($pyAction) . ($pyArg !== '' ? ' ' . escapeshellarg($pyArg) : '');
        shell_exec($cmd);
    }
    die("OK");
}

// Backend Endpoint: Delete File or Folder from Cache/Unpacked
if (isset($_POST['action']) && $_POST['action'] == "delete_file_item") {
    $target = basename(trim($_POST['target'] ?? ''));
    $safeTarget = escapeshellarg($target);
    
    if (!empty($target)) {
        $searchDirs = escapeshellarg($config['jd_download_dir'] ?? '');
        if (!empty($config['jd_extraction_dir'])) {
            $searchDirs .= " " . escapeshellarg($config['jd_extraction_dir']);
        }
        $findCmd = "find $searchDirs -maxdepth 6 -name $safeTarget -print -quit 2>/dev/null";
        $foundPath = trim(shell_exec($findCmd));
        
        if ($foundPath) {
            exec("rm -rf " . escapeshellarg($foundPath) . " 2>&1", $output, $return_var);
            if ($return_var === 0) { echo "Deleted"; } 
            else { echo "Error: Permission denied (Code: $return_var)."; }
        } else {
            echo "Error: Item not found on disk.";
        }
    } else { echo "Error: Invalid target."; }
    die();
}

// Form Submission: New Download Request
if (isset($_POST['animeTitel']) && trim($_POST['animeTitel']) !== '') {
    $animeTitel = trim($_POST['animeTitel']);
    if (isset($_POST['ISHENTAI'])) {
        $animeTitel = "HENTAI_" . $animeTitel;
    }
    $languageselect = $_POST['languageselect'] ?? 'german';
    $resolutionselect = $_POST['resolutionselect'] ?? '1080p';
    $forceAnimeResult = !empty($_POST['forceAnimeResult']) ? intval($_POST['forceAnimeResult']) : 0;
    $forceAnimeRelease = !empty($_POST['forceAnimeRelease']) ? intval($_POST['forceAnimeRelease']) : 0;
    $skipEpisodes = !empty($_POST['skipEpisodes']) ? intval($_POST['skipEpisodes']) : 0;
    $DRYRUN = isset($_POST['DRYRUN']) ? 1 : 0;

    $pids = trim(shell_exec("ps ux | grep 'download_anime.py' | grep -v grep"));
    $formDataLine = "$animeTitel;$languageselect;$resolutionselect;$forceAnimeResult;$forceAnimeRelease;$skipEpisodes;$DRYRUN\n";
    if ($pids != '') {
        file_put_contents($queueFile, $formDataLine, FILE_APPEND);
        file_put_contents($requestLogFile, $formDataLine, FILE_APPEND);
    } else {
        file_put_contents($requestLogFile, $formDataLine, FILE_APPEND);
        file_put_contents($base_dir . '/manualOutput.log', '');
        $cmd = 'cd ' . escapeshellarg($base_dir) . ' && PATH=/usr/local/bin:$PATH python3 -u download_anime.py ' . escapeshellarg($animeTitel) . ' ' . escapeshellarg($languageselect) . ' ' . escapeshellarg($resolutionselect) . ' ' . escapeshellarg(strval($forceAnimeResult)) . ' ' . escapeshellarg(strval($forceAnimeRelease)) . ' ' . escapeshellarg(strval($skipEpisodes)) . ' ' . escapeshellarg(strval($DRYRUN)) . ' > ' . escapeshellarg($base_dir . '/manualOutput.log') . ' 2>&1 &';
        liveExecuteCommand($cmd);
        $displayTitle = (strpos($animeTitel, 'http') !== false && strpos($animeTitel, '/media/') !== false) ? explode("/media/", $animeTitel)[1] : $animeTitel;
        $statusMsg = ($DRYRUN === 1) ? "DRY RUN (KEIN DOWNLOAD) Prozess gestartet [" . $displayTitle . "]... Es dauert etwa 60 Sekunden bis es weiter geht..." : "Prozess gestartet [" . $displayTitle . "]... Es dauert etwa 60 Sekunden bis es weiter geht...";
        file_put_contents($base_dir . "/manualOutput.log", $statusMsg);
    }
}

if (isset($_GET['unmonitor']) && $_GET['unmonitor'] != '') {
    $unmonTarget = $_GET['unmonitor'];
    // Remove from active
    if (file_exists($jsonFile)) {
        $data = json_decode(file_get_contents($jsonFile), true);
        if ($data && isset($data['anime'])) {
            foreach ($data['anime'] as $k => $arr) {
                if ($arr["customPackage"] == $unmonTarget) { 
                    unset($data['anime'][$k]); 
                    $urlName = basename($arr['url']);
                    if (file_exists('./anime_cover/'.$urlName.'.png')) unlink('./anime_cover/'.$urlName.'.png');
                }
            }   
            $data['anime'] = array_values($data['anime']);
            file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
        }
    }
    
    // Remove from paused
    if (file_exists($pausedFile)) {
        $pData = json_decode(file_get_contents($pausedFile), true);
        if ($pData && isset($pData['anime'])) {
            foreach ($pData['anime'] as $k => $arr) {
                if ($arr["customPackage"] == $unmonTarget) { 
                    unset($pData['anime'][$k]); 
                    $urlName = basename($arr['url']);
                    if (file_exists('./anime_cover/'.$urlName.'.png')) unlink('./anime_cover/'.$urlName.'.png');
                }
            }   
            $pData['anime'] = array_values($pData['anime']);
            file_put_contents($pausedFile, json_encode($pData, JSON_PRETTY_PRINT));
        }
    }
    die();
}

if (isset($_GET['downloader']) && $_GET['downloader'] == '1') {
    shell_exec("pkill -9 -f 'download_anime.py'");
    header("Refresh:0; url=index.php");
    die();
}

if (isset($_GET['killrequest']) && $_GET['killrequest'] == '1') {
    shell_exec("pkill -9 -f 'download_anime.py'");
    file_put_contents($base_dir . "/manualOutput.log", "Prozess wurde manuell beendet.");
    header("Refresh:0; url=index.php");
    die();
}

function liveExecuteCommand($cmd, $echoLive = false) {
    while (@ob_end_flush());
    $proc = popen("$cmd 2>&1 ; echo Exit status : $?", 'r');
    $live_output = "";
    $complete_output = "";
    while (!feof($proc)) {
        $live_output = fread($proc, 4096);
        $complete_output = $complete_output . $live_output;
        if ($echoLive) { echo "$live_output"; }
        @flush();
    }
    pclose($proc);
    preg_match('/[0-9]+$/', $complete_output, $matches);
    return array ( 'exit_status' => intval($matches[0]), 'output' => str_replace("Exit status : " . $matches[0], '', $complete_output) );
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes >= 1024 && $i < 4; $i++) $bytes /= 1024;
    return round($bytes, 1) . ' ' . $units[$i];
}

function getDiskSpaceInfo($paths) {
    foreach ($paths as $path) {
        if (file_exists($path)) {
            $total = @disk_total_space($path);
            $free = @disk_free_space($path);
            if ($total > 0) {
                $used = $total - $free;
                $pct = round(($used / $total) * 100);
                $class = $pct > 90 ? 'bg-danger' : ($pct > 75 ? 'bg-warning' : 'bg-success');
                return ['total' => formatBytes($total), 'free' => formatBytes($free), 'used' => formatBytes($used), 'pct' => $pct, 'class' => $class];
            }
        }
    }
    return null;
}

$ssdInfo = null;
$hddInfo = null;

if (!empty($config['jd_download_dir']) && file_exists($config['jd_download_dir'])) {
    $ssdInfo = getDiskSpaceInfo([$config['jd_download_dir']]);
    if ($ssdInfo) $ssdInfo['label'] = 'Download Drive';
}

if (!empty($config['main_storage_dir']) && file_exists($config['main_storage_dir'])) {
    $hddInfo = getDiskSpaceInfo([$config['main_storage_dir']]);
    if ($hddInfo) $hddInfo['label'] = 'Final Storage';
} else if (!empty($config['jd_extraction_dir']) && file_exists($config['jd_extraction_dir'])) {
    $hddInfo = getDiskSpaceInfo([$config['jd_extraction_dir']]);
    if ($hddInfo) $hddInfo['label'] = 'Extraction Drive';
}

// Consolidate Active and Paused Library for Display
$monitoredNames = [];
$allAnime = [];

$jsonLib = file_exists($jsonFile) ? file_get_contents($jsonFile) : '{"anime":[]}';
$dataLib = json_decode($jsonLib, true);
if ($dataLib && isset($dataLib['anime'])) {
    foreach ($dataLib['anime'] as $anime) {
        $monitoredNames[] = strtolower(preg_replace('/[^a-zA-Z0-9]+/', ' ', $anime['name']));
        $allAnime[] = $anime;
    }
}

if (file_exists($pausedFile)) {
    $pLib = file_get_contents($pausedFile);
    $pData = json_decode($pLib, true);
    if ($pData && isset($pData['anime'])) {
        foreach ($pData['anime'] as $anime) {
            $monitoredNames[] = strtolower(preg_replace('/[^a-zA-Z0-9]+/', ' ', $anime['name']));
            $anime['paused'] = true; // Inject paused flag into runtime object
            $allAnime[] = $anime;
        }
    }
}
// Convert array to object format so original rendering code works without rewriting
$allAnimeObjects = json_decode(json_encode($allAnime));
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anime-Loads Downloader</title>
    
	<!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

	<style>
        /* --- Premium Obsidian Theme --- */
		body { background-color: #0d1117; color: #e6edf3; font-family: 'Inter', -apple-system, sans-serif; }
        
        .panel { background-color: #161b22; border: 1px solid #30363d; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .text-muted, .text-secondary { color: #a1aab5 !important; }
        .text-light { color: #e6edf3 !important; }
        .text-extraction { color: #d2a8ff !important; }
        .bg-extraction { background-color: #8957e5 !important; }

        .form-control, .form-select { background-color: #0d1117 !important; border: 1px solid #30363d; color: #e6edf3 !important; border-radius: 6px; transition: all 0.2s ease; }
        .form-control:focus, .form-select:focus { border-color: #58a6ff; box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.15); }
        
        label { font-weight: 500; font-size: 0.9rem; color: #a1aab5; }
        
        .btn-primary { background-color: #238636; border: 1px solid rgba(240,246,252,0.1); color: #fff; }
        .btn-primary:hover { background-color: #2ea043; border-color: rgba(240,246,252,0.1); color: #fff; }
        
        .btn-surface { background-color: #21262d; border: 1px solid rgba(240,246,252,0.1); color: #e6edf3; transition: all 0.2s ease; }
        .btn-surface:hover { background-color: #30363d; border-color: #8b949e; color: #ffffff; }
        .btn-surface-warning { background-color: #21262d; border: 1px solid rgba(210, 153, 34, 0.4); color: #e3b341; transition: all 0.2s ease; }
        .btn-surface-warning:hover { background-color: #d29922; color: #000; }
        .btn-surface-danger { background-color: #21262d; border: 1px solid rgba(248, 81, 73, 0.4); color: #ff7b72; transition: all 0.2s ease; }
        .btn-surface-danger:hover { background-color: #f85149; color: #fff; }
        .btn-surface-success { background-color: #21262d; border: 1px solid rgba(46, 160, 67, 0.4); color: #56d364; transition: all 0.2s ease; }
        .btn-surface-success:hover { background-color: #2ea043; color: #fff; }
        .btn-surface-info { background-color: #21262d; border: 1px solid rgba(88, 166, 255, 0.4); color: #79c0ff; transition: all 0.2s ease; }
        .btn-surface-info:hover { background-color: #388bfd; color: #fff; }
        .btn-surface-secondary { background-color: #161b22; border: 1px solid #30363d; color: #a1aab5; transition: all 0.2s ease; }
        .btn-surface-secondary:hover { background-color: #30363d; color: #fff; }

        @keyframes pulse-btn {
            0% { box-shadow: 0 0 0 0 rgba(210, 153, 34, 0.5); }
            70% { box-shadow: 0 0 0 6px rgba(210, 153, 34, 0); }
            100% { box-shadow: 0 0 0 0 rgba(210, 153, 34, 0); }
        }
        .pulse-animation { animation: pulse-btn 2s infinite; }

        /* macOS Style Terminal Window */
        .mac-window { background: #010409; border: 1px solid #30363d; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
        .mac-header { background: #161b22; padding: 10px 12px; display: flex; gap: 8px; border-bottom: 1px solid #30363d; }
        .mac-dot { width: 12px; height: 12px; border-radius: 50%; }
        .mac-close { background: #ff5f56; }
        .mac-min { background: #ffbd2e; }
        .mac-max { background: #27c93f; }
        .mac-body { padding: 14px; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: #56d364; height: 280px; overflow-y: auto; margin: 0; white-space: pre-wrap; }

        .file-panel { background-color: #010409; border: 1px solid #30363d; border-radius: 8px; padding: 10px; height: 220px; overflow-y: auto; }
        .file-item { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 8px 12px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s ease; }
        .file-item:last-child { margin-bottom: 0; }
        .file-item:hover { border-color: #58a6ff; background: #1c2128; }
        .file-name { font-size: 13px; font-weight: 500; color: #e6edf3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 12px; }
        .file-meta { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
        .file-size { font-size: 10px; font-weight: 600; color: #a1aab5; background: #010409; padding: 4px 8px; border-radius: 6px; border: 1px solid #30363d; }
        
        .btn-delete-file { color: #ff7b72; border-color: rgba(248,81,73,0.3); background: rgba(248,81,73,0.1); padding: 2px 8px; border-radius: 6px; transition: all 0.2s; margin-left: 4px; }
        .btn-delete-file:hover { background: #f85149; color: #fff; border-color: #f85149; }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0d1117; }
        ::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #484f58; }

        /* Dynamic Grid Layout */
        .anime-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 380px), 1fr)); gap: 1.5rem; }
        .anime-grid-item { width: 100%; transition: opacity 0.2s; }
        
        /* Premium Anime Cards (Dynamic Height Fixes) */
        .premium-card { display: flex; background: #161b22; border: 1px solid #30363d; border-radius: 12px; overflow: hidden; min-height: 240px; height: 100%; transition: all 0.3s ease; }
        .premium-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.3); border-color: #58a6ff; }
        
        .premium-card.status-completed { border-color: #2ea043; box-shadow: inset 0 0 20px rgba(46,160,67,0.05); }
        .premium-card.status-error { border-color: #f85149; }
        .premium-card.status-paused { opacity: 0.6; border-color: #d29922; filter: grayscale(0.6); }
        .premium-card.status-paused:hover { opacity: 1; filter: grayscale(0); }
        
        .card-img-wrapper { width: 145px; flex-shrink: 0; background: #010409; position: relative; } /* Reduced width for text room */
        .card-img-wrapper img { width: 100%; height: 100%; object-fit: cover; cursor: pointer; transition: opacity 0.2s; }
        .card-img-wrapper img:hover { opacity: 0.7; }
        
        .card-info { padding: 14px; display: flex; flex-direction: column; flex-grow: 1; overflow: hidden; }
        .anime-title { font-size: 1.05rem; font-weight: 700; color: #e6edf3; line-height: 1.3; margin-bottom: 8px; cursor: pointer; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; flex-shrink: 0; }
        .anime-title:hover { color: #58a6ff; }
        .premium-badge { background: rgba(88, 166, 255, 0.1); color: #79c0ff; border: 1px solid rgba(88, 166, 255, 0.2); padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; display: inline-block; margin-bottom: 12px; align-self: flex-start; flex-shrink: 0; }
        .meta-data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 8px; margin-bottom: 8px; flex-shrink: 0; }
        .meta-text { font-size: 12px; color: #a1aab5; line-height: 1.4; flex-shrink: 0; }
        
        /* Fixed Action Button Layout */
        .card-bottom-actions { margin-top: auto; display: flex; justify-content: space-between; align-items: center; gap: 8px; padding-top: 12px; flex-shrink: 0; }
        .card-action-btn { font-size: 12px; font-weight: 500; padding: 5px 10px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s; }
        .card-action-btn i { font-size: 14px; }
        .flag-icon { width: 28px; opacity: 0.8; border-radius: 3px; }

        /* Quick Add Grid */
        .quickadd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }
        .quickadd-card { background: #161b22; border: 1px solid #30363d; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s; }
        .quickadd-card:hover { transform: translateY(-4px); border-color: #58a6ff; }
        .quickadd-img { width: 100%; height: 200px; object-fit: cover; }
        .quickadd-info { padding: 10px; flex-grow: 1; display: flex; flex-direction: column; }
        .quickadd-title { font-size: 12px; font-weight: 700; color: #e6edf3; margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 32px; }
        
        .toplist-wrapper { position: relative; max-width: 900px; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .nav-overlay-btn { position: absolute; top: 0; width: 15%; height: 100%; cursor: pointer; opacity: 0; transition: all 0.3s; z-index: 10; display: flex; align-items: center; justify-content: center; }
        .nav-overlay-btn:hover { opacity: 1; background: rgba(255,255,255,0.1); backdrop-filter: blur(2px); }
        .nav-overlay-left { left: 0; }
        .nav-overlay-right { right: 0; }
        .nav-arrow { font-size: 3rem; color: #fff; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.8)); }

        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 1055; }
	</style>

	<script>
        const monitoredAnime = <?php echo json_encode($monitoredNames); ?>;

		function humanFileSize(bytes, si=false, dp=1) {
		  const thresh = si ? 1000 : 1024;
		  if (Math.abs(bytes) < thresh) return bytes + ' B';
		  const units = si ? ['kB', 'MB', 'GB', 'TB'] : ['KiB', 'MiB', 'GiB', 'TiB'];
		  let u = -1; const r = 10**dp;
		  do { bytes /= thresh; ++u; } while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1);
		  return bytes.toFixed(dp) + ' ' + units[u];
		}
		
		function popupwindow(url, title, w, h) {
		  var left = (screen.width/2)-(w/2);
		  var top = (screen.height/2)-(h/2);
		  $("body").append("<div id='dark-overlay' style='position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: #000; opacity: 0.85; z-index: 1040;'></div>");
		  var windoww = window.open(url, title, 'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no, width='+w+', height='+h+', top='+top+', left='+left);
		  window.addEventListener('focus', () => {
			 if (!windoww.closed) windoww.close();
			 $('#dark-overlay').remove();
		  });
		  return windoww;
		} 

        function showToast(title, message, isError = false) {
            let color = isError ? 'text-danger' : 'text-success';
            let icon = isError ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill';
            let html = `
            <div class="toast align-items-center text-bg-dark border-secondary mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-black border-secondary">
                    <i class="bi ${icon} ${color} me-2"></i>
                    <strong class="me-auto text-light">${title}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body" style="font-size: 13px;">${message}</div>
            </div>`;
            let $toast = $(html).appendTo('#toast-container');
            let bsToast = new bootstrap.Toast($toast[0], {delay: 4000});
            bsToast.show();
            $toast.on('hidden.bs.toast', function () { $(this).remove(); });
        }

        // --- Episode Manager Logic ---
        function openEpisodeManager(animeStr) {
            let anime = JSON.parse(animeStr);
            $('#episodeModalTitle').text(`Manage Episodes: ${anime.name}`);
            
            let eps = anime.episodes > 0 ? anime.episodes : (anime.maxEpisodes === 1337 ? 1 : anime.maxEpisodes);
            if(!eps) eps = 1;

            let html = '<div class="d-flex flex-wrap gap-2">';
            for(let i=1; i<=eps; i++) {
                let isMissing = anime.missing.includes(i);
                let btnClass = isMissing ? 'btn-warning text-dark' : 'btn-success';
                let icon = isMissing ? 'bi-hourglass-split' : 'bi-check2';
                let title = isMissing ? 'Queued / Missing' : 'Completed (Click to redownload)';

                html += `<button class="btn btn-sm ${btnClass} fw-bold" style="width: 52px; font-size: 12px;" title="${title}" onclick="promptRedownload('${anime.customPackage}', ${i}, ${isMissing})">
                            <i class="bi ${icon} d-block mb-1 fs-6"></i> ${i}
                         </button>`;
            }
            html += '</div>';
            $('#episodeModalBody').html(html);
            let epModal = new bootstrap.Modal(document.getElementById('episodeModal'));
            epModal.show();
        }

        function promptRedownload(package, ep, isMissing) {
            if(isMissing) return; 
            let html = `
                <div class="text-center py-4">
                    <i class="bi bi-cloud-arrow-down-fill text-primary" style="font-size: 3.5rem;"></i>
                    <h5 class="text-light mt-3 fw-bold">Redownload Episode ${ep}?</h5>
                    <p class="text-muted" style="font-size: 13px;">How would you like to proceed?</p>
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <button class="btn btn-surface-warning fw-bold" onclick="submitRedownload('${package}', ${ep}, 0)"><i class="bi bi-clock-history me-2"></i>Queue for Next Run</button>
                        <button class="btn btn-primary fw-bold" onclick="submitRedownload('${package}', ${ep}, 1)"><i class="bi bi-lightning-charge-fill me-2"></i>Force Download NOW</button>
                    </div>
                </div>
            `;
            $('#episodeModalBody').html(html);
        }

        function submitRedownload(pkg, ep, force) {
            $.post('index.php', { action: 'redownload_episode', package: pkg, ep: ep, force: force ? '1' : '0' }, function(res) {
                if(res.trim() === 'OK') {
                    showToast("Success", `Episode ${ep} queued for redownload.`);
                    if(force) showToast("Forced Download", "Background process started!", false);
                    bootstrap.Modal.getInstance(document.getElementById('episodeModal')).hide();
                    setTimeout(() => location.reload(), 1500);
                } else if(res.trim() === 'QUEUED') {
                    showToast("Warning", "A download process is already running. The force request has been placed in the queue.", true);
                    bootstrap.Modal.getInstance(document.getElementById('episodeModal')).hide();
                    setTimeout(() => location.reload(), 2500);
                } else {
                    showToast("Error", res, true);
                }
            });
        }

        // --- Seasonal AniList GraphQL Quick Add Logic ---
        let seasonsList = ['WINTER', 'SPRING', 'SUMMER', 'FALL'];
        let seasonLabels = ['Winter', 'Spring', 'Summer', 'Fall'];
        
        let qaYear = new Date().getFullYear();
        let qaSeasonIdx = Math.floor(new Date().getMonth() / 3);

        function normalizeTitle(t) {
            if(!t) return '';
            return t.toLowerCase().replace(/[^a-z0-9]/g, ' ').replace(/\s+/g, ' ').trim();
        }

        function isAnimeMonitored(romajiTitle, englishTitle) {
            let jt = normalizeTitle(romajiTitle);
            let jte = normalizeTitle(englishTitle);
            for(let title of monitoredAnime) {
                if(!title) continue;
                if(jt && (jt === title || (jt.length > 8 && title.includes(jt)) || (title.length > 8 && jt.includes(title)))) return true;
                if(jte && (jte === title || (jte.length > 8 && title.includes(jte)) || (title.length > 8 && jte.includes(title)))) return true;
            }
            return false;
        }

        function quickAddAnime(titleJs, id) {
            let lang = $(`#qa-lang-${id}`).val();
            let res = $(`#qa-res-${id}`).val();
            $('input[name="animeTitel"]').val(titleJs);
            $('select[name="languageselect"]').val(lang);
            $('select[name="resolutionselect"]').val(res);
            $('#DRYRUN').prop('checked', false);
            window.scrollTo({ top: 0, behavior: 'smooth' });
            $('input[name="animeTitel"]').css('background-color', 'rgba(46, 160, 67, 0.4)');
            setTimeout(() => { $('#mainRequestForm').submit(); }, 600);
        }

        function changeQASeason(dir) {
            $('.season-btn').prop('disabled', true);
            qaSeasonIdx += dir;
            if (qaSeasonIdx > 3) { qaSeasonIdx = 0; qaYear++; } 
            else if (qaSeasonIdx < 0) { qaSeasonIdx = 3; qaYear--; }
            loadSeasonalAnime();
        }

        function renderSeasonalAnime(data) {
            let html = '';
            data.forEach((anime, idx) => {
                let titleRaw = anime.title.romaji || anime.title.english || 'Unknown Title';
                let titleHtml = titleRaw.replace(/"/g, '&quot;');
                let titleJs = titleRaw.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                
                let img = anime.coverImage?.large || '';
                let isAdded = isAnimeMonitored(anime.title.romaji, anime.title.english);
                
                let btnHtml = isAdded 
                    ? `<button class="btn btn-surface-secondary btn-sm w-100 py-1 mt-auto" style="font-size: 11px; font-weight: bold;" disabled><i class="bi bi-check2-circle me-1"></i> Added</button>`
                    : `<button class="btn btn-surface-success btn-sm w-100 py-1 mt-auto" style="font-size: 11px; font-weight: bold;" onclick="quickAddAnime('${titleJs}', ${idx})"><i class="bi bi-plus-circle me-1"></i> Add</button>`;

                html += `
                <div class="quickadd-card">
                    <img src="${img}" class="quickadd-img" alt="Cover">
                    <div class="quickadd-info">
                        <div class="quickadd-title" title="${titleHtml}">${titleHtml}</div>
                        <select id="qa-lang-${idx}" class="form-select form-select-sm mb-1" style="font-size: 11px; padding: 2px 6px;">
                            <option value="japanese">Japanisch</option>
                            <option value="german">Deutsch</option>
                        </select>
                        <select id="qa-res-${idx}" class="form-select form-select-sm mb-2" style="font-size: 11px; padding: 2px 6px;">
                            <option value="1080p">1080p</option>
                            <option value="720p">720p</option>
                        </select>
                        ${btnHtml}
                    </div>
                </div>`;
            });
            $('#quickadd-container').html(html);
        }

        function loadSeasonalAnime() {
            let sStr = seasonsList[qaSeasonIdx];
            let cacheKey = `anilist_${qaYear}_${sStr}`;
            
            $('#seasonTitle').text(`${seasonLabels[qaSeasonIdx]} ${qaYear}`);
            $('#quickadd-container').html('<div class="text-muted w-100 text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading anime...</div>');
            $('.season-btn').prop('disabled', true);

            let cached = localStorage.getItem(cacheKey);
            let now = Date.now();

            if (cached) {
                cached = JSON.parse(cached);
                if (now - cached.timestamp < 86400000) {
                    renderSeasonalAnime(cached.data);
                    $('.season-btn').prop('disabled', false);
                    return;
                }
            }

            let query = `
            query ($season: MediaSeason, $seasonYear: Int) {
              Page(page: 1, perPage: 8) {
                media(season: $season, seasonYear: $seasonYear, type: ANIME, sort: POPULARITY_DESC) {
                  title {
                    romaji
                    english
                  }
                  coverImage {
                    large
                  }
                }
              }
            }`;

            $.ajax({
                url: 'https://graphql.anilist.co',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                data: JSON.stringify({
                    query: query,
                    variables: { season: sStr, seasonYear: qaYear }
                }),
                success: function(res) {
                    if(res && res.data && res.data.Page && res.data.Page.media) {
                        let top8 = res.data.Page.media;
                        localStorage.setItem(cacheKey, JSON.stringify({timestamp: now, data: top8}));
                        renderSeasonalAnime(top8);
                    }
                },
                error: function(jqXHR) {
                    if (cached && cached.data) {
                        renderSeasonalAnime(cached.data);
                        showToast("API Offline", "Loaded season from local cache because API is down.", true);
                    } else {
                        let errStr = "Could not load recommendations API.";
                        if (jqXHR.status === 429) errStr = "API Rate Limit reached. Please wait a moment.";
                        $('#quickadd-container').html(`<div class="text-danger w-100 text-center py-3"><i class="bi bi-exclamation-triangle-fill me-2"></i>${errStr}</div>`);
                    }
                },
                complete: function() {
                    $('.season-btn').prop('disabled', false);
                }
            });
        }

        // --- NATIVE JDOWNLOADER API CONTROLLER ---
        const JDController = {
            polling: null,
            selectedIds: new Set(),
            
            init: function() {
                this.fetchData();
                this.polling = setInterval(() => this.fetchData(), 6000);
            },
            
            fetchData: function() {
                $.get('index.php?action=jd_data', (res) => {
                    if (res && res.global) this.renderGlobal(res.global);
                    if (res && res.packages) this.renderPackages(res.packages);
                }, 'json').fail(() => {
                    $('#jd-global-status').html('<i class="bi bi-x-circle me-1"></i>Offline').removeClass('bg-success text-dark').addClass('bg-danger text-white');
                    $('#jd-global-speed').text('0 B/s');
                });
            },
            
            renderGlobal: function(data) {
                $('#jd-global-speed').text(humanFileSize(data.speed) + '/s');
                
                let $status = $('#jd-global-status');
                if (data.status === 'Running') {
                    $status.html('<i class="bi bi-activity me-1"></i>Online').removeClass('bg-warning bg-danger text-white').addClass('bg-success text-dark');
                } else {
                    $status.html('<i class="bi bi-pause-circle me-1"></i>Paused').removeClass('bg-success bg-danger text-dark').addClass('bg-warning text-dark');
                }

                let $updateBtn = $('#jd-update-btn');
                if (data.hasUpdate === true) {
                    $updateBtn.removeClass('btn-surface').addClass('btn-surface-warning pulse-animation').attr('title', 'Update Available! Click to install.');
                    $updateBtn.html('<i class="bi bi-cloud-arrow-down-fill me-1"></i><span class="fw-bold" style="font-size: 12px;">Update Ready</span>');
                } else {
                    $updateBtn.removeClass('btn-surface-warning pulse-animation').addClass('btn-surface').attr('title', 'Check / Trigger Update');
                    $updateBtn.html('<i class="bi bi-cloud-arrow-down text-info fs-6"></i>');
                }

                if (!$('#jd-speed-input').is(':focus')) {
                    if (data.speedLimit > 0) {
                        $('#jd-speed-input').val(Math.round(data.speedLimit / 1024));
                    } else {
                        $('#jd-speed-input').val('');
                    }
                }
            },
            
            renderPackages: function(packages) {
                let html = '';
                packages.forEach(p => {
                    let percent = (p.bytesLoaded / p.bytesTotal) * 100;
                    if (isNaN(percent)) percent = 0;
                    
                    let statusStr = p.status || "";
                    let isExtracting = false;
                    let extPercent = 0;

                    let extMatch = statusStr.match(/(?:Extracting|Entpacken|Extraction).*?(\d+(?:\.\d+)?)\s*%/i);
                    let isSuccess = statusStr.match(/(?:ok|successful|erfolgreich|finished|beendet)/i);

                    if (extMatch && !isSuccess) {
                        isExtracting = true;
                        extPercent = parseFloat(extMatch[1]);
                    } else if (!isSuccess && (statusStr.toLowerCase().includes('extract') || statusStr.toLowerCase().includes('entpacken'))) {
                        isExtracting = true;
                        extPercent = 100; 
                    }
                    
                    let statusColor = p.finished ? 'text-success' : (p.running ? 'text-info' : 'text-warning');
                    let barClass = p.finished ? 'bg-success' : (p.running ? 'bg-primary progress-bar-striped progress-bar-animated' : 'bg-secondary');
                    
                    if (statusStr.toLowerCase().includes('error') || statusStr.toLowerCase().includes('fehler')) {
                        statusColor = 'text-danger fw-bold';
                        barClass = 'bg-danger';
                    } else if (isExtracting) {
                        statusColor = 'text-extraction fw-bold';
                        barClass = 'bg-extraction progress-bar-striped progress-bar-animated';
                        percent = extPercent > 0 ? extPercent : 100;
                    }

                    let isChecked = this.selectedIds.has(String(p.uuid)) ? 'checked' : '';
                    
                    html += `
                    <tr style="border-bottom: 1px solid #30363d;">
                        <td class="ps-4 align-middle"><input class="form-check-input jd-pkg-cb" type="checkbox" value="${p.uuid}" ${isChecked} onchange="JDController.toggleSelection(this)"></td>
                        <td class="text-truncate fw-medium" style="max-width: 200px;" title="${p.name}">
                            <i class="bi bi-folder-symlink me-2 text-warning"></i>${p.name}
                        </td>
                        <td class="${statusColor} fw-medium" style="max-width: 180px; white-space: normal; line-height: 1.2;">${statusStr || (p.finished ? 'Finished' : (p.running ? 'Downloading' : 'Queued'))}</td>
                        <td class="align-middle">
                            <div class="progress" style="height: 12px; background-color: #010409; border: 1px solid #30363d; border-radius: 6px;">
                                <div class="progress-bar ${barClass}" style="width: ${percent}%"></div>
                            </div>
                        </td>
                        <td class="text-muted" style="font-size: 12px;">${humanFileSize(p.bytesLoaded)} / ${humanFileSize(p.bytesTotal)}</td>
                        <td class="fw-bold" style="color: #e6edf3;">${p.speed > 0 ? humanFileSize(p.speed)+'/s' : '-'}</td>
                        <td class="text-secondary">${this.formatETA(p.eta)}</td>
                        <td class="text-end pe-4" style="min-width: 150px;">
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-surface-success py-1 px-2" onclick="JDController.packageAction('jd_resume', '${p.uuid}')" title="Resume / Retry"><i class="bi bi-play-fill"></i></button>
                                <button class="btn btn-sm btn-surface-info py-1 px-2" onclick="JDController.packageAction('jd_extract', '${p.uuid}')" title="Force Extraction"><i class="bi bi-file-earmark-zip"></i></button>
                                <button class="btn btn-sm btn-surface-danger py-1 px-2" onclick="JDController.packageAction('jd_delete', '${p.uuid}', 'Are you sure you want to delete this package from JDownloader?')" title="Delete Package"><i class="bi bi-trash3"></i></button>
                            </div>
                        </td>
                    </tr>`;
                });
                if(html === '') html = '<tr><td colspan="8" class="text-center text-muted py-4">No active downloads in JDownloader</td></tr>';
                $('#jd-downloads-list').html(html);
                this.updateBulkButton();
            },
            
            formatETA: function(seconds) {
                if (!seconds || seconds <= 0 || !isFinite(seconds)) return '-';
                let h = Math.floor(seconds / 3600);
                let m = Math.floor((seconds % 3600) / 60);
                let s = Math.floor(seconds % 60);
                if (h > 0) return `${h}h ${m}m`;
                return `${m}m ${s}s`;
            },
            
            start: function() { $.post('index.php', { action: 'jd_start' }, () => this.fetchData()); },
            pause: function() { $.post('index.php', { action: 'jd_pause' }, () => this.fetchData()); },
            stop: function() { $.post('index.php', { action: 'jd_stop' }, () => this.fetchData()); },
            update: function() { 
                if(confirm('Trigger MyJDownloader Update and Restart Core?')) {
                    $.post('index.php', { action: 'jd_update' }, () => this.fetchData());
                }
            },
            
            setSpeedLimit: function() {
                let limitKB = parseInt($('#jd-speed-input').val()) || 0;
                let limitBytes = limitKB * 1024;
                $.post('index.php', { action: 'jd_speedlimit', limit: limitBytes }, () => {
                    showToast('Speed Limit Set', `Limit changed to ${limitKB > 0 ? limitKB + ' KB/s' : 'Unlimited'}`);
                    this.fetchData();
                });
            },
            
            packageAction: function(action, id, promptText = null) {
                if (promptText && !confirm(promptText)) return;
                $.post('index.php', { action: action, id: id }, () => {
                    setTimeout(() => this.fetchData(), 800);
                });
            },

            // Bulk Actions
            toggleSelection: function(checkbox) {
                if(checkbox.checked) this.selectedIds.add(checkbox.value);
                else this.selectedIds.delete(checkbox.value);
                this.updateBulkButton();
            },
            selectAll: function(checkbox) {
                if(checkbox.checked) {
                    $('.jd-pkg-cb').each((i, el) => this.selectedIds.add(el.value));
                } else {
                    this.selectedIds.clear();
                }
                $('.jd-pkg-cb').prop('checked', checkbox.checked);
                this.updateBulkButton();
            },
            updateBulkButton: function() {
                $('#jd-bulk-btn').prop('disabled', this.selectedIds.size === 0);
            },
            bulkAction: function(action) {
                if(this.selectedIds.size === 0) return;
                if(action === 'jd_delete' && !confirm(`Delete ${this.selectedIds.size} selected packages?`)) return;
                
                let ids = Array.from(this.selectedIds).join(',');
                $.post('index.php', { action: action, id: ids }, () => {
                    this.selectedIds.clear();
                    $('#jd-select-all').prop('checked', false);
                    showToast('Bulk Action', `Command sent to ${ids.split(',').length} packages.`);
                    setTimeout(() => this.fetchData(), 800);
                });
            }
        };

		var lastDataPrinted = '';
		var lastDataPrinted2 = '';
        var lastActiveCache = '';
        var lastFinishedUnpacked = '';
        var exitHandled = false;
		
        $(document).ready(function(){
            
            JDController.init();
            
            // Automatically initialize the recommendation grid using the new fast AniList GraphQL API
            loadSeasonalAnime();
			
            // --- Library Search ---
            $('#librarySearch').on('input', function() {
                let term = $(this).val().toLowerCase();
                $('.anime-grid-item').each(function() {
                    let text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(term) > -1);
                });
            });

            // Toggle Pause Status
            $(document).on('click', '.toggle-pause-btn', function(e) {
                e.preventDefault();
                let pkg = $(this).data('pkg');
                $.post('index.php', { action: 'toggle_pause', package: pkg }, function(res) {
                    if(res.trim() === 'OK') {
                        setTimeout(() => location.reload(), 200);
                    }
                });
            });

            // Unmonitor Library Item
			$(document).on('click', '.unmonitor-btn', function(e) {
                e.preventDefault();
				$.get($(this).attr("data"));
				$(this).closest(".anime-grid-item").fadeOut(300, function() { $(this).remove(); });
                showToast("Unmonitored", "Anime removed from ani.json successfully.");
			});
			
            // Delete Physical File / Folder
            $(document).on('click', '.btn-delete-file', function(e) {
                e.preventDefault();
                var itemName = $(this).data('item');
                if (confirm("WARNING: Are you sure you want to permanently delete:\n\n" + itemName + "\n\nThis cannot be undone!")) {
                    var $btn = $(this);
                    $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>').prop('disabled', true);
                    
                    $.post('index.php', { action: 'delete_file_item', target: itemName }, function(response) {
                        var res = response.trim();
                        if (res === "Deleted") {
                            $btn.closest('.file-item').fadeOut(300, function() { $(this).remove(); });
                            showToast("Deleted", `${itemName} permanently deleted.`);
                        } else {
                            showToast("Error", res, true);
                            $btn.html('<i class="bi bi-trash3"></i>').prop('disabled', false);
                        }
                    }).fail(function() {
                        showToast("Error", "Network or server error during deletion.", true);
                        $btn.html('<i class="bi bi-trash3"></i>').prop('disabled', false);
                    });
                }
            });

            // Popup Anime Details
			$(document).on('click', '.anime-title, .card-img-wrapper img', function() {
				popupwindow($(this).attr("data") + '#description', $(this).text(), 770, 430);
			});
            
            // Re-add Button Handler (For failed Log Entries)
            $(document).on('click', '.readd-btn', function(e) {
                e.preventDefault();
                var titleText = $(this).data('title');
                
                var $input = $('input[name="animeTitel"]');
                $input.val(titleText);
                $input.css('transition', 'none');
                $input.css('background-color', 'rgba(227,179,65,0.3)');
                setTimeout(() => {
                    $input.css('transition', 'all 0.5s ease');
                    $input.css('background-color', '');
                }, 50);
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
			
            // Background Polling Handlers (Smart Throttle)
            let pollSpeed = 500;
		    setInterval(function(){
		    	$.get('/echo_the_content.php?file=1', function(data) {
					if (lastDataPrinted != data) {
						lastDataPrinted = data;
						var cleanedData = data.replace(/\[0m/g, '').replace(/\[33m/g, '');
                        if (cleanedData.indexOf('Anime wurde hinzugef') != -1) {
		        			$("#manualOutput").html('<span style="color: #56d364;">Anime wurde hinzugefügt.</span>');
		        		} else {
                            $("#manualOutput").html(cleanedData);
                        }
		        		$('#manualOutput').scrollTop($('#manualOutput')[0].scrollHeight);
		        		
		        		if (cleanedData.indexOf('Exit') !== -1) {
                            if (!exitHandled) {
                                exitHandled = true;
                                setTimeout(function() {
                                    $.get(window.location.href, function(htmlData) {
                                        var newLibrary = $(htmlData).find('#anijson').html();
                                        if (newLibrary) $('#anijson').html(newLibrary);
                                    });
                                }, 3000);
                            }
		        		} else { exitHandled = false; }
				   }
				});
		    }, pollSpeed);
		    
		    setInterval(function(){
		    	$.get('/echo_the_content.php?file=10', function(data) {
					if (lastDataPrinted2 != data) {
						lastDataPrinted2 = data;
						data = data.replace(/Ist JDownloader gestartet\?/g, '');
						const lines = data.split('\n');
						const colored = lines.map(line => {
						  if (/error/i.test(line) || /wrong/i.test(line)) return `<span style="color: #f85149;">${line}</span>`;
						  if (/success/i.test(line) || /correct/i.test(line) || /\[download\]/i.test(line)) return `<span style="color: #56d364;">${line}</span>`;
						  if (/\[info\]/i.test(line)) return `<span style="color: #e3b341;">${line}</span>`;
						  return line;
						});
						$("#manualOutput2").html(colored.join('\n'));
						$('#manualOutput2').scrollTop($('#manualOutput2')[0].scrollHeight);
				   	}
				});
		    }, pollSpeed);
		    
            function stripHtml(html) {
                var tmp = document.createElement("DIV");
                tmp.innerHTML = html;
                return tmp.textContent || tmp.innerText || "";
            }

            function parseFileLogLine(line) {
                var rawLine = line.replace(/<br \/>/g, '').trim();
                if (!rawLine) return null;
                var parts = rawLine.split(' -- ');
                if (parts.length < 2) return null;
                var rawSize = parts[0].trim();
                var rawName = parts.slice(1).join(' -- ').trim();
                var isFolder = rawName.endsWith(" tv") || rawName.endsWith(" movie");
                var isArchive = rawName.indexOf('.rar') !== -1 || rawName.indexOf('.part') !== -1 || rawName.indexOf('.zip') !== -1;
                return { name: rawName, size: isFolder ? "" : humanFileSize(rawSize), icon: isFolder ? 'bi-folder-fill text-warning' : (isArchive ? 'bi-file-zip-fill text-danger' : 'bi-file-play-fill text-success') };
            }

            function fetchFileLogs() {
                $.get('/echo_the_content.php?file=7', function(data) {
                    if(data !== lastActiveCache) {
                        lastActiveCache = data;
                        var html = '';
                        var lines = data.split('\n');
                        for(var i=0; i<lines.length; i++) {
                            var parsed = parseFileLogLine(lines[i]);
                            if (!parsed) continue;
                            html += `<div class="file-item"><div class="file-name" title="${parsed.name}"><i class="bi ${parsed.icon} me-2 fs-6 align-middle"></i>${parsed.name}</div>
                                <div class="file-meta"><span class="file-size">${parsed.size}</span><button class="btn btn-sm btn-delete-file delete-item-btn" data-item="${parsed.name}"><i class="bi bi-trash3"></i></button></div></div>`;
                        }
                        $("#downloaded-files-data").html(html || '<div class="text-muted text-center mt-4">No active downloads</div>');
                    }
                });

                if ($("#downloaded-files-data2").length > 0) {
                    $.get('/echo_the_content.php?file=5', function(data) {
                        if(data !== lastFinishedUnpacked) {
                            lastFinishedUnpacked = data;
                            var html = '';
                            var lines = data.split('\n');
                            for(var i=0; i<lines.length; i++) {
                                var parsed = parseFileLogLine(lines[i]);
                                if (!parsed) continue;
                                html += `<div class="file-item"><div class="file-name" title="${parsed.name}"><i class="bi ${parsed.icon} me-2 fs-6 align-middle"></i>${parsed.name}</div>
                                    <div class="file-meta"><span class="file-size">${parsed.size}</span><button class="btn btn-sm btn-delete-file delete-item-btn" data-item="${parsed.name}"><i class="bi bi-trash3"></i></button></div></div>`;
                            }
                            $("#downloaded-files-data2").html(html || '<div class="text-muted text-center mt-4">No unpacked files</div>');
                        }
                    });
                }
            }

            fetchFileLogs();
		    setInterval(fetchFileLogs, 3000);
		});
	</script>
</head>
<body>

<div id="toast-container" class="toast-container"></div>

<!-- Episode Redownload Modal -->
<div class="modal fade" id="episodeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-black border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-light" id="episodeModalTitle">Manage Episodes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="episodeModalBody">
                <!-- Dynamically Populated -->
            </div>
        </div>
    </div>
</div>

<?php
    if (!file_exists($base_dir . '/manualOutput.log')) touch($base_dir . '/manualOutput.log');
	if (!file_exists($base_dir . '/docker_live_output.log')) touch($base_dir . '/docker_live_output.log');
    
    $running = false;
    $pids = trim(shell_exec("ps ux | grep 'download_anime.py' | grep -v grep"));
	if ($pids != '') $running = true;
    $pids2 = trim(shell_exec("ps ux | grep 'python3' | grep -v grep"));
    if ($pids2 == '') {
        file_put_contents($base_dir . '/docker_live_output.log', '');
    }
?>

<!-- Premium Top Navbar -->
<nav class="navbar navbar-expand-lg bg-black border-bottom border-secondary mb-4 py-2 sticky-top">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-bold d-flex align-items-center" style="color: #e6edf3;" href="#">
        <i class="bi bi-cloud-arrow-down-fill text-primary me-2 fs-4"></i> Anime-Loads
    </a>
    
    <!-- Dual Disk Space Indicator -->
    <div class="ms-4 d-none d-xl-flex align-items-center" style="width: 250px;" title="Volume Free Space">
        <i class="bi bi-device-hdd text-secondary me-3 fs-4"></i>
        <div class="w-100 d-flex flex-column justify-content-center pt-1" style="font-size: 10px;">
            <?php if ($ssdInfo): ?>
            <div class="d-flex justify-content-between text-muted mb-1" style="line-height: 1;">
                <span><i class="bi bi-usb-drive me-1 text-light"></i><?= htmlspecialchars($ssdInfo['label']) ?></span><span class="text-light fw-bold"><?php echo $ssdInfo['free']; ?> free</span>
            </div>
            <div class="progress mb-2" style="height: 4px; background-color: #010409; border: 1px solid #30363d;">
                <div class="progress-bar <?php echo $ssdInfo['class']; ?>" style="width: <?php echo $ssdInfo['pct']; ?>%"></div>
            </div>
            <?php endif; ?>
            
            <?php if ($hddInfo && (!$ssdInfo || $hddInfo['total'] !== $ssdInfo['total'])): ?>
            <div class="d-flex justify-content-between text-muted mb-1" style="line-height: 1;">
                <span><i class="bi bi-hdd me-1 text-light"></i><?= htmlspecialchars($hddInfo['label']) ?></span><span class="text-light fw-bold"><?php echo $hddInfo['free']; ?> free</span>
            </div>
            <div class="progress" style="height: 4px; background-color: #010409; border: 1px solid #30363d;">
                <div class="progress-bar <?php echo $hddInfo['class']; ?>" style="width: <?php echo $hddInfo['pct']; ?>%"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex gap-2 ms-auto">
        <a href="https://github.com/Crypto90/anime-loads" target="_blank" class="btn btn-surface btn-sm" title="View Source on GitHub"><i class="bi bi-github me-1"></i> GitHub</a>
        <a href="https://ko-fi.com/crypto90?ref=anime_loads_docker" target="_blank" class="btn btn-surface btn-sm text-warning" title="Support the Project"><i class="bi bi-cup-hot-fill me-1"></i> Support</a>
        <a href="test.php" class="btn btn-surface btn-sm"><i class="bi bi-activity me-1"></i> Diagnostics</a>
        <a href="settings.php" class="btn btn-surface btn-sm"><i class="bi bi-gear-fill me-1"></i> Settings</a>
        <a href="folder_manager.php?lang=ger" target="_blank" class="btn btn-surface btn-sm"><i class="bi bi-folder-symlink me-1"></i> Folder Manager</a>
        <?php
            $myjdDeviceId = $config['myjd_device_id'] ?? '';
            $myjdLink = $myjdDeviceId ? "https://my.jdownloader.org/index.html?deviceId={$myjdDeviceId}#webinterface:downloads" : "https://my.jdownloader.org/";
        ?>
        <a href="<?= htmlspecialchars($myjdLink) ?>" target="_blank" class="btn btn-surface btn-sm"><i class="bi bi-cloud-arrow-down me-1"></i> MyJD API</a>
        <a href='?downloader=1' class='btn btn-surface-warning btn-sm'><i class="bi bi-arrow-clockwise me-1"></i> Restart Core</a>
        <a href='?killrequest=1' class='btn btn-surface-danger btn-sm'><i class="bi bi-stop-circle me-1"></i> Kill Active</a>
        <a href="index.php?action=logout" class="btn btn-surface btn-sm px-3"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</nav>

<div class="container-fluid px-4 pb-5">

    <?php if ($running): ?>
        <div class="alert bg-warning text-dark border-warning shadow-sm mb-4 fw-bold" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> A process is currently running! Any new requests will be queued automatically.
        </div>
    <?php endif; ?>

    <!-- Top Section: Form & Live Logs -->
    <div class="row g-4 mb-5">
        
        <!-- Left Column: Request Form -->
        <div class="col-xl-4 col-lg-5">
            <div class="panel h-100">
                <div class="p-3 border-bottom border-secondary bg-black" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0 text-white fw-bold"><i class="bi bi-plus-lg me-2 text-primary"></i>New Download Request</h6>
                </div>
                <div class="p-4">
                    <form method="POST" action="index.php" id="mainRequestForm">
                        <div class="mb-3">
                            <label class="mb-2">Title or Exact URL <span class="text-muted">(Anime-Loads.org)</span></label>
                            <input type="text" class="form-control" name="animeTitel" placeholder="e.g. Super cooler kawaii Anime <3" value="<?php echo $_POST['animeTitel'] ?? '' ?>" required>
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="mb-2">Language</label>
                                <select class="form-select" name="languageselect">
                                    <option value="german" <?php echo (!isset($_POST['languageselect']) || $_POST['languageselect'] == 'german' ? 'selected' : '') ?>>Deutsch</option>
                                    <option value="japanese" <?php echo (isset($_POST['languageselect']) && $_POST['languageselect'] == 'japanese' ? 'selected' : '') ?>>Japanisch</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="mb-2">Resolution</label>
                                <select class="form-select" name="resolutionselect">
                                    <option value="1080p" <?php echo (!isset($_POST['resolutionselect']) || $_POST['resolutionselect'] == '1080p' ? 'selected' : '') ?>>1080p</option>
                                    <option value="720p" <?php echo (isset($_POST['resolutionselect']) && $_POST['resolutionselect'] == '720p' ? 'selected' : '') ?>>720p</option>
                                </select> 
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-4">
                                <label class="mb-2">Force Result</label>
                                <input type="number" class="form-control text-center" name="forceAnimeResult" placeholder="ID" value="<?php echo $_POST['forceAnimeResult'] ?? '' ?>">
                            </div>
                            <div class="col-sm-4">
                                <label class="mb-2">Force Release</label>
                                <input type="number" class="form-control text-center" name="forceAnimeRelease" placeholder="ID" value="<?php echo $_POST['forceAnimeRelease'] ?? '' ?>">
                            </div>
                            <div class="col-sm-4">
                                <label class="mb-2">Skip EPs</label>
                                <input type="number" class="form-control text-center" name="skipEpisodes" placeholder="0" value="<?php echo $_POST['skipEpisodes'] ?? '' ?>">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-4 px-3 py-3" style="background: #010409; border-radius: 8px; border: 1px solid #21262d;">
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" id="ISHENTAI" name="ISHENTAI" <?php echo (isset($_POST['ISHENTAI']) ? 'checked' : '') ?>>
                                <label class="form-check-label text-danger fw-bold" for="ISHENTAI" style="font-size: 13px;">Is Hentai?</label>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" id="DRYRUN" name="DRYRUN" <?php echo (isset($_POST['DRYRUN']) ? 'checked' : '') ?>>
                                <label class="form-check-label text-warning fw-bold" style="color: #e3b341 !important;" for="DRYRUN" style="font-size: 13px;">Dry Run</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mb-4"><i class="bi bi-send-fill me-2"></i>Submit Request</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Terminals & Logs -->
        <div class="col-xl-8 col-lg-7 d-flex flex-column gap-4">
            
            <!-- Terminals (Side-by-Side) -->
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="mb-2"><i class="bi bi-terminal me-2 text-secondary"></i>Process Output</label>
                    <div class="mac-window">
                        <div class="mac-header">
                            <span class="mac-dot mac-close"></span><span class="mac-dot mac-min"></span><span class="mac-dot mac-max"></span>
                        </div>
                        <pre id="manualOutput" class="mac-body"></pre>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="mb-2"><i class="bi bi-cpu me-2 text-secondary"></i>Docker Background Log</label>
                    <div class="mac-window">
                        <div class="mac-header">
                            <span class="mac-dot mac-close"></span><span class="mac-dot mac-min"></span><span class="mac-dot mac-max"></span>
                        </div>
                        <pre id="manualOutput2" class="mac-body"></pre>
                    </div>
                </div>
            </div>

            <!-- Log Panels -->
            <?php
            $hasExtraction = !empty($config['jd_extraction_dir']);
            $colClass = $hasExtraction ? 'col-md-3 col-sm-6' : 'col-md-4 col-sm-12';
            ?>
            <div class="row g-4">
                <div class="<?= $colClass ?>">
                    <label class="mb-2"><i class="bi bi-download me-2 text-primary"></i>Active Cache</label>
                    <div id="downloaded-files-data" class="file-panel"></div>
                </div>
                <?php if ($hasExtraction): ?>
                <div class="<?= $colClass ?>" id="panel-finished-unpacked">
                    <label class="mb-2"><i class="bi bi-check2-circle me-2" style="color: #56d364;"></i>Finished Unpacked</label>
                    <div id="downloaded-files-data2" class="file-panel"></div>
                </div>
                <?php endif; ?>
                <div class="<?= $colClass ?>">
                    <label class="mb-2"><i class="bi bi-list-ol me-2 text-warning" style="color: #e3b341 !important;"></i>Queue</label>
                    <div id="request-queue-data" class="file-panel"></div>
                </div>
                <div class="<?= $colClass ?>">
                    <label class="mb-2"><i class="bi bi-journal-text me-2 text-secondary"></i>Log</label>
                    <div id="request-queue-log-data" class="file-panel"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Middle Section: NATIVE JDownloader Integration -->
    <div class="panel mb-5">
        <div class="p-3 border-bottom border-secondary bg-black d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
            <h5 class="mb-0 text-white fw-bold"><i class="bi bi-cloud-arrow-down-fill me-3 text-primary"></i>JDownloader Live Remote</h5>
            <div class="d-flex align-items-center gap-4">
                
                <!-- Bulk Actions -->
                <div class="dropdown">
                    <button class="btn btn-surface btn-sm dropdown-toggle" type="button" id="jd-bulk-btn" data-bs-toggle="dropdown" disabled>
                        Bulk Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark bg-black border-secondary">
                        <li><a class="dropdown-item text-success" href="#" onclick="JDController.bulkAction('jd_resume')"><i class="bi bi-play-fill me-2"></i>Resume Selected</a></li>
                        <li><a class="dropdown-item text-warning" href="#" onclick="JDController.bulkAction('jd_reset')"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset Selected</a></li>
                        <li><a class="dropdown-item text-extraction" href="#" onclick="JDController.bulkAction('jd_extract')"><i class="bi bi-file-earmark-zip me-2"></i>Extract Selected</a></li>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="JDController.bulkAction('jd_delete')"><i class="bi bi-trash3 me-2"></i>Delete Selected</a></li>
                    </ul>
                </div>

                <!-- Speed Limiter -->
                <div class="input-group input-group-sm ms-2" style="width: 140px;">
                    <span class="input-group-text bg-black border-secondary text-secondary"><i class="bi bi-speedometer"></i></span>
                    <input type="number" class="form-control text-center" id="jd-speed-input" placeholder="Limit" onchange="JDController.setSpeedLimit()">
                    <span class="input-group-text bg-black border-secondary text-secondary">KB/s</span>
                </div>

                <div class="d-flex align-items-center gap-2 border-start border-secondary ps-4">
                    <span id="jd-global-status" class="badge bg-success text-dark py-1 px-2"><i class="bi bi-activity me-1"></i>Online</span>
                    <span class="text-light fw-bold ms-2" style="font-size: 14px;"><i class="bi bi-speedometer2 me-2 text-secondary"></i><span id="jd-global-speed">0 B/s</span></span>
                </div>

                <div class="d-flex gap-2 border-start border-secondary ps-4">
                    <button class="btn btn-sm btn-surface" onclick="JDController.start()" title="Start Downloads"><i class="bi bi-play-fill text-success fs-6"></i></button>
                    <button class="btn btn-sm btn-surface" onclick="JDController.pause()" title="Pause Downloads"><i class="bi bi-pause-fill text-warning fs-6"></i></button>
                    <button class="btn btn-sm btn-surface" onclick="JDController.stop()" title="Stop Downloads"><i class="bi bi-stop-fill text-danger fs-6"></i></button>
                    <button class="btn btn-sm btn-surface ms-2" id="jd-update-btn" onclick="JDController.update()" title="Check / Trigger Update"><i class="bi bi-cloud-arrow-down text-info fs-6"></i></button>
                </div>
            </div>
        </div>
        <div class="p-0">
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-dark table-hover mb-0" style="font-size: 13.5px; background-color: transparent;">
                    <thead class="bg-black" style="position: sticky; top: 0; z-index: 10;">
                        <tr>
                            <th class="ps-4" style="width: 40px;"><input class="form-check-input" type="checkbox" id="jd-select-all" onchange="JDController.selectAll(this)"></th>
                            <th>Package Name</th>
                            <th style="width: 16%">Status</th>
                            <th style="width: 20%">Progress</th>
                            <th style="width: 12%">Loaded</th>
                            <th style="width: 10%">Speed</th>
                            <th style="width: 10%">ETA</th>
                            <th style="width: 14%" class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="jd-downloads-list">
                        <tr><td colspan="8" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Connecting to local API...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Middle Section: Monitored Library -->
    <div class="d-flex justify-content-between align-items-center mb-4 mt-5">
        <div class="d-flex align-items-center">
            <h4 class="m-0 text-white fw-bold"><i class="bi bi-collection me-3 text-primary"></i>Monitored Library</h4>
            <span class="ms-3 px-2 py-1 bg-black border border-secondary rounded" style="color: #a1aab5; font-size: 11px;">ani.json</span>
        </div>
        
        <!-- Live Library Search -->
        <div class="input-group input-group-sm" style="width: 250px;">
            <span class="input-group-text bg-black border-secondary text-secondary"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" id="librarySearch" placeholder="Filter Library...">
        </div>
    </div>
    
    <div class="anime-grid mb-5" id="anijson">
        <?php
        if (count($allAnimeObjects) > 0) {
            foreach (array_reverse($allAnimeObjects) as $anime) {
                $urlName = substr($anime->url, strrpos($anime->url, '/') + 1);
                
                // Original Synchronous Cover Generation
                $coverToDisplay = '';
                if (!file_exists('./anime_cover/'.$urlName.'.png') || !file_exists('./anime_cover/'.$urlName.'.txt')) {
                    $url = 'https://www.anisearch.de/anime/index/page-1?char=all&text=' . $urlName . '&smode=1&sort=date&order=asc&view=2&kev=7478ce6e';
                    $options = array(
                        CURLOPT_RETURNTRANSFER => 1, 
                        CURLOPT_USERAGENT      => "Mozilla/5.0",  
                        CURLOPT_FOLLOWLOCATION => true,   
                        CURLOPT_CONNECTTIMEOUT => 5,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_REFERER => 'https://www.anisearch.de/',
                    );
                    $ch = curl_init($url); curl_setopt_array($ch, $options);
                    $htmlContent = curl_exec($ch); curl_close($ch);
                    
                    $doc = new DOMDocument();
                    libxml_use_internal_errors(true);
                    $doc->loadHTML($htmlContent);
                    libxml_clear_errors();
                
                    $detailsRedirectCoverURL = '';
                    $gotElement = $doc->getElementById("details-cover");
                    if ($gotElement != NULL) { $detailsRedirectCoverURL = $gotElement->getAttribute('src'); }
                    
                    $resultsCoverURL = '';
                    $xpath = new DomXPath($doc);
                    $images = [];
                    foreach ($xpath->query("//th[contains(@class, 'showpop')]") as $img) {
                        if ($img->hasAttribute('data-tooltip')) {
                            preg_match('/< *img[^>]*src *= *["\']?([^"\']*)/i', $img->getAttribute('data-tooltip'), $match);
                            if (isset($match[1])) $images[] = trim($match[1], '\'" ');
                        }
                    }
                    if (isset($images[0]) && $images[0] != NULL) { $resultsCoverURL = $images[0]; }
                
                    if ($detailsRedirectCoverURL != '') { $coverToDisplay = $detailsRedirectCoverURL; } 
                    else if ($resultsCoverURL != '') { $coverToDisplay = $resultsCoverURL; }
                
                    if ($coverToDisplay != '') {
                        $file = fopen('./anime_cover/'.$urlName.'.png', 'w');
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $coverToDisplay);
                        curl_setopt($ch, CURLOPT_FAILONERROR, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
                        curl_setopt($ch, CURLOPT_FILE, $file);
                        curl_exec($ch); curl_close($ch); fclose($file);
                    }
                }
                
                preg_match('/\d+$/', $anime->anisearchUrl, $matches);
                $anisearchId = $matches[0] ?? '';
                
                // Meta info
                $flag = (strpos($anime->customPackage, 'japanese') !== false) ? 'japan' : 'germany';
                $maxEpisodesSaved = $anime->maxEpisodes == 1337 ? 1 : $anime->maxEpisodes;
                
                $isCompleted = ((strpos($anime->customPackage, 'movie') !== false && $anime->episodes == 1 && count($anime->missing) == 0) || ($anime->episodes == $maxEpisodesSaved && count($anime->missing) == 0));
                $releaseUID = ($anime->releaseUID != "") ? "..." . substr($anime->releaseUID, -20) : "";
                $isError = (strpos($anime->status, 'Release fehlerhaft') !== false && $releaseUID != "");
                $isPaused = isset($anime->paused) && $anime->paused === true;
                
                $cardClass = "";
                if ($isPaused) {
                    $cardClass = "status-paused";
                } else if ($isCompleted) {
                    $cardClass = "status-completed";
                } else if ($isError) {
                    $cardClass = "status-error";
                }
                
                $missingText = count($anime->missing) > 0 ? implode(', ', $anime->missing) : '-';
                $safeJson = htmlspecialchars(json_encode($anime), ENT_QUOTES, 'UTF-8');

                $pauseIcon = $isPaused ? 'bi-play-fill' : 'bi-pause-fill';
                $pauseTitle = $isPaused ? 'Resume Monitoring' : 'Pause Monitoring';
                $pauseBtnClass = $isPaused ? 'btn-surface-success' : 'btn-surface-warning';
                $pausedBadge = $isPaused ? '<span class="badge bg-warning text-dark ms-2" style="font-size: 9px;"><i class="bi bi-pause-fill"></i> PAUSED</span>' : '';

                echo '
                <div class="anime-grid-item">
                    <div class="premium-card ' . $cardClass . '">
                        <div class="card-img-wrapper">
                            <img data="' . htmlspecialchars($anime->url) . '" src="getcover.php?url=' . urlencode($anime->coverUrl) . '&id=' . $anisearchId . '&urlName=' . urlencode($urlName) . '">
                        </div>
                        <div class="card-info">
                            <div class="anime-title" data="' . htmlspecialchars($anime->url) . '" title="' . htmlspecialchars($anime->name) . '">' . htmlspecialchars($anime->name) . $pausedBadge . '</div>
                            <div class="premium-badge">' . htmlspecialchars($anime->customPackage) . '</div>
                            
                            <div class="meta-data-grid">
                                <div class="meta-text text-truncate"><i class="bi bi-hdd text-secondary me-1"></i> Rel ID: <span class="text-light">' . $anime->releaseID . '</span></div>
                                <div class="meta-text text-truncate"><i class="bi bi-hash text-secondary me-1"></i> Ani ID: <span class="text-light">' . $anisearchId . '</span></div>
                            </div>
                            
                            <div class="meta-text mb-2 text-truncate"><i class="bi bi-activity text-secondary me-1"></i> <span class="text-light">' . htmlspecialchars($anime->status) . '</span></div>
                            
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="meta-text m-0 fw-bold flex-shrink-0" style="color: #56d364;"><i class="bi bi-check2-circle me-1"></i>' . $anime->episodes . '/' . $maxEpisodesSaved . '</div>
                                <div class="meta-text m-0 text-danger text-truncate" title="' . $missingText . '"><i class="bi bi-exclamation-triangle me-1"></i>' . $missingText . '</div>
                            </div>
                            
                            <div class="card-bottom-actions">
                                <div class="d-flex gap-2">
                                    <button class="btn ' . $pauseBtnClass . ' card-action-btn toggle-pause-btn" data-pkg="' . htmlspecialchars($anime->customPackage) . '" title="' . $pauseTitle . '"><i class="bi ' . $pauseIcon . '"></i></button>
                                    <button class="btn btn-surface-secondary card-action-btn" data-anime="' . $safeJson . '" onclick="openEpisodeManager(this.dataset.anime)" title="Manage Episodes"><i class="bi bi-list-ol me-1"></i>Eps</button>
                                    <button data="?unmonitor=' . urlencode($anime->customPackage) . '" class="btn btn-surface-danger card-action-btn unmonitor-btn" title="Delete completely"><i class="bi bi-trash3"></i></button>
                                </div>
                                <div class="flex-shrink-0 mb-1">
                                    ' . (file_exists("$flag.png") ? '<img src="' . $flag . '.png" class="flag-icon" />' : '') . '
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
            }
        }
        ?>
    </div>

    <!-- Quick Add Section (Live AniList GraphQL API) -->
    <div class="d-flex align-items-center mb-4 mt-5">
        <h4 class="m-0 text-white fw-bold d-flex align-items-center">
            <i class="bi bi-lightning-charge me-3 text-success"></i>
            <span id="seasonTitle">Trending Seasonal</span>
        </h4>
        <div class="d-flex align-items-center gap-2 ms-4">
            <button class="btn btn-surface btn-sm px-2 season-btn" onclick="changeQASeason(-1)" title="Previous Season"><i class="bi bi-chevron-left"></i></button>
            <button class="btn btn-surface btn-sm px-2 season-btn" onclick="changeQASeason(1)" title="Next Season"><i class="bi bi-chevron-right"></i></button>
            <button class="btn btn-surface btn-sm px-3 season-btn" onclick="loadSeasonalAnime()" title="Reload Season"><i class="bi bi-arrow-repeat"></i></button>
        </div>
    </div>
    <div id="quickadd-container" class="quickadd-grid mb-5">
        <div class="text-muted w-100 text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading anime...</div>
    </div>

    <!-- Bottom Section: AniSearch Toplist -->
    <div class="d-flex align-items-center mb-4 mt-5">
        <h4 class="m-0 text-white fw-bold"><i class="bi bi-fire me-3 text-warning" style="color: #e3b341 !important;"></i>Weekly Toplist Graphic</h4>
        
        <div class="d-flex align-items-center gap-2 ms-5">
			<select id="year" class="form-select form-select-sm bg-black" style="width: 120px;">
				<?php
				$currentYear = date("Y");
				for ($y = $currentYear; $y >= 2000; $y--) { echo "<option value='$y'>$y</option>"; }
				?>
			</select>
			<select id="week" class="form-select form-select-sm bg-black" style="width: 100px;">
				<?php
				$currentWeek = date('W') - 1;
				for ($w = 1; $w <= 52; $w++) {
					$selected = ($w == $currentWeek) ? 'selected' : '';
					echo "<option value='$w' $selected>$w</option>";
				}
				?>
			</select>
			<button id="updateButton" class="btn btn-surface btn-sm px-3"><i class="bi bi-arrow-repeat"></i></button>
		</div>
    </div>

    <div class="toplist-wrapper mb-5">
        <div id="prevweek" class="nav-overlay-btn nav-overlay-left">
            <i class="bi bi-chevron-left nav-arrow"></i>
        </div>
        <div id="nextweek" class="nav-overlay-btn nav-overlay-right">
            <i class="bi bi-chevron-right nav-arrow"></i>
        </div>
        <img id="animeImage" src="" class="img-fluid w-100 d-block" alt="AniSearch Toplist">
    </div>

</div>

<script>
    function updateImage() {
        var selectedYear = $("#year").val();
        var selectedWeek = $("#week").val();
        $("#animeImage").attr("src", `https://api.anisearch.de/v1/trending/anime/${selectedYear}-${selectedWeek}.webp`);
    }

    $("#updateButton").on("click", updateImage);
    
    $("#prevweek").on("click", function() {
        var selectBox = $('#week');
        var selectedOption = selectBox.find(':selected');
        var prevOption = selectedOption.prev('option');
        if (prevOption.length > 0) {
            selectedOption.removeAttr('selected');
            prevOption.prop('selected', true);
        }
        updateImage();
    });
    
    $("#nextweek").on("click", function() {
        var selectBox = $('#week');
        var selectedOption = selectBox.find(':selected');
        var nextOption = selectedOption.next('option');
        if (nextOption.length > 0) {
            selectedOption.removeAttr('selected');
            nextOption.prop('selected', true);
        }
        updateImage();
    });

    updateImage(); 
</script>

</body>
</html>