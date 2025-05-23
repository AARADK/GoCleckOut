<?php
header('Content-Type: application/json');

// Get the action from POST request
$action = $_POST['action'] ?? '';

// Initialize response array
$response = [
    'status' => 'error',
    'message' => 'Invalid action'
];

switch ($action) {
    case 'start':
        // Start the Python script
        $python_script = __DIR__ . '/frontend/trader/read_rfid_and_save.py';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B python \"$python_script\"", "r"));
        } else {
            exec("python3 \"$python_script\" > /dev/null &");
        }
        $response = [
            'status' => 'success',
            'message' => 'RFID scanning started'
        ];
        break;

    case 'stop':
        // Stop the Python script
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("taskkill /F /IM python.exe");
        } else {
            exec("pkill -f read_rfid_and_save.py");
        }
        $response = [
            'status' => 'success',
            'message' => 'RFID scanning stopped'
        ];
        break;

    case 'check':
        // Check if there's new RFID data
        $json_file = __DIR__ . '/frontend/trader/rfid_scan.json';
        if (file_exists($json_file)) {
            $data = json_decode(file_get_contents($json_file), true);
            if ($data && isset($data['rfid'])) {
                $response = [
                    'status' => 'read',
                    'rfid' => $data['rfid'],
                    'url' => "/GCO/frontend/user/product_detail.php?rfid=" . $data['rfid']
                ];
                // Clear the file after reading
                file_put_contents($json_file, json_encode(['rfid' => null, 'data' => null]));
            } else {
                $response = [
                    'status' => 'waiting',
                    'message' => 'No RFID data yet'
                ];
            }
        } else {
            $response = [
                'status' => 'error',
                'message' => 'RFID data file not found'
            ];
        }
        break;

    default:
        $response = [
            'status' => 'error',
            'message' => 'Invalid action'
        ];
}

// Send JSON response
echo json_encode($response);