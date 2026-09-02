<?php
header('Content-Type: application/json');

if (!isset($_POST['action'])) {
    echo json_encode(['success' => false, 'error' => 'No action specified']);
    exit;
}

$action = $_POST['action'];
$configFile = '/config/config.json';
$aniFile = '/config/ani.json';

// Helper for sending json response
function sendRes($success, $log, $error = '') {
    echo json_encode(['success' => $success, 'log' => trim($log), 'error' => $error]);
    exit;
}

if ($action === 'test_fs') {
    $log = "";
    $success = true;
    
    // Check config dir
    if (is_dir('/config')) {
        $log .= "/config is present.\n";
        if (is_writable('/config')) {
            $log .= "/config is writable.\n";
        } else {
            $log .= "/config is NOT writable by www-data.\n";
            $success = false;
        }
    } else {
        $log .= "/config directory is missing!\n";
        $success = false;
    }
    
    // Load config to check other dirs
    if (file_exists($configFile)) {
        $cfg = json_decode(file_get_contents($configFile), true);
        $dldir = $cfg['jd_download_dir'] ?? '/downloads';
        $viddir = $cfg['main_storage_dir'] ?? '';
        
        if (is_dir($dldir)) {
            $log .= "Download dir ($dldir) is present.\n";
            if (!is_writable($dldir)) {
                $log .= "Download dir ($dldir) is NOT writable.\n";
                $success = false;
            } else {
                $log .= "Download dir ($dldir) is writable.\n";
            }
        } else {
            $log .= "Download dir ($dldir) is missing!\n";
            $success = false;
        }
        
        if (!empty($viddir)) {
            if (is_dir($viddir)) {
                $log .= "Video storage dir ($viddir) is present.\n";
                if (!is_writable($viddir)) {
                    $log .= "Video storage dir ($viddir) is NOT writable.\n";
                    $success = false;
                }
            } else {
                $log .= "Video storage dir ($viddir) is missing!\n";
                $success = false;
            }
        }
    } else {
        $log .= "Cannot test download/video dirs because config.json is missing.\n";
    }

    sendRes($success, $log, $success ? '' : 'Permission or missing directory issues');
}

if ($action === 'test_config') {
    $log = "";
    $success = true;
    
    if (file_exists($configFile)) {
        $log .= "config.json found.\n";
        $cfg = json_decode(file_get_contents($configFile), true);
        if ($cfg === null) {
            $log .= "config.json is INVALID (JSON parse error).\n";
            $success = false;
        } else {
            $log .= "config.json parsed successfully. Base Dir: " . ($cfg['base_dir'] ?? 'N/A') . "\n";
        }
    } else {
        $log .= "config.json is missing!\n";
        $success = false;
    }
    
    if (file_exists($aniFile)) {
        $log .= "ani.json found.\n";
        $ani = json_decode(file_get_contents($aniFile), true);
        if ($ani === null) {
            $log .= "ani.json is INVALID (JSON parse error).\n";
            $success = false;
        } else {
            $log .= "ani.json parsed successfully.\n";
            $log .= "Active Anime count: " . count($ani['anime'] ?? []) . "\n";
        }
    } else {
        $log .= "ani.json is missing!\n";
        $success = false;
    }
    
    sendRes($success, $log, $success ? '' : 'Configuration missing or corrupted');
}

if ($action === 'test_deps') {
    $log = "";
    $success = true;
    
    // Check python3
    $py = trim(shell_exec('which python3'));
    if (!empty($py)) {
        $log .= "Python3 found at: $py\n";
        $py_ver = trim(shell_exec('python3 --version 2>&1'));
        $log .= "Version: $py_ver\n";
    } else {
        $log .= "Python3 is NOT found in PATH!\n";
        $success = false;
    }
    
    // Check geckodriver
    $gecko = trim(shell_exec('which geckodriver'));
    if (!empty($gecko)) {
        $log .= "Geckodriver found at: $gecko\n";
        $gecko_ver = trim(shell_exec('geckodriver --version | head -n 1'));
        $log .= "Version: $gecko_ver\n";
    } else {
        $log .= "Geckodriver is NOT found in PATH!\n";
        $success = false;
    }
    
    // Check firefox
    $ff = trim(shell_exec('which firefox'));
    if (!empty($ff)) {
        $log .= "Firefox found at: $ff\n";
        $ff_ver = trim(shell_exec('firefox --version 2>&1'));
        $log .= "Version: $ff_ver\n";
    } else {
        $log .= "Firefox is NOT found in PATH!\n";
        $success = false;
    }
    
    // Check cron
    $cron = trim(shell_exec('ps aux | grep cron | grep -v grep'));
    if (!empty($cron)) {
        $log .= "Cron service is running.\n";
    } else {
        $log .= "Cron service is NOT running!\n";
        $success = false;
    }
    
    sendRes($success, $log, $success ? '' : 'Missing dependencies or services');
}

