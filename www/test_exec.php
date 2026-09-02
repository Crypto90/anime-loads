<?php
$script = "import sys\nimport requests\nimport animeloads\nprint('SUCCESS')";
$testScriptPath = '/tmp/test_dbg.py';
file_put_contents($testScriptPath, $script);
$output = shell_exec("python3 " . escapeshellarg($testScriptPath) . " 2>&1");
echo "OUTPUT:\n" . $output;
