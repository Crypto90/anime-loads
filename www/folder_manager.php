<?php
$configFile = '/config/config.json';
if (!file_exists($configFile)) {
    header('Location: setup.php');
    exit;
}
$config = json_decode(file_get_contents($configFile), true);
$base_dir = $config['base_dir'];

// folder_manager.php
session_start();
$conf_user = $config['web_user'] ?? 'admin';
$conf_pass = $config['web_password'] ?? 'admin';

if (!isset($_SESSION['user']) || $_SESSION['user'] !== $conf_user || !isset($_SESSION['pass']) || $_SESSION['pass'] !== $conf_pass) {
    session_write_close();
    header('Location: index.php');
    exit;
}
session_write_close(); // Unlock session immediately so browser doesn't hang

set_time_limit(0);
ini_set('max_execution_time', 0);
ignore_user_abort(true); // Ensure script finishes even if the web server drops the connection on long copies

// Cleanup old progress files to keep the temp folder clean
$tempFiles = glob(sys_get_temp_dir() . '/folder_manager_progress_*.json');
foreach ($tempFiles as $tFile) {
    if (filemtime($tFile) < time() - 3600) { // Older than 1 hour
        @unlink($tFile);
    }
}

// Assemble all directories for dynamic tabs
$primary = $config['jd_download_dir'] ?? '/downloads';
$extraction = $config['jd_extraction_dir'] ?? '';
$main_storage = $config['main_storage_dir'] ?? '';
$additional = $config['additional_dirs'] ?? [];

$all_dirs = [$primary];
if (!empty($extraction)) $all_dirs[] = $extraction;
if (!empty($main_storage)) $all_dirs[] = $main_storage;

$default_source = null;

// Determine the active "final" parent directory where categories live
$final_target_base = !empty($main_storage) ? $main_storage : (!empty($extraction) ? $extraction : $primary);

$cats = [
    $config['cat_anime_ger'] ?? 'Anime (Ger)',
    $config['cat_anime_jap'] ?? 'Anime (Jap)',
    $config['cat_filme_ger'] ?? 'Filme',
    $config['cat_filme_jap'] ?? 'Filme (Jap)',
    $config['cat_hentai'] ?? 'Hentai'
];
$cats = array_filter(array_unique($cats));

foreach ($cats as $cat) {
    $cat_path = rtrim($final_target_base, '/') . '/' . $cat;
    $all_dirs[] = $cat_path;
    if ($default_source === null) $default_source = $cat_path; // Default to first category
}

$all_dirs = array_merge($all_dirs, $additional);
$all_dirs = array_values(array_unique(array_filter($all_dirs)));

// Default source fallback
if ($default_source === null) {
    $default_source = !empty($extraction) ? $extraction : $primary;
}

$default_index = array_search($default_source, $all_dirs);
if ($default_index === false) $default_index = 0;

$sourceIndex = isset($_GET['src']) ? (int)$_GET['src'] : $default_index;
if (!isset($all_dirs[$sourceIndex])) $sourceIndex = $default_index;

$sourcePath = $all_dirs[$sourceIndex];

// Ensure source exists
if (!is_dir($sourcePath)) @mkdir($sourcePath, 0777, true);

$cacheFile = 'anime_cache_' . md5($sourcePath) . '.json';
$pageTitle = 'Folder Manager: ' . basename($sourcePath);

$targetPaths = [];
foreach ($all_dirs as $index => $dir) {
    if ($index !== $sourceIndex) {
        $targetPaths[] = $dir;
    }
}

$jsonFilePath = file_exists('/config/ani.json') ? '/config/ani.json' : ($base_dir . '/ani.json');  

// --- CORE FUNCTIONS ---

if (isset($_POST['clearCache'])) {
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
        echo "<div class='alert alert-success'>Cache cleared successfully!</div>";
    } else {
        echo "<div class='alert alert-info'>Cache was already empty.</div>";
    }
    exit;
}

