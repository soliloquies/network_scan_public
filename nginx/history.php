<?php
session_start();
if (!isset($_SESSION['token'])) {
    header("Location: /login.php");
    exit;
}
$api_base_url = "https://scan.xiaoxqian.xyz:8443/api"; // Replace with your domain
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Network Scanner</title>
    <link href="/static/css/bootstrap.min.css" rel="stylesheet">
    <link href="/static/css/datatables.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="/static/js/jquery.min.js"></script>
    <script src="/static/js/bootstrap.bundle.min.js"></script>
    <script src="/static/js/datatables.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f0f4f8, #e0e7ef);
            font-family: 'Poppins', sans-serif;
            color: #2c3e50;
            min-height: 100vh;
        }
        .navbar {
            background: #2c3e50;
            padding: 1rem 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: #ecf0f1 !important;
        }
        .nav-link {
            color: #bdc3c7 !important;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            color: #ecf0f1 !important;
        }
        .container {
            max-width: 1400px;
            margin-top: 2rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            background: #fff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: #fff;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
        }
        .card-title {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 600;
        }
        .card-body {
            padding: 2rem;
        }
        .control-panel {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .sql-input {
            border-radius: 8px;
            border: 1px solid #ced4da;
            padding: 12px;
            font-size: 1rem;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: border-color 0.3s ease;
        }
        .sql-input:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        .btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #3498db;
            border: none;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-success {
            background: #27ae60;
            border: none;
        }
        .btn-success:hover {
            background: #219653;
        }
        .table {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
        }
        th {
            background: #ecf0f1;
            font-weight: 600;
            padding: 12px;
        }
        th input.form-control {
            margin-top: 8px;
            padding: 6px;
            font-size: 0.9rem;
            border-radius: 5px;
            border: 1px solid #bdc3c7;
        }
        td {
            padding: 12px;
            vertical-align: middle;
        }
        .dataTables_wrapper .dt-buttons {
            float: none;
            text-align: right;
            margin-bottom: 1rem;
        }
        .dataTables_length, .dataTables_info {
            margin-top: 1rem;
        }
        .dropdown-menu {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .dropdown-item {
            padding: 8px 15px;
            font-size: 0.95rem;
        }
        .dropdown-item:hover {
            background: #f0f4f8;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Network Scanner</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/import.php">Import</a></li>
                    <li class="nav-item"><a class="nav-link" href="/status.php">Status</a></li>
                    <li class="nav-item"><a class="nav-link" href="/config.php">Config</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/history.php">History</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Scan History</h1>
            </div>
            <div class="card-body">
                <div class="control-panel">
                    <div class="row align-items-end">
                        <div class="col-md-8 mb-3 mb-md-0">
                            <label for="sqlQuery" class="form-label fw-bold">Custom SQL Query (SELECT only):</label>
                            <input type="text" id="sqlQuery" class="form-control sql-input" placeholder="e.g., SELECT * FROM devices WHERE snmp_status = 'Success'">
                        </div>
                        <div class="col-md-4 d-flex justify-content-end gap-2">
                            <button id="runSql" class="btn btn-primary"><i class="fas fa-play"></i> Run</button>
                            <button id="exportBtn" class="btn btn-success"><i class="fas fa-download"></i> Export</button>
                            <div class="dropdown">
                                <button class="btn btn-primary dropdown-toggle" type="button" id="columnToggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-eye"></i> Columns
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="columnToggle">
                                    <li><a class="dropdown-item" href="#" data-column="0">IP</a></li>
                                    <li><a class="dropdown-item" href="#" data-column="1">System Name</a></li>
                                    <li><a class="dropdown-item" href="#" data-column="2">Vendor</a></li>
                                    <li><a class="dropdown-item" href="#" data-column="3">Model</a></li>
                                    <li><a class="dropdown-item" href="#" data-column="4">SNMP Status</a></li>
                                    <li><a class="dropdown-item" href="#" data-column="5">SSH Status</a></li>
                                    <li><a class="dropdown-item" href="#" data-column="6">SSH User</a></li>
                                    <li><a class="dropdown-item" href="#" data-column="7">Timestamp</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <table id="history_table" class="table table-striped">
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
                        <tr>
                            <th><input type="text" class="form-control" placeholder="Filter IP" data-column="0"></th>
                            <th><input type="text" class="form-control" placeholder="Filter SysName" data-column="1"></th>
                            <th><input type="text" class="form-control" placeholder="Filter Vendor" data-column="2"></th>
                            <th><input type="text" class="form-control" placeholder="Filter Model" data-column="3"></th>
                            <th><input type="text" class="form-control" placeholder="Filter SNMP" data-column="4"></th>
                            <th><input type="text" class="form-control" placeholder="Filter SSH" data-column="5"></th>
                            <th><input type="text" class="form-control" placeholder="Filter User" data-column="6"></th>
                            <th><input type="text" class="form-control" placeholder="Filter Time" data-column="7"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
    $(document).ready(function() {
        const token = "<?php echo $_SESSION['token']; ?>";
        const apiUrl = "<?php echo $api_base_url; ?>";
        let currentQuery = null;
        let tableData = [];

        let table = $('#history_table').DataTable({
            ajax: {
                url: apiUrl + "/history",
                headers: {"Authorization": "Bearer " + token},
                dataSrc: function(json) {
                    tableData = json.results;
                    return json.results;
                },
                error: function(xhr) {
                    if (xhr.status === 401) window.location.href = "/login.php";
                }
            },
            columns: [
                { data: "ip" },
                { data: "sysName", defaultContent: "N/A" },
                { data: "vendor", defaultContent: "N/A" },
                { data: "model", defaultContent: "N/A" },
                { data: "snmp_status" },
                { data: "ssh_status" },
                { data: "ssh_user", defaultContent: "N/A" },
                { data: "timestamp" }
            ],
            pageLength: 10,
            responsive: true,
            dom: 'Bfrtip',
            buttons: [], // Remove DataTables buttons, using custom ones
            initComplete: function() {
                this.api().columns().every(function() {
                    var column = this;
                    $('input[data-column="' + column.index() + '"]').on('keyup change', function() {
                        if (column.search() !== this.value) {
                            column.search(this.value).draw();
                        }
                    });
                });
            }
        });

        // Custom SQL Query
        $('#runSql').click(function() {
            var query = $('#sqlQuery').val().trim();
            if (!query.toLowerCase().startsWith("select")) {
                alert("Only SELECT queries are allowed.");
                return;
            }
            $.ajax({
                url: apiUrl + "/custom-sql",
                method: "POST",
                headers: {"Authorization": "Bearer " + token},
                contentType: "text/plain", // Send raw string
                data: query,
                success: function(data) {
                    currentQuery = query;
                    tableData = data.results;
                    table.clear().rows.add(data.results).draw();
                },
                error: function(xhr) {
                    let errorMsg = xhr.responseJSON?.detail || "Unknown error";
                    alert("SQL Query Failed: " + errorMsg);
                }
            });
        });

        // Export Filtered or Queried Data
        $('#exportBtn').click(function() {
            let filteredData = currentQuery ? tableData : table.rows({ search: 'applied' }).data().toArray();
            let visibleColumns = table.columns(':visible')[0].map(index => table.column(index).dataSrc());

            $.ajax({
                url: apiUrl + "/export-history",
                method: "POST",
                headers: {"Authorization": "Bearer " + token},
                contentType: "application/json",
                data: JSON.stringify({ columns: visibleColumns, data: filteredData }),
                xhrFields: { responseType: 'blob' },
                success: function(blob) {
                    let url = window.URL.createObjectURL(blob);
                    let a = document.createElement('a');
                    a.href = url;
                    a.download = 'history_export.xlsx';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                },
                error: function(xhr) {
                    let errorMsg = xhr.responseJSON?.detail || "Unknown error";
                    alert("Export Failed: " + errorMsg);
                }
            });
        });

        // Column Toggle Dropdown
        $('.dropdown-menu a').click(function(e) {
            e.preventDefault();
            let colIdx = $(this).data('column');
            let column = table.column(colIdx);
            column.visible(!column.visible());
            $(this).toggleClass('active');
            $(this).prepend(column.visible() ? '<i class="fas fa-check me-2"></i>' : '<i class="fas fa-times me-2"></i>');
        });
    });
    </script>
</body>
</html>
