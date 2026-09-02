<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anime-Loads Diagnostics</title>
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
            padding: 40px 20px;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .test-card {
            background: rgba(25, 30, 40, 0.7);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 40px;
            margin: 0 auto;
            max-width: 900px;
        }

        .test-title {
            font-weight: 800;
            background: -webkit-linear-gradient(45deg, #00c6ff, #0072ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 30px;
            text-align: center;
        }

        .btn-custom {
            background: linear-gradient(45deg, #00c6ff, #0072ff);
            border: none;
            color: #fff;
            font-weight: bold;
            border-radius: 50px;
            padding: 12px 30px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 198, 255, 0.4);
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 198, 255, 0.6);
            color: #fff;
        }
        
        .btn-secondary-custom {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            border-radius: 50px;
            padding: 8px 20px;
            transition: all 0.3s ease;
        }

        .btn-secondary-custom:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .list-group-item {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            border-radius: 10px !important;
            transition: all 0.2s ease;
        }
        
        .list-group-item:hover {
            background: rgba(0, 0, 0, 0.4);
        }

        .status-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .status-pending { color: #6c757d; }
        .status-running { color: #00c6ff; }
        .status-success { color: #28a745; }
        .status-error { color: #dc3545; }

        .log-container {
            background: #0d1117;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            color: #58a6ff;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 0.85rem;
            height: 350px;
            overflow-y: auto;
            white-space: pre-wrap;
            margin-top: 20px;
        }
        
        .nav-link-back {
            color: #b8c6db;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
            transition: color 0.2s;
        }
        .nav-link-back:hover {
            color: #fff;
        }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="nav-link-back"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
    
    <div class="test-card">
        <h2 class="test-title"><i class="bi bi-activity"></i> System Diagnostics Suite</h2>
        <p class="text-center text-muted mb-4">Run comprehensive tests to verify your environment, dependencies, and configuration.</p>
        
        <div class="text-center mb-4">
            <button id="btnRunTests" class="btn btn-custom btn-lg" onclick="startDiagnostics()">
                <i class="bi bi-play-circle"></i> Run Full Diagnostics
            </button>
        </div>

        <div class="row">
            <div class="col-md-5">
                <h5 class="mb-3 text-info">Test Suite</h5>
                <ul class="list-group list-group-flush" id="testList">
                    <li class="list-group-item" id="test-fs">
                        <span>1. File System & Permissions</span>
                        <i class="bi bi-dash-circle status-icon status-pending" id="icon-fs"></i>
                    </li>
                    <li class="list-group-item" id="test-config">
                        <span>2. Configuration Integrity</span>
                        <i class="bi bi-dash-circle status-icon status-pending" id="icon-config"></i>
                    </li>
                    <li class="list-group-item" id="test-deps">
                        <span>3. Dependencies & Processes</span>
                        <i class="bi bi-dash-circle status-icon status-pending" id="icon-deps"></i>
                    </li>
                    <li class="list-group-item" id="test-jd">
                        <span>4. JDownloader API</span>
                        <i class="bi bi-dash-circle status-icon status-pending" id="icon-jd"></i>
                    </li>
                    <li class="list-group-item" id="test-selenium">
                        <span>5. Selenium Engine</span>
                        <i class="bi bi-dash-circle status-icon status-pending" id="icon-selenium"></i>
                    </li>
                    <li class="list-group-item" id="test-e2e">
                        <span>6. Anime-Loads E2E Scraping</span>
                        <i class="bi bi-dash-circle status-icon status-pending" id="icon-e2e"></i>
                    </li>
                </ul>
            </div>
            
            <div class="col-md-7">
                <div class="d-flex justify-content-between align-items-end mb-2 mt-4 mt-md-0">
                    <h5 class="text-info mb-0">Diagnostic Logs</h5>
                    <button class="btn btn-secondary-custom btn-sm" onclick="copyLogs()">
                        <i class="bi bi-clipboard"></i> Copy Logs
                    </button>
                </div>
                <div class="log-container" id="logOutput">System ready. Awaiting diagnostic run...</div>
            </div>
        </div>
    </div>
</div>

<script>
    const tests = [
        { id: 'fs', name: 'File System & Permissions', action: 'test_fs' },
        { id: 'config', name: 'Configuration Integrity', action: 'test_config' },
        { id: 'deps', name: 'Dependencies & Processes', action: 'test_deps' },
        { id: 'jd', name: 'JDownloader API', action: 'test_jd' },
        { id: 'selenium', name: 'Selenium Engine', action: 'test_selenium' },
        { id: 'e2e', name: 'Anime-Loads E2E Scraping', action: 'test_e2e' }
    ];

    let fullLog = "System ready. Awaiting diagnostic run...\n";

    function appendLog(text, type = 'info') {
        const logBox = document.getElementById('logOutput');
        const timestamp = new Date().toISOString().split('T')[1].split('.')[0];
        let prefix = `[${timestamp}] `;
        
        let color = '#58a6ff'; // default blue
        if (type === 'error') color = '#ff7b72'; // red
        if (type === 'success') color = '#3fb950'; // green
        if (type === 'warn') color = '#d29922'; // yellow
        if (type === 'system') color = '#8b949e'; // gray
        
        const logLine = `<span style="color: ${color}">${prefix}${text}</span><br>`;
        
        if (fullLog === "System ready. Awaiting diagnostic run...\n") {
            fullLog = "";
            logBox.innerHTML = "";
        }
        
        fullLog += `${prefix}${text}\n`;
        logBox.innerHTML += logLine;
        logBox.scrollTop = logBox.scrollHeight;
    }

    function setIconStatus(id, status) {
        const icon = document.getElementById(`icon-${id}`);
        icon.className = "status-icon"; // reset
        
        if (status === 'pending') {
            icon.classList.add('bi', 'bi-dash-circle', 'status-pending');
        } else if (status === 'running') {
            icon.classList.add('spinner-border', 'spinner-border-sm', 'status-running');
        } else if (status === 'success') {
            icon.classList.add('bi', 'bi-check-circle-fill', 'status-success');
        } else if (status === 'error') {
            icon.classList.add('bi', 'bi-x-circle-fill', 'status-error');
        }
    }

    async function runSingleTest(test) {
        setIconStatus(test.id, 'running');
        appendLog(`Starting test: ${test.name}`, 'system');
        
        try {
            const formData = new FormData();
            formData.append('action', test.action);
            
            const response = await fetch('test_ajax.php', { method: 'POST', body: formData });
            let data;
            
            try {
                data = await response.json();
            } catch (jsonErr) {
                const textOutput = await response.text();
                appendLog(`Server returned invalid JSON: ${textOutput}`, 'error');
                setIconStatus(test.id, 'error');
                return false;
            }
            
            if (data.log) {
                const lines = data.log.split('\n');
                lines.forEach(l => {
                    if (l.trim() !== '') appendLog(`  > ${l}`, data.success ? 'info' : 'warn');
                });
            }

            if (data.success) {
                setIconStatus(test.id, 'success');
                appendLog(`[PASS] ${test.name} passed successfully.`, 'success');
                return true;
            } else {
                setIconStatus(test.id, 'error');
                appendLog(`[FAIL] ${test.name} failed: ${data.error}`, 'error');
                return false;
            }
            
        } catch (error) {
            setIconStatus(test.id, 'error');
            appendLog(`[FATAL] Network error during ${test.name}: ${error.message}`, 'error');
            return false;
        }
    }

    async function startDiagnostics() {
        const btn = document.getElementById('btnRunTests');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Running...';
        
        // Reset state
        fullLog = "";
        document.getElementById('logOutput').innerHTML = "";
        tests.forEach(t => setIconStatus(t.id, 'pending'));
        
        appendLog("=== BEGIN DIAGNOSTIC RUN ===", 'system');
        
        let allPassed = true;
        for (let test of tests) {
            const passed = await runSingleTest(test);
            if (!passed) allPassed = false;
        }
        
        appendLog("=== DIAGNOSTIC RUN COMPLETE ===", 'system');
        
        if (allPassed) {
            appendLog("Result: ALL TESTS PASSED. The system is healthy.", 'success');
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Tests Passed';
            btn.classList.replace('btn-custom', 'btn-success');
        } else {
            appendLog("Result: ONE OR MORE TESTS FAILED. Please review the logs.", 'error');
            btn.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Tests Failed';
            btn.classList.replace('btn-custom', 'btn-danger');
        }
        
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-circle"></i> Run Full Diagnostics';
            btn.classList.remove('btn-success', 'btn-danger');
            btn.classList.add('btn-custom');
        }, 5000);
    }

    function copyLogs() {
        const el = document.createElement('textarea');
        el.value = fullLog;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        alert('Diagnostic logs copied to clipboard!');
    }
</script>

</body>
</html>