// Advanced Chunked Copy for Real-Time File Progress
function copyFolderChunked($source, $destination, $progressFile, &$state) {
    if (!is_dir($destination)) {
        mkdir($destination, 0777, true);
    }
    
    $dir = @opendir($source);
    if (!$dir) return false;
    
    while (($item = readdir($dir)) !== false) {
        if ($item == '.' || $item == '..') continue;
        
        $srcPath = $source . '/' . $item;
        $destPath = $destination . '/' . $item;

        if (is_dir($srcPath)) {
            if (!copyFolderChunked($srcPath, $destPath, $progressFile, $state)) return false;
        } else {
            // Update state: Start new file
            $state['current_file'] = $item;
            $state['current_file_progress'] = 0;
            file_put_contents($progressFile, json_encode($state));

            $in = @fopen($srcPath, 'rb');
            $out = @fopen($destPath, 'wb');
            
            if ($in && $out) {
                $fileSize = filesize($srcPath);
                $copiedBytes = 0;
                $lastUpdateTime = microtime(true);
                
                // Copy in 2MB Chunks
                while (!feof($in)) {
                    $buffer = fread($in, 1024 * 1024 * 2);
                    if ($buffer === false) break;
                    fwrite($out, $buffer);
                    $copiedBytes += strlen($buffer);
                    
                    $now = microtime(true);
                    if ($now - $lastUpdateTime > 0.3 || $copiedBytes >= $fileSize) {
                        $state['current_file_progress'] = $fileSize > 0 ? round(($copiedBytes / $fileSize) * 100) : 100;
                        file_put_contents($progressFile, json_encode($state));
                        $lastUpdateTime = $now;
                    }
                }
                fclose($in);
                fclose($out);
                
                // Update state: Finished file
                $state['copied_files']++;
                if ($state['overall_files'] > 0) {
                    $state['overall_progress'] = round(($state['copied_files'] / $state['overall_files']) * 100);
                }
                file_put_contents($progressFile, json_encode($state));
            } else {
                if ($in) fclose($in);
                if ($out) fclose($out);
                return false; 
            }
        }
    }
    closedir($dir);
    return true;
}

function deleteFolder($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) deleteFolder($path);
        else unlink($path);
    }
    rmdir($dir);
}

function getFolderDetails($dir) {
    if (!is_readable($dir)) {
        return ['size' => 'N/A', 'count' => 'N/A', 'newest_file_date' => 'N/A'];
    }
    $escapedDir = escapeshellarg($dir);
    $size = trim(shell_exec("du -sh $escapedDir 2>/dev/null | cut -f1"));
    if (!empty($size)) {
        $size = preg_replace('/([KMGTP])$/i', '${1}B', $size);
    }
    $count = trim(shell_exec("find $escapedDir -type f 2>/dev/null | wc -l"));
    $newestFileDate = trim(shell_exec("find $escapedDir -type f -printf '%TY-%Tm-%Td %TH:%TM:%TS\n' 2>/dev/null | sort -r | head -n 1"));
    if (!empty($newestFileDate)) {
        $newestFileDate = preg_replace('/\.\d+/', '', $newestFileDate);
    }

    return [
        'size' => empty($size) ? 'N/A' : $size,
        'count' => (empty($count) || $count == 0) ? '0' : $count,
        'newest_file_date' => (empty($count) || $count == 0) ? 'N/A' : ($newestFileDate ?: 'N/A')
    ];
}

function loadFolders($sourcePath) {
    $allFolders = array_filter(glob($sourcePath . '/*'), 'is_dir');
    return array_filter($allFolders, function($folder) {
        return strpos(basename($folder), '@') === false;
    });
}

function folderExistsInJson($folderName, $jsonFilePath) {
    if (file_exists($jsonFilePath)) {
        return strpos(file_get_contents($jsonFilePath), $folderName) !== false;
    }
    return false;
}

function getFreeDiskSpace($path) {
    $output = shell_exec("df -h --output=avail " . escapeshellarg($path) . " 2>/dev/null");
    if (empty($output)) return 'N/A';
    $lines = explode("\n", trim($output));
    $space = isset($lines[1]) ? trim($lines[1]) : 'N/A';
    if ($space !== 'N/A') {
        $space = preg_replace('/([KMGTP])$/i', '${1}B', $space);
    }
    return $space;
}

function getDriveName($path) {
    if (preg_match('/volumeUSB(\d+)/', $path, $matches)) return "USB Drive " . $matches[1];
    if (preg_match('/volume(\d+)\/hdd_intern/', $path, $matches)) return "Internal HDD " . $matches[1];
    if (preg_match('/volume(\d+)/', $path, $matches)) return "Volume " . $matches[1];
    return basename($path) ? basename($path) : "Drive";
}

