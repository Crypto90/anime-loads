<?php
$configFile = '/config/config.json';
if (!file_exists($configFile)) {
    header('Location: setup.php');
    exit;
}
$config = json_decode(file_get_contents($configFile), true);
$base_dir = $config['base_dir'];

session_start(); // Start the session to track progress
set_time_limit(0); // Set unlimited execution time

// Define the source path and target paths
$sourcePath = '/volume1/video/Anime (Ger)'; // Change this to your source path
$targetPaths = [
    '/volumeUSB3/usbshare/video/Anime (Ger)/',
    '/volumeUSB4/usbshare/video/Anime (Ger)/',
    '/volumeUSB5/usbshare/video/Anime (Ger)/',
    '/volumeUSB6/usbshare/video/Anime (Ger)/',
    '/volumeUSB7/usbshare/video/Anime (Ger)/',
    '/volumeUSB8/usbshare/video/Anime (Ger)/',
    '/volumeUSB9/usbshare/video/Anime (Ger)/',
    '/volume2/hdd_intern/video/Anime (Ger)/'
];

// Path to the JSON file
$jsonFilePath = $base_dir . '/ani.json';

// Function to get the size of a folder
function folderSize($dir) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

// Function to format bytes
function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } else {
        $bytes = $bytes . ' bytes';
    }
    return $bytes;
}

// Function to count files in a directory
function countFiles($dir) {
    return iterator_count(new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS));
}

// Function to get the modify date of the newest file in a folder
function newestFileDate($dir) {
    $newestFileTime = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getMTime() > $newestFileTime) {
            $newestFileTime = $file->getMTime();
        }
    }
    return date('Y-m-d H:i:s', $newestFileTime);
}

// Function to recursively copy a folder and its contents
function copyFolder($source, $destination) {
    if (!is_dir($destination)) {
        mkdir($destination, 0777, true);
    }

    $totalFiles = iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)));
    $copiedFiles = 0;
    $_SESSION['progress'] = 0; // Reset progress

    foreach (scandir($source) as $item) {
        if ($item === '.' || $item === '..') continue;

        $srcPath = $source . '/' . $item;
        $destPath = $destination . '/' . $item;

        if (is_dir($srcPath)) {
            if (!copyFolder($srcPath, $destPath)) {
                return false;
            }
        } else {
            if (copy($srcPath, $destPath)) {
                $copiedFiles++;
                $_SESSION['progress'] = ($copiedFiles / $totalFiles) * 100; // Update progress
            } else {
                return false;
            }
        }
    }

    $_SESSION['progress'] = 100; // Complete progress
    return true;
}

// Function to delete folder
function deleteFolder($dir) {
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = $dir . '/' . $item;

        if (is_dir($path)) {
            deleteFolder($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Handle AJAX requests to get progress
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'getProgress') {
    echo json_encode(['progress' => $_SESSION['progress'] ?? 0]);
    exit;
}

// Handle form submission for moving folders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selectedFolders'], $_POST['targetFolder'])) {
    $selectedFolders = $_POST['selectedFolders'];
    $targetFolder = $_POST['targetFolder'];

    foreach ($selectedFolders as $folder) {
        $sourceFolder = $sourcePath . '/' . basename($folder);
        $destinationFolder = $targetFolder . '/' . basename($folder);

        // Copy the folder with progress tracking
        if (copyFolder($sourceFolder, $destinationFolder)) {
            deleteFolder($sourceFolder); // Only delete after successful copy
            echo "Successfully moved $folder to $targetFolder.<br>";
        } else {
            echo "Failed to move $folder.<br>";
        }
    }
    exit;
}

// Handle form submission for deleting folders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteFolders'])) {
    $deleteFolders = $_POST['deleteFolders'];

    foreach ($deleteFolders as $folder) {
        $folderPath = $sourcePath . '/' . basename($folder);
        deleteFolder($folderPath); // Delete the folder
        echo "Successfully deleted $folder.<br>";
    }
    exit;
}

// Function to check if folder name exists in the JSON file
function folderExistsInJson($folderName, $jsonFilePath) {
    if (!file_exists($jsonFilePath)) {
        return false;
    }

    $jsonData = file_get_contents($jsonFilePath);
    return strpos($jsonData, $folderName) !== false;
}

// Fetch folders from the source path
$folders = array_filter(glob($sourcePath . '/*'), 'is_dir');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Folder Manager with Progress Bar</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        #progressContainer {
            display: none;
            margin-top: 20px;
        }
        #progressBar {
            width: 0;
            height: 30px;
            background-color: #4caf50;
        }
        .folder-not-exists {
            background-color: darkgreen;
            color: white;
        }
    </style>
</head>
<body>
    <h1>Folder Manager</h1>
    <form method="post" id="moveForm">
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Select</th>
                    <th>Folder Name</th>
                    <th>Size</th>
                    <th>File Count</th>
                    <th>Newest File Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($folders as $folder): ?>
                    <tr class="<?php echo !folderExistsInJson(basename($folder), $jsonFilePath) ? 'folder-not-exists' : ''; ?>">
                        <td><input type="checkbox" name="selectedFolders[]" value="<?php echo basename($folder); ?>"></td>
                        <td><?php echo basename($folder); ?></td>
                        <td><?php echo formatSize(folderSize($folder)); ?></td>
                        <td><?php echo countFiles($folder); ?></td>
                        <td><?php echo newestFileDate($folder); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <label for="targetFolder">Move to:</label>
        <select name="targetFolder" id="targetFolder" required>
            <option value="">Select Target Folder</option>
            <?php foreach ($targetPaths as $path): ?>
                <option value="<?php echo $path; ?>"><?php echo $path; ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" id="moveBtn">Move</button>
    </form>

    <form method="post" id="deleteForm">
        <h2>Delete Selected Folders</h2>
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Select</th>
                    <th>Folder Name</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($folders as $folder): ?>
                    <tr>
                        <td><input type="checkbox" name="deleteFolders[]" value="<?php echo basename($folder); ?>"></td>
                        <td><?php echo basename($folder); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <button type="submit" id="deleteBtn">Delete</button>
    </form>

    <!-- Progress Bar -->
    <div id="progressContainer">
        <label>Progress:</label>
        <div style="width: 100%; background-color: #ddd;">
            <div id="progressBar"></div>
        </div>
        <div id="progressText"></div>
    </div>

    <script>
        // Handle form submission for moving folders
        $('#moveForm').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            // Show progress container
            $('#progressContainer').show();
            $('#progressBar').width('0%');
            $('#progressText').text('');

            // Submit the form via AJAX
            $.post($(this).attr('action'), $(this).serialize(), function(response) {
                // Show success messages
                $('#progressText').append(response + '<br>');
                $('#moveForm')[0].reset(); // Reset the form

                // Poll for progress
                setInterval(getProgress, 1000);
            });
        });

        // Function to get progress
        function getProgress() {
            $.post('', { action: 'getProgress' }, function(data) {
                const progress = JSON.parse(data).progress;
                $('#progressBar').width(progress + '%');
                $('#progressText').text('Progress: ' + progress + '%');

                if (progress >= 100) {
                    clearInterval(this);
                }
            });
        }
    </script>
</body>
</html>
