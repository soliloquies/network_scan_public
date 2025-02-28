<?php
session_start();
if (!isset($_SESSION['token'])) {
    header("Location: /login.php");
    exit;
}
$host = $_SERVER['HTTP_HOST']; // Get hostname and port (e.g., scan1.xiaoxqian.xyz:8443)
$api_base_url = "https://$host/api"; // Dynamic API base URL

$ch = curl_init("$api_base_url/config");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $_SESSION['token']]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);
$config = json_decode($response, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Config - Network Scanner</title>
    <link href="/static/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Network Scanner</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="/import.php">Import</a></li>
                    <li class="nav-item"><a class="nav-link" href="/status.php">Status</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/config.php">Config</a></li>
                    <li class="nav-item"><a class="nav-link" href="/history.php">History</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h1 class="card-title mb-0">Current Configuration Parameters</h1>
            </div>
            <div class="card-body">
                <p class="mb-4">Below are the current settings used for scanning. Upload a new config via the Import page to change them.</p>
                <pre class="bg-light p-4 rounded"><?php echo json_encode($config, JSON_PRETTY_PRINT); ?></pre>
            </div>
        </div>
    </div>
    <script src="/static/js/bootstrap.min.js"></script>
</body>
</html>
