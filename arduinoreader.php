<?php
session_start();

$python_script = escapeshellarg('C:\\xampp\\htdocs\\GCO\\arduino.py');
$uid_file = 'C:\\xampp\\htdocs\\GCO\\rfid_uid.txt';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'start') {
            // Start the Python script in the background
            pclose(popen("start /B python $python_script", "r"));
            $_SESSION['listening'] = true;
            echo json_encode(['status' => 'listening']);
            exit;
        } elseif ($_POST['action'] === 'stop') {
            // Attempt to kill the python process (simple version)
            exec('taskkill /F /IM python.exe');
            $_SESSION['listening'] = false;
            echo json_encode(['status' => 'stopped']);
            exit;
        } elseif ($_POST['action'] === 'check') {
            if (file_exists($uid_file)) {
                $uid = trim(file_get_contents($uid_file));
                if ($uid) {
                    unlink($uid_file); // Remove after reading
                    $_SESSION['listening'] = false;
                    echo json_encode(['status' => 'read', 'uid' => $uid]);
                    exit;
                }
            }
            echo json_encode(['status' => 'waiting']);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>RFID Listener</title>
    <script>
        let listening = <?php echo isset($_SESSION['listening']) && $_SESSION['listening'] ? 'true' : 'false'; ?>;
        let interval = null;

        function updateButton() {
            document.getElementById('rfidBtn').innerText = listening ? 'Stop Listening' : 'Start Listening';
        }

        function listenRFID() {
            if (!listening) {
                fetch('', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=start'})
                .then(r => r.json()).then(data => {
                    listening = true;
                    updateButton();
                    pollUID();
                });
            } else {
                fetch('', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=stop'})
                .then(r => r.json()).then(data => {
                    listening = false;
                    updateButton();
                    clearInterval(interval);
                });
            }
        }

        function pollUID() {
            interval = setInterval(() => {
                fetch('', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=check'})
                .then(r => r.json()).then(data => {
                    if (data.status === 'read') {
                        clearInterval(interval);
                        listening = false;
                        updateButton();
                        document.getElementById('result').innerText = 'RFID UID: ' + data.uid;
                    }
                });
            }, 1000);
        }

        window.onload = updateButton;
    </script>
</head>
<body>
    <button id="rfidBtn" onclick="listenRFID()">Start Listening</button>
    <div id="result"></div>
</body>
</html>