function removeFromCache($cacheFile, $folderName) {
    if (file_exists($cacheFile)) {
        $fp = fopen($cacheFile, 'c+');
        if (flock($fp, LOCK_EX)) {
            $size = filesize($cacheFile);
            $content = $size > 0 ? fread($fp, $size) : '';
            $cache = json_decode($content, true) ?: [];
            if (isset($cache[$folderName])) {
                unset($cache[$folderName]);
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($cache));
            }
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

// --- AJAX HANDLERS ---

if (isset($_POST['getFolderDetails'])) {
    $folderName = urldecode($_POST['folderName']);
    $folder = $sourcePath . '/' . $folderName;
    $details = getFolderDetails($folder);

    $fp = fopen($cacheFile, 'c+');
    if (flock($fp, LOCK_EX)) {
        $size = filesize($cacheFile);
        $content = $size > 0 ? fread($fp, $size) : '';
        $currentCache = json_decode($content, true) ?: [];
        $currentCache[$folderName] = $details;
        
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($currentCache));
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    echo json_encode($details);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'getProgress') {
    $taskId = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['task_id']);
    $progressFile = sys_get_temp_dir() . "/folder_manager_progress_{$taskId}.json";
    
    if (file_exists($progressFile)) {
        echo file_get_contents($progressFile);
    } else {
        echo json_encode(['status' => 'waiting']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selectedFolders'])) {
    $selectedFolders = $_POST['selectedFolders'];
    $taskId = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['task_id']);
    $progressFile = sys_get_temp_dir() . "/folder_manager_progress_{$taskId}.json";
    
    $state = [
        'status' => 'processing',
        'current_folder' => '',
        'current_file' => 'Scanning files...',
        'current_file_progress' => 0,
        'copied_files' => 0,
        'overall_files' => 0,
        'overall_progress' => 0,
        'log' => ''
    ];
    $actionLog = "";

    if (isset($_POST['targetFolder']) && $_POST['action_type'] === 'move') {
        $targetFolder = rtrim($_POST['targetFolder'], '/');

        // Pre-count ALL files for accurate Overall Progress
        foreach ($selectedFolders as $folder) {
            $src = $sourcePath . '/' . basename($folder);
            $count = trim(shell_exec("find " . escapeshellarg($src) . " -type f 2>/dev/null | wc -l"));
            $state['overall_files'] += (int)$count;
        }
        file_put_contents($progressFile, json_encode($state));

        // Perform Move
        foreach ($selectedFolders as $folder) {
            $sourceFolder = $sourcePath . '/' . basename($folder);
            $destinationFolder = $targetFolder . '/' . basename($folder);
            
            $state['current_folder'] = basename($folder);
            file_put_contents($progressFile, json_encode($state));
            
            if (!is_writable($targetFolder)) {
                $actionLog .= "<div class='text-danger'><i class='bi bi-shield-x'></i> Skipped <strong>" . htmlspecialchars($folder) . "</strong>: Target disk is not writable.</div>";
                continue;
            }
            
            $unauth = trim(shell_exec("find " . escapeshellarg($sourceFolder) . " ! -readable -o ! -writable 2>/dev/null | head -n 1"));
            if (!empty($unauth)) {
                $actionLog .= "<div class='text-danger'><i class='bi bi-shield-x'></i> Skipped <strong>" . htmlspecialchars($folder) . "</strong>: Permission denied on file/folder: <em>" . htmlspecialchars($unauth) . "</em>. Fix permissions manually.</div>";
                continue;
            }

            if (copyFolderChunked($sourceFolder, $destinationFolder, $progressFile, $state)) {
                deleteFolder($sourceFolder);
                removeFromCache($cacheFile, basename($folder));
                $actionLog .= "<div class='text-success'><i class='bi bi-check-circle'></i> Successfully moved: <strong>" . htmlspecialchars($folder) . "</strong></div>";
            } else {
                $actionLog .= "<div class='text-danger'><i class='bi bi-x-circle'></i> Failed to move: <strong>" . htmlspecialchars($folder) . "</strong></div>";
            }
        }
    } elseif ($_POST['action_type'] === 'delete') {
        foreach ($selectedFolders as $folder) {
            $sourceFolder = $sourcePath . '/' . basename($folder);
            $unauth = trim(shell_exec("find " . escapeshellarg($sourceFolder) . " ! -readable -o ! -writable 2>/dev/null | head -n 1"));
            if (!empty($unauth)) {
                $actionLog .= "<div class='text-danger'><i class='bi bi-shield-x'></i> Skipped Delete <strong>" . htmlspecialchars($folder) . "</strong>: Permission denied on file/folder: <em>" . htmlspecialchars($unauth) . "</em>.</div>";
                continue;
            }
            deleteFolder($sourceFolder);
            removeFromCache($cacheFile, basename($folder));
            $actionLog .= "<div class='text-success'><i class='bi bi-trash'></i> Deleted: <strong>" . htmlspecialchars($folder) . "</strong></div>";
        }
    }

    // Finalize state so the Poller catches it
    $state['status'] = 'completed';
    $state['overall_progress'] = 100;
    $state['log'] = $actionLog;
    file_put_contents($progressFile, json_encode($state));

    echo "OK";
    exit;
}

$cacheData = [];
if (file_exists($cacheFile)) {
    $cacheData = json_decode(file_get_contents($cacheFile), true) ?: [];
}
$folders = loadFolders($sourcePath); 

?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Folder Manager - <?php echo $pageTitle; ?></title>
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    
    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <style>
        body { background-color: #121212; color: #e0e0e0; margin: 0; padding: 0; overflow: hidden; font-family: -apple-system, system-ui, sans-serif; }
        .nav-pills .nav-link.active { background-color: #0d6efd; color: #fff; font-weight: bold; }
        .nav-pills .nav-link { color: #a0a0a0; }
        .table-hover tbody tr:hover { background-color: rgba(255, 255, 255, 0.05); }
        .status-done { border-left: 5px solid #198754 !important; }
        .status-processing { border-left: 5px solid #ffc107 !important; }
        .status-empty { border-left: 5px solid #dc3545 !important; opacity: 0.7; }
        .table-responsive { border: 1px solid #333; border-radius: 8px; overflow-y: auto; }
        thead th { position: sticky; top: 0; background-color: #1f1f1f !important; z-index: 2; border-bottom: 2px solid #333; }
        th.sortable { cursor: pointer; user-select: none; }
        th.sortable:hover { color: #fff; background-color: #2a2a2a !important; }
        th.sortable.active-sort { background-color: #2b3035 !important; color: #0d6efd !important; }
        th.sortable i { font-size: 0.8rem; margin-left: 5px; opacity: 0.5; }
        th.sortable.active-sort i { opacity: 1; color: #0d6efd; }
        .console-log { background: #010409; padding: 15px; border-radius: 8px; height: 160px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 13px; color: #a1aab5; border: 1px solid #30363d; }
        .select2-container--bootstrap-5 .select2-selection { background-color: #212529; color: #fff; border-color: #495057; height: auto; min-height: 42px; }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { display: flex; align-items: center; color: #fff; padding: 4px 12px; width: 100%; line-height: normal; }
        .select2-container--bootstrap-5 .select2-dropdown { background-color: #212529; border-color: #495057; color: #fff; }
        .select2-container--bootstrap-5 .select2-results__option--highlighted { background-color: #343a40 !important; color: #ffffff !important; }
        .select2-container--bootstrap-5 .select2-results__option[aria-selected="true"] { background-color: #1a1d20 !important; border-left: 3px solid #0d6efd; }
        .select2-results__option { padding: 10px 15px !important; border-bottom: 1px solid #333; }
        .select2-results__option:last-child { border-bottom: none; }
    </style>
</head>
<body class="d-flex flex-column vh-100">

<div class="container-fluid d-flex flex-column flex-grow-1 overflow-hidden p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
        <h2><i class="bi bi-hdd-network"></i> Folder Manager <span class="text-secondary fs-5">| <?php echo $pageTitle; ?></span></h2>
        
        <ul class="nav nav-pills">
            <?php foreach ($all_dirs as $index => $dir): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $index === $sourceIndex ? 'active' : ''; ?>" href="?src=<?php echo $index; ?>">
                    <i class="bi bi-folder"></i> <?php echo htmlspecialchars(basename($dir) ? basename($dir) : $dir); ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Controls Row -->
    <div class="card bg-dark border-secondary mb-3 flex-shrink-0">
        <div class="card-body d-flex gap-3 align-items-center flex-wrap">
            <form method="post" id="clearCacheForm" class="m-0">
                <input type="hidden" name="clearCache" value="true">
                <button type="submit" class="btn btn-outline-warning"><i class="bi bi-arrow-clockwise"></i> Clear Cache</button>
            </form>
            
            <div class="vr mx-2 text-secondary"></div>
            
            <!-- Filter & Search Group -->
            <div class="d-flex gap-2 align-items-center" style="max-width: 450px; flex-grow: 1;">
                <div style="min-width: 230px;">
                    <select id="statusFilter" class="form-select">
                        <option value="all">All Statuses</option>
                        <option value="status-done">Done / Unmonitored</option>
                        <option value="status-processing">Processing / Monitored</option>
                        <option value="status-empty">Empty Folders</option>
                    </select>
                </div>

                <div class="input-group">
                    <span class="input-group-text bg-dark text-secondary border-secondary"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control bg-dark text-light border-secondary" placeholder="Search...">
                </div>
            </div>

            <div class="vr mx-2 text-secondary"></div>
            
            <!-- Actions -->
            <form id="actionForm" class="d-flex gap-3 align-items-center flex-grow-1 m-0">
                <div style="flex-grow: 1; max-width: 500px;">
                    <select name="targetFolder" id="targetFolder" class="form-select">
                        <option value="">-- Select Target Destination --</option>
                        <?php foreach ($targetPaths as $path): ?>
                            <option value="<?php echo htmlspecialchars($path); ?>"
                                    data-drive="<?php echo htmlspecialchars(getDriveName($path)); ?>"
                                    data-path="<?php echo htmlspecialchars($path); ?>"
                                    data-free="<?php echo htmlspecialchars(getFreeDiskSpace($path)); ?>">
                                <?php echo htmlspecialchars($path); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <input type="hidden" name="action_type" id="action_type" value="">
                <input type="hidden" name="task_id" id="task_id" value="">
                
                <button type="button" class="btn btn-primary px-4" onclick="submitAction('move')">
                    <i class="bi bi-folder-symlink"></i> Move Selected
                </button>
                <button type="button" class="btn btn-outline-danger px-4" onclick="submitAction('delete')">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>

    <!-- MAIN FOLDER TABLE -->
    <div class="table-responsive bg-dark flex-grow-1 shadow-sm pb-0">
        <table class="table table-dark table-hover align-middle mb-0" id="folderTable">
            <thead>
                <tr>
                    <th style="width: 50px;" class="text-center">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                    </th>
                    <th class="sortable" data-type="string">Folder Name <i class="bi bi-arrow-down-up"></i></th>
                    <th class="sortable" data-type="string">Status <i class="bi bi-arrow-down-up"></i></th>
                    <th class="sortable" data-type="size">Size <i class="bi bi-arrow-down-up"></i></th>
                    <th class="sortable" data-type="number">File Count <i class="bi bi-arrow-down-up"></i></th>
                    <th class="sortable" data-type="date">Newest File Date <i class="bi bi-arrow-down-up"></i></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($folders)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No folders found in directory.</td></tr>
                <?php endif; ?>
                
                <?php 
                foreach ($folders as $folderPath):
                    $folderName = basename($folderPath);
                    $existsInJson = folderExistsInJson($folderName, $jsonFilePath);
                    
                    // Determine if we need an AJAX fetch
                    $isCached = isset($cacheData[$folderName]);
                    $details = $isCached ? $cacheData[$folderName] : ['size' => '...', 'count' => '...', 'newest_file_date' => '...'];
                    
                    // Assign classes
                    $rowClass = !$existsInJson ? 'status-done' : 'status-processing';
                    $needsUpdateClass = !$isCached ? 'needs-update' : '';
                    $emptyClass = ($isCached && $details['count'] === '0') ? 'status-empty' : '';
                ?>
                    <tr class="folder-row <?php echo "$rowClass $needsUpdateClass $emptyClass"; ?>" data-foldername="<?php echo htmlspecialchars($folderName); ?>">
                        <td class="text-center">
                            <input class="form-check-input folder-checkbox" type="checkbox" name="selectedFolders[]" value="<?php echo htmlspecialchars($folderName); ?>" form="actionForm">
                        </td>
                        <td class="fw-bold text-light folder-name-cell"><?php echo htmlspecialchars($folderName); ?></td>
                        <td>
                            <?php if(!$existsInJson): ?>
                                <span class="badge bg-success">Done / Unmonitored</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Processing / Monitored</span>
                            <?php endif; ?>
                            <span class="badge bg-danger empty-badge <?php echo ($details['count'] === '0') ? '' : 'd-none'; ?>">Empty</span>
                        </td>
                        <td class="folder-size text-info">
                            <?php if(!$isCached): ?>
                                <span class="spinner-border spinner-border-sm text-secondary" role="status"></span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($details['size']); ?>
                            <?php endif; ?>
                        </td>
                        <td class="folder-count text-info"><?php echo htmlspecialchars($details['count']); ?></td>
                        <td class="folder-date text-muted"><?php echo htmlspecialchars($details['newest_file_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Beautiful Advanced Progress Modal -->
<div class="modal fade" id="progressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary text-light shadow-lg">
      <div class="modal-header border-secondary bg-black">
        <h5 class="modal-title fw-bold" id="progressModalTitle">
            <i class="bi bi-gear-wide-connected text-primary me-2"></i>Processing Request...
        </h5>
      </div>
      <div class="modal-body p-4">
        
        <!-- Overall Progress -->
        <div class="mb-4">
            <div class="d-flex justify-content-between mb-1">
                <span class="text-light fw-bold">Overall Progress</span>
                <span class="text-info fw-bold" id="overallText">0 / 0 Files Processed (0%)</span>
            </div>
            <div class="progress" style="height: 12px; background-color: #21262d;">
                <div id="overallBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;"></div>
            </div>
        </div>

        <!-- Current Action Details -->
        <div class="p-3 mb-4 rounded" style="background-color: #010409; border: 1px solid #30363d;">
            <div class="mb-2 text-truncate">
                <span class="text-muted small text-uppercase fw-bold">Current Folder:</span><br>
                <i class="bi bi-folder2-open text-warning me-2"></i><span id="currentFolderText" class="text-light fw-bold">Initializing...</span>
            </div>
            <div class="text-truncate">
                <span class="text-muted small text-uppercase fw-bold">Current File Transfer:</span><br>
                <i class="bi bi-file-earmark-play text-success me-2"></i><span id="currentFileText" class="text-light">Scanning sizes...</span>
            </div>
            
            <!-- Per-File Bar -->
            <div class="progress mt-3" style="height: 6px; background-color: #21262d;">
                <div id="fileBar" class="progress-bar bg-success" role="progressbar" style="width: 0%; transition: width 0.3s ease;"></div>
            </div>
        </div>

        <!-- Live Server Log -->
        <div class="console-log" id="progressText"></div>
      </div>
      <div class="modal-footer border-secondary bg-black">
        <button type="button" class="btn btn-primary d-none fw-bold" id="closeModalBtn" data-bs-dismiss="modal" onclick="location.reload()">
            <i class="bi bi-check2-all me-2"></i>Done, Reload Page
        </button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {

    // 1a. Beautiful Custom Select2 Setup
    function formatTargetFolderOption(state) {
        if (!state.id) { return state.text; }
        var $el = $(state.element);
        var drive = $el.data('drive');
        var path = $el.data('path');
        var free = $el.data('free');
        
        return $(
            '<div class="d-flex justify-content-between align-items-center">' +
            '  <div class="lh-sm">' +
            '    <i class="bi bi-hdd-rack text-primary me-2 fs-5 align-middle"></i>' +
            '    <span class="fw-bold text-light">' + drive + '</span><br>' +
            '    <small class="text-muted ms-4" style="font-size: 0.8em;">' + path + '</small>' +
            '  </div>' +
            '  <span class="badge bg-success ms-3 shadow-sm px-2 py-1"><i class="bi bi-database me-1"></i> Free: ' + free + '</span>' +
            '</div>'
        );
    }

    function formatTargetFolderSelection(state) {
        if (!state.id) { return state.text; }
        var $el = $(state.element);
        var drive = $el.data('drive');
        var path = $el.data('path');
        var free = $el.data('free');
        
        return $(
            '<div class="d-flex justify-content-between align-items-center w-100 pe-4">' +
            '  <div class="text-truncate">' +
            '    <i class="bi bi-hdd-rack text-primary me-2"></i>' +
            '    <span class="fw-bold text-light">' + drive + '</span>' +
            '    <small class="text-muted ms-2 d-none d-lg-inline" style="font-size: 0.85em;">' + path + '</small>' +
            '  </div>' +
            '  <span class="badge bg-success shadow-sm flex-shrink-0"><i class="bi bi-database me-1"></i> Free: ' + free + '</span>' +
            '</div>'
        );
    }

    $('#targetFolder').select2({
        theme: 'bootstrap-5',
        width: '100%',
        minimumResultsForSearch: Infinity,
        templateResult: formatTargetFolderOption,
        templateSelection: formatTargetFolderSelection
    });

    // 1b. Beautiful Custom Select2 for Status Filter
    function formatStatus(state) {
        if (!state.id) { return state.text; }
        if (state.id === 'all') return $('<span><i class="bi bi-funnel text-secondary me-2"></i> All Statuses</span>');
        if (state.id === 'status-done') return $('<span class="badge bg-success" style="font-size: 0.9em;">Done / Unmonitored</span>');
        if (state.id === 'status-processing') return $('<span class="badge bg-warning text-dark" style="font-size: 0.9em;">Processing / Monitored</span>');
        if (state.id === 'status-empty') return $('<span class="badge bg-danger" style="font-size: 0.9em;">Empty Folders</span>');
        return state.text;
    }

    $('#statusFilter').select2({
        theme: 'bootstrap-5',
        width: '100%',
        minimumResultsForSearch: Infinity,
        templateResult: formatStatus,
        templateSelection: formatStatus
    });

    $('#selectAll').change(function() {
        $('.folder-checkbox:visible').prop('checked', $(this).prop('checked'));
    });

    var lastChecked = null;
    $(document).on('click', '.folder-checkbox', function(e) {
        var $boxes = $('.folder-checkbox:visible');
        if (!lastChecked) { lastChecked = this; return; }
        if (e.shiftKey) {
            var start = $boxes.index(this);
            var end = $boxes.index(lastChecked);
            $boxes.slice(Math.min(start, end), Math.max(start, end) + 1).prop('checked', lastChecked.checked);
        }
        lastChecked = this;
    });

    function applyFilters() {
        var term = $('#searchInput').val().toLowerCase();
        var statusFilter = $('#statusFilter').val();

        $('.folder-row').each(function() {
            var $row = $(this);
            var name = $row.find('.folder-name-cell').text().toLowerCase();
            var textMatch = (name.indexOf(term) > -1);
            var statusMatch = (statusFilter === 'all' || $row.hasClass(statusFilter));
            $row.toggle(textMatch && statusMatch);
        });
        $('.folder-checkbox:hidden').prop('checked', false);
        $('#selectAll').prop('checked', false);
    }

    $('#searchInput').on('keyup', applyFilters);
    $('#statusFilter').on('change', applyFilters);

    var defaultHeader = $('th.sortable').eq(0); 
    defaultHeader.addClass('active-sort').find('i').attr('class', 'bi bi-arrow-up');
    defaultHeader[0].asc = true;

    function parseSize(sizeStr) {
        if (!sizeStr || sizeStr === 'N/A' || sizeStr === '...' || sizeStr.indexOf('spinner') > -1) return 0;
        var cleanStr = sizeStr.replace(/\s+/g, '').replace(',', '.');
        var match = cleanStr.match(/^([\d.]+)([A-Za-z]*)$/);
        if (!match) return 0;
        var val = parseFloat(match[1]);
        var unit = match[2].toUpperCase();
        var multipliers = { '': 1, 'B': 1, 'K': 1024, 'KB': 1024, 'M': 1048576, 'MB': 1048576, 'G': 1073741824, 'GB': 1073741824, 'T': 1099511627776, 'TB': 1099511627776 };
        return val * (multipliers[unit] || 1);
    }

    $('th.sortable').click(function() {
        var table = $(this).parents('table').eq(0);
        var rows = table.find('tr.folder-row').toArray();
        var index = $(this).index();
        var type = $(this).data('type');
        
        this.asc = !this.asc;
        var isAsc = this.asc;

        $('th.sortable').removeClass('active-sort').find('i').attr('class', 'bi bi-arrow-down-up');
        $(this).addClass('active-sort');
        $(this).find('i').attr('class', isAsc ? 'bi bi-arrow-up' : 'bi bi-arrow-down');

        rows.sort(function(a, b) {
            var valA = $(a).children('td').eq(index).text().trim();
            var valB = $(b).children('td').eq(index).text().trim();

            if (type === 'size') return parseSize(valA) - parseSize(valB);
            if (type === 'number') return (parseInt(valA) || 0) - (parseInt(valB) || 0);
            if (type === 'date') return (new Date(valA).getTime() || 0) - (new Date(valB).getTime() || 0);
            return valA.localeCompare(valB);
        });

        if (!isAsc) { rows = rows.reverse(); }
        for (var i = 0; i < rows.length; i++) { table.children('tbody').append(rows[i]); }
    });

    function processBatch(batch) {
        batch.each(function() {
            var row = $(this);
            var folderName = row.data('foldername');

            $.post('?src=<?php echo $sourceIndex; ?>', { getFolderDetails: true, folderName: encodeURIComponent(folderName) }, function(data) {
                try {
                    var details = JSON.parse(data);
                    row.find('.folder-size').text(details.size);
                    row.find('.folder-count').text(details.count);
                    row.find('.folder-date').text(details.newest_file_date);
                    
                    if (details.count === '0') {
                        row.addClass('status-empty');
                        row.find('.empty-badge').removeClass('d-none');
                    } else {
                        row.removeClass('status-empty');
                        row.find('.empty-badge').addClass('d-none');
                    }

                    if ($('#statusFilter').val() === 'status-empty') applyFilters();
                } catch (e) {}
            });
        });
    }

    var batchSize = 10;
    var rowsToUpdate = $('.needs-update');
    for (var i = 0; i < rowsToUpdate.length; i += batchSize) {
        (function(i) {
            setTimeout(function() { processBatch(rowsToUpdate.slice(i, i + batchSize)); }, (i / batchSize) * 600);
        })(i);
    }

    $('#clearCacheForm').on('submit', function(e) {
        e.preventDefault();
        $.post('?src=<?php echo $sourceIndex; ?>', $(this).serialize(), function(response) { location.reload(); });
    });

});

// 7. Move / Delete Action Submitter with Advanced Progress & Web Server Timeout Safety
function submitAction(type) {
    if ($('.folder-checkbox:checked').length === 0) {
        alert('Please select at least one folder.');
        return;
    }
    
    if (type === 'move' && $('#targetFolder').val() === '') {
        alert('Please select a target destination to move files.');
        return;
    }

    if(type === 'delete' && !confirm('Are you absolutely sure you want to PERMANENTLY delete the selected folders?')) {
        return;
    }

    $('#action_type').val(type);
    
    var taskId = Date.now() + Math.random().toString(36).substr(2, 9);
    $('#task_id').val(taskId);
    
    // Reset and Show Modal
    var modal = new bootstrap.Modal(document.getElementById('progressModal'));
    $('#progressModalTitle').html('<i class="bi bi-gear-wide-connected text-primary me-2"></i>Processing Request...');
    $('#overallBar').css('width', '0%').addClass('progress-bar-animated');
    $('#fileBar').css('width', '0%');
    $('#overallText').text('0 / 0 Files Processed (0%)');
    $('#currentFolderText').text('Preparing operation...');
    $('#currentFileText').text('Scanning size limits...');
    $('#progressText').html('Authenticating and compiling file lists...<br>');
    $('#closeModalBtn').addClass('d-none');
    
    modal.show();

    // Fast Advanced JSON Poller (Controls entire completion flow!)
    var progressInterval = setInterval(function() {
        $.post('?src=<?php echo $sourceIndex; ?>', { action: 'getProgress', task_id: taskId }, function(data) {
            try {
                var state = JSON.parse(data);
                if (state && typeof state === 'object') {
                    
                    if (state.status === 'completed') {
                        // BACKEND SIGNALED COMPLETION!
                        clearInterval(progressInterval);
                        
                        $('#overallBar').css('width', '100%').removeClass('progress-bar-animated');
                        $('#fileBar').css('width', '100%');
                        $('#overallText').text('100% Complete');
                        $('#currentFolderText').text('Finished');
                        $('#currentFileText').text('All tasks finished.');
                        
                        $('#progressText').html(state.log + "<br><span class='text-success fw-bold mt-2 d-inline-block'>Operation complete.</span>");
                        var logElem = document.getElementById("progressText");
                        logElem.scrollTop = logElem.scrollHeight;

                        $('#progressModalTitle').html('<i class="bi bi-check-circle-fill text-success me-2"></i>Operation Complete');
                        $('#closeModalBtn').removeClass('d-none');

                    } else {
                        // Update Overall UI
                        $('#overallBar').css('width', state.overall_progress + '%');
                        $('#overallText').text(state.copied_files + ' / ' + state.overall_files + ' Files (' + state.overall_progress + '%)');
                        
                        // Update Folder & File targets
                        if(state.current_folder) $('#currentFolderText').text(state.current_folder);
                        if(state.current_file) $('#currentFileText').text(state.current_file);
                        
                        // Smooth per-file progress UI
                        $('#fileBar').css('width', state.current_file_progress + '%');
                    }
                }
            } catch(e) {} // Silently ignore if JSON isn't fully written yet
        });
    }, 500); 

    // Actual Action Request Executor (Fire and Forget)
    $.post('?src=<?php echo $sourceIndex; ?>', $('#actionForm').serialize())
        .fail(function() {
            // Nginx proxy timeout (504). Ignore it. The PHP process handles its own completion via JSON poller.
            console.log("Main HTTP request disconnected (expected on long copies). Relying on JSON background poller.");
        });
}
</script>
</body>
</html>