if ($action === 'test_jd') {
    $log = "";
    if (!file_exists($configFile)) {
        sendRes(false, "config.json is missing. Cannot test JD.", "Missing config");
    }
    
    $cfg = json_decode(file_get_contents($configFile), true);
    $email = $cfg['jd_email'] ?? '';
    $password = $cfg['jd_password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendRes(false, "No JDownloader credentials found in config.", "Missing credentials");
    }
    
    $log .= "Testing MyJDownloader connection for: $email\n";
    
    $cmd = sprintf('python3 /var/www/html/jd_verify.py %s %s 2>&1', escapeshellarg($email), escapeshellarg($password));
    $output = shell_exec($cmd);
    
    if ($output === null) {
        $log .= "Failed to execute jd_verify.py\n";
        sendRes(false, $log, "Execution failed");
    }
    
    $result = json_decode($output, true);
    if ($result === null) {
        $log .= "Invalid response from jd_verify.py: \n$output\n";
        sendRes(false, $log, "Invalid API response");
    }
    
    if (isset($result['success']) && $result['success']) {
        $log .= "Authentication SUCCESSFUL!\n";
        if (isset($result['devices'])) {
            $log .= "Available Devices: " . implode(', ', $result['devices']) . "\n";
        }
        sendRes(true, $log);
    } else {
        $log .= "Authentication FAILED: " . ($result['error'] ?? 'Unknown error') . "\n";
        sendRes(false, $log, $result['error'] ?? 'Authentication failed');
    }
}

if ($action === 'test_selenium') {
    $log = "Running headless Selenium test...\n";
    
    $script = "import sys\n"
            . "from selenium import webdriver\n"
            . "from selenium.webdriver.firefox.options import Options\n"
            . "try:\n"
            . "    options = Options()\n"
            . "    options.add_argument('--headless')\n"
            . "    options.add_argument('--no-sandbox')\n"
            . "    options.add_argument('--disable-dev-shm-usage')\n"
            . "    driver = webdriver.Firefox(options=options)\n"
            . "    driver.get('https://example.com')\n"
            . "    title = driver.title\n"
            . "    driver.quit()\n"
            . "    print(f'SUCCESS: Loaded page {title}')\n"
            . "except Exception as e:\n"
            . "    print(f'ERROR: {str(e)}')\n"
            . "    sys.exit(1)\n";
            
    $testScriptPath = sys_get_temp_dir() . '/test_selenium_' . time() . '.py';
    file_put_contents($testScriptPath, $script);
    
    $output = shell_exec("python3 " . escapeshellarg($testScriptPath) . " 2>&1");
    @unlink($testScriptPath);
    
    if (strpos($output, 'SUCCESS') !== false) {
        $log .= "Selenium test completed successfully.\n";
        $log .= "Output: $output\n";
        sendRes(true, $log);
    } else {
        $log .= "Selenium test failed.\n";
        $log .= "Output: $output\n";
        sendRes(false, $log, "Selenium engine failure");
    }
}

if ($action === 'test_e2e') {
    $log = "Running E2E Anime-Loads Scraping Test...\n";
    
    $script = "import sys\n"
            . "import json\n"
            . "sys.path.append('/var/www/html')\n" // Make sure animeloads can be imported
            . "try:\n"
            . "    import animeloads\n"
            . "except ImportError as e:\n"
            . "    print(f'ERROR: Could not import animeloads: {e}')\n"
            . "    sys.exit(1)\n"
            . "\n"
            . "try:\n"
            . "    print('Initializing Animeloads object (Headless Firefox)...')\n"
            . "    al = animeloads.Animeloads(browser='Firefox')\n"
            . "    print('Testing Search function for \"Naruto\"...')\n"
            . "    results = al.search('Naruto')\n"
            . "    if not results:\n"
            . "        print('ERROR: Search returned no results.')\n"
            . "        sys.exit(1)\n"
            . "    \n"
            . "    anime = results[0]\n"
            . "    print(f'Found Anime: {anime.getName()} ({anime.getTyp()})')\n"
            . "    print('Fetching details and releases...')\n"
            . "    anime.updateInfo()\n"
            . "    releases = anime.getReleases()\n"
            . "    if not releases:\n"
            . "        print('ERROR: No releases found for this anime.')\n"
            . "        sys.exit(1)\n"
            . "    \n"
            . "    release = releases[0]\n"
            . "    print(f'Extracting links for Release: {release.getGroup()} - {release.getResolution()}')\n"
            . "    print('Executing captchas (simulated/automated)...')\n"
            . "    \n"
            . "    # Mock the addToMYJD function to prevent actual payload being sent to JD\n"
            . "    original_add = animeloads.Animeloads.addToMYJD\n"
            . "    mock_called = False\n"
            . "    def mock_add(myjd_user, myjd_pass, myjd_device, links, pkgName, pwd, destinationFolder=None):\n"
            . "        global mock_called\n"
            . "        mock_called = True\n"
            . "        print(f'MOCK JD INTERCEPT: Received {len(links)} links for package {pkgName}')\n"
            . "    animeloads.Animeloads.addToMYJD = staticmethod(mock_add)\n"
            . "    \n"
            . "    # Try downloading episode 1 of this release\n"
            . "    anime.downloadEpisode('1', release, 'ddownload', 'Firefox', '', '', '', '', '')\n"
            . "    \n"
            . "    print('SUCCESS: E2E Scraping and Link Extraction completed.')\n"
            . "except Exception as e:\n"
            . "    print(f'ERROR: {str(e)}')\n"
            . "    sys.exit(1)\n";
            
    $testScriptPath = sys_get_temp_dir() . '/test_e2e_' . time() . '.py';
    file_put_contents($testScriptPath, $script);
    
    // We run it with a timeout of 45 seconds as it can take some time
    $output = shell_exec("timeout 45 python3 " . escapeshellarg($testScriptPath) . " 2>&1");
    @unlink($testScriptPath);
    
    if (strpos($output, 'SUCCESS') !== false) {
        $log .= "E2E Test completed successfully.\n";
        $log .= "Output:\n$output\n";
        sendRes(true, $log);
    } else {
        $log .= "E2E Test failed or timed out.\n";
        $log .= "Output:\n$output\n";
        sendRes(false, $log, "E2E scraping failure");
    }
}

sendRes(false, "", "Invalid action");
