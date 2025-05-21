<?php
// Clear any existing RFID scan data
$rfid_data_path = 'rfid_scan.json';
file_put_contents($rfid_data_path, json_encode(["data" => null, "scanned_at" => null]));

$path = "/GCO/read_rfid_and_save.py";
// Execute the Python script
$command = "python $path";
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    pclose(popen("start /B " . $command, "r"));
} else {
    exec($command . " > /dev/null &");
}

// Return success response
header('Content-Type: application/json');
echo json_encode(['success' => true]); 