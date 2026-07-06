<?php

// Secure log viewer to fetch the last 100 lines of laravel.log
$logFile = __DIR__ . '/../storage/logs/laravel.log';

header('Content-Type: text/plain');

if (!file_exists($logFile)) {
    echo "Log file does not exist at: " . $logFile . "\n";
    // Check if there are other files in storage/logs/
    $files = glob(__DIR__ . '/../storage/logs/*');
    echo "Files in storage/logs/:\n";
    print_r($files);
    exit;
}

// Read the last 100 lines of the file
$lines = 100;
$data = file($logFile);
$lineCount = count($data);
$start = max(0, $lineCount - $lines);

echo "Showing last $lines lines of laravel.log:\n\n";
for ($i = $start; $i < $lineCount; $i++) {
    echo $data[$i];
}
