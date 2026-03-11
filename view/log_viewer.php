<?php
require_once __DIR__ . '/../lib/auth_check_admin.php';
// Log Viewer - Alternative access to log files
// Use this when direct .log file access is forbidden by server

header('Content-Type: text/html; charset=utf-8');

$logDir = __DIR__ . '/../logs/';
$apiLogDir = __DIR__ . '/../api/';

// Get requested log file
$logFile = $_GET['file'] ?? 'webhook_debug.log';
$lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 100;
$search = $_GET['search'] ?? '';

// Sanitize filename to prevent directory traversal
$logFile = basename($logFile);

// Determine which directory to look in
$filePath = null;
if (file_exists($logDir . $logFile)) {
    $filePath = $logDir . $logFile;
} elseif (file_exists($apiLogDir . $logFile)) {
    $filePath = $apiLogDir . $logFile;
}

// Available log files
$availableLogs = [];
foreach (glob($logDir . '*.log') as $file) {
    $availableLogs[] = basename($file);
}
foreach (glob($apiLogDir . '*.log') as $file) {
    $availableLogs[] = basename($file);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Viewer - Berkeley County Emergency Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.5;
            border-left: 3px solid #e5e7eb;
            padding-left: 1rem;
            margin-bottom: 0.5rem;
        }

        .log-entry:hover {
            background-color: #f3f4f6;
            border-left-color: #3b82f6;
        }

        .log-entry.error {
            border-left-color: #ef4444;
            background-color: #fef2f2;
        }

        .log-entry.warning {
            border-left-color: #f59e0b;
            background-color: #fffbeb;
        }

        .log-entry.success {
            border-left-color: #10b981;
            background-color: #f0fdf4;
        }

        .json-viewer {
            background: #1a1a1a;
            color: #e6e6e6;
            border-radius: 8px;
            padding: 1rem;
            overflow-x: auto;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-6 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-3xl font-bold mb-4">
                <i class="fas fa-file-alt text-blue-600 mr-2"></i>
                Log Viewer
            </h1>

            <!-- Controls -->
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Log File</label>
                    <select name="file" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <?php foreach ($availableLogs as $log): ?>
                            <option value="<?php echo htmlspecialchars($log); ?>" <?php echo $log === $logFile ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($log); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lines to Show</label>
                    <input type="number" name="lines" value="<?php echo $lines; ?>" min="10" max="1000"
                        class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search (optional)</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Filter entries..."
                        class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                </div>
            </form>

            <!-- Log Display -->
            <div class="bg-gray-50 rounded-lg p-4 max-h-96 overflow-y-auto">
                <?php if ($filePath && file_exists($filePath)): ?>
                    <?php
                    // Read file
                    $content = file_get_contents($filePath);
                    $logLines = explode("\n", $content);
                    $logLines = array_reverse(array_filter($logLines)); // Newest first
                    $logLines = array_slice($logLines, 0, $lines);

                    if (empty($logLines)):
                    ?>
                        <p class="text-gray-500 text-center py-8">No log entries found</p>
                    <?php else: ?>
                        <?php foreach ($logLines as $line): ?>
                            <?php
                            // Skip if search term provided and line doesn't match
                            if ($search && stripos($line, $search) === false) {
                                continue;
                            }

                            // Try to parse as JSON
                            $json = json_decode($line, true);
                            $class = '';

                            if ($json) {
                                // Determine class based on action or error
                                if (isset($json['action'])) {
                                    if (strpos($json['action'], 'error') !== false) {
                                        $class = 'error';
                                    } elseif (strpos($json['action'], 'success') !== false) {
                                        $class = 'success';
                                    }
                                }
                            } else {
                                // Plain text log - check for keywords
                                if (stripos($line, 'error') !== false || stripos($line, 'fail') !== false) {
                                    $class = 'error';
                                } elseif (stripos($line, 'warn') !== false) {
                                    $class = 'warning';
                                } elseif (stripos($line, 'success') !== false) {
                                    $class = 'success';
                                }
                            }
                            ?>
                            <div class="log-entry <?php echo $class; ?>">
                                <?php if ($json): ?>
                                    <div class="mb-2">
                                        <span class="font-semibold text-gray-700">
                                            <?php echo htmlspecialchars($json['timestamp'] ?? 'N/A'); ?>
                                        </span>
                                        <span class="text-blue-600 ml-2">
                                            <?php echo htmlspecialchars($json['action'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                                    <details class="cursor-pointer">
                                        <summary class="text-sm text-gray-600 hover:text-gray-800">View details</summary>
                                        <pre class="json-viewer mt-2 text-xs"><?php echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?></pre>
                                    </details>
                                <?php else: ?>
                                    <pre class="whitespace-pre-wrap break-words"><?php echo htmlspecialchars($line); ?></pre>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-red-500 text-center py-8">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Log file not found: <?php echo htmlspecialchars($logFile); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- File Info -->
            <?php if ($filePath && file_exists($filePath)): ?>
                <div class="mt-4 text-sm text-gray-600 flex justify-between">
                    <span>File: <?php echo htmlspecialchars(realpath($filePath)); ?></span>
                    <span>Size: <?php echo number_format(filesize($filePath) / 1024, 2); ?> KB</span>
                    <span>Modified: <?php echo date('Y-m-d H:i:s', filemtime($filePath)); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <div class="text-center">
            <a href="../index.html" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
        </div>
    </div>

    <script>
        // Auto-refresh every 10 seconds if checkbox is checked
        setInterval(() => {
            const url = new URL(window.location.href);
            if (url.searchParams.get('auto_refresh') === '1') {
                window.location.reload();
            }
        }, 10000);
    </script>
</body>

</html>