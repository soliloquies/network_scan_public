<?php
session_start();
if (!isset($_SESSION['token'])) {
    header("Location: /login.php");
    exit;
}
$host = $_SERVER['HTTP_HOST']; // Get current host (e.g., scan1.xiaoxqian.xyz:8443)
$api_base_url = "https://$host/api"; // Dynamic API base URL
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status - Network Scanner</title>
    <link href="/static/css/bootstrap.min.css" rel="stylesheet">
    <script src="/static/js/jquery.min.js"></script>
    <script src="/static/js/bootstrap.min.js"></script>
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
                    <li class="nav-item"><a class="nav-link active" href="/status.php">Status</a></li>
                    <li class="nav-item"><a class="nav-link" href="/config.php">Config</a></li>
                    <li class="nav-item"><a class="nav-link" href="/history.php">History</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h1 class="card-title mb-0">Scan Status</h1>
            </div>
            <div class="card-body">
                <p class="mb-2">Status: <span id="status" class="fw-bold">Idle</span></p>
                <p class="mb-4">Total IPs: <span id="total_ips" class="fw-bold">0</span> | Completed: <span id="completed_ips" class="fw-bold">0</span></p>
                <div class="progress mb-4" style="height: 20px;">
                    <div id="progress_bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                </div>
                <button id="start_scan" class="btn btn-primary w-100">Start Scan</button>
                <table class="table table-striped mt-4">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>System Name</th>
                            <th>Vendor</th>
                            <th>Model</th>
                            <th>SNMP Status</th>
                            <th>SSH Status</th>
                            <th>SSH User</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody id="results_table"></tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
    $(document).ready(function() {
        const token = "<?php echo $_SESSION['token']; ?>";
        const apiUrl = "<?php echo $api_base_url; ?>"; // Dynamic API URL from PHP

        function updateStatus() {
            $.ajax({
                url: apiUrl + "/status",
                headers: {"Authorization": "Bearer " + token},
                success: function(data) {
                    $("#status").text(data.running ? "Running" : "Idle");
                    $("#total_ips").text(data.total_ips);
                    $("#completed_ips").text(data.completed_ips);
                    $("#progress_bar").css("width", data.progress + "%").text(Math.round(data.progress * 100) / 100 + "%").attr("aria-valuenow", data.progress);

                    const tbody = $("#results_table");
                    tbody.empty();
                    data.results.forEach(result => {
                        tbody.append(`
                            <tr>
                                <td>${result.ip}</td>
                                <td>${result.sysName || 'N/A'}</td>
                                <td>${result.vendor || 'N/A'}</td>
                                <td>${result.model || 'N/A'}</td>
                                <td>${result.snmp_status}</td>
                                <td>${result.ssh_status}</td>
                                <td>${result.ssh_user || 'N/A'}</td>
                                <td>${result.timestamp}</td>
                            </tr>
                        `);
                    });
                },
                error: function(xhr) {
                    if (xhr.status === 401) {
                        window.location.href = "/login.php";
                    }
                }
            });
        }

        $("#start_scan").click(function() {
            $.ajax({
                url: apiUrl + "/start-scan",
                method: "POST",
                headers: {"Authorization": "Bearer " + token},
                success: function(data) {
                    alert(data.message);
                },
                error: function(xhr) {
                    if (xhr.status === 401) {
                        window.location.href = "/login.php";
                    } else {
                        alert("Failed to start scan");
                    }
                }
            });
        });

        updateStatus();
        setInterval(updateStatus, 2000); // Poll every 2 seconds
    });
    </script>
</body>
</html>
