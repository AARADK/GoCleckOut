<?php
$rfid_data_path = __DIR__ . '/rfid_scan.json';
file_put_contents($rfid_data_path, json_encode(["data" => null, "scanned_at" => null]));

$python_script_path = __DIR__ . '/rfidReader.py';

$command = "python \"$python_script_path\"";
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    pclose(popen("start /B " . $command, "r"));
} else {
    exec($command . " > /dev/null &");
}

// Return success response
header('Content-Type: application/json');
echo json_encode(['success' => true]); 