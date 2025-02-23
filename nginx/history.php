<?php
session_start();
if (!isset($_SESSION['token'])) {
    header("Location: https://scan.xiaoxqian.xyz:8443/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Network Scanner</title>
    <link href="/static/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f6f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .table {
            margin-bottom: 0;
            background: white;
            border-radius: 6px;
            overflow: hidden;
        }
        .table th {
            background: #e9ecef;
            border-bottom: 2px solid #dee2e6;
            padding: 0.75rem;
        }
        .table td {
            vertical-align: middle;
            padding: 0.75rem;
        }
        .filter-input {
            width: 100%;
            padding: 0.25rem 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .sort-btn {
            background: none;
            border: none;
            padding: 0;
            margin-left: 0.25rem;
            color: #495057;
            cursor: pointer;
        }
        .sort-btn:hover {
            color: #007bff;
        }
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .dropdown-menu {
            max-height: 300px;
            overflow-y: auto;
        }
        .sql-query {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            border: 1px solid #ced4da;
        }
    </style>
</head>
<body>
    <!-- Original navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Network Scanner</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="https://scan.xiaoxqian.xyz:8443/import.php">Import</a></li>
                    <li class="nav-item"><a class="nav-link" href="https://scan.xiaoxqian.xyz:8443/status.php">Status</a></li>
                    <li class="nav-item"><a class="nav-link" href="https://scan.xiaoxqian.xyz:8443/config.php">Config</a></li>
                    <li class="nav-item"><a class="nav-link active" href="https://scan.xiaoxqian.xyz:8443/history.php">History</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-body">
                <div class="controls">
                    <h4 class="mb-0">Scan History</h4>
                    <div>
                        <div class="dropdown d-inline-block me-2">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="columnsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                Columns
                            </button>
                            <ul class="dropdown-menu" id="columnSelector" aria-labelledby="columnsDropdown">
                                <!-- Populated by JS -->
                            </ul>
                        </div>
                        <button id="exportBtn" class="btn btn-outline-success btn-sm">Export to Excel</button>
                    </div>
                </div>
                <textarea class="sql-query" id="sqlQuery" placeholder="Enter custom SQL query (e.g: SELECT * FROM devices WHERE ip LIKE '%192.168%')"></textarea>
                <button id="runSqlBtn" class="btn btn-outline-primary btn-sm mb-3">Run SQL Query</button>
                <div class="table-responsive">
                    <table class="table table-striped" id="historyTable">
                        <thead id="tableHead"></thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="/static/js/bootstrap.min.js"></script>
    <script>
        // State management
        let allColumns = [];
        let selectedColumns = [];
        let filters = {};
        let sortColumn = null;
        let sortOrder = 'asc';
        let currentData = [];

        // Token from PHP session
        const token = '<?php echo htmlspecialchars($_SESSION['token']); ?>';
        const apiBaseUrl = 'https://scan.xiaoxqian.xyz:8443/api';

        // Fetch initial data
        async function fetchHistory() {
            try {
                const response = await fetch(`${apiBaseUrl}/history`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (!response.ok) {
                    if (response.status === 401) window.location.href = 'https://scan.xiaoxqian.xyz:8443/login.php';
                    throw new Error('Failed to fetch history: ' + response.statusText);
                }
                const data = await response.json();
                allColumns = data.columns;
                currentData = data.results;
                selectedColumns = selectedColumns.length ? selectedColumns : allColumns;
                renderTable();
                renderColumnSelector();
            } catch (error) {
                console.error('Error fetching history:', error);
                alert('Failed to load history data: ' + error.message);
            }
        }

        // Render column selector
        function renderColumnSelector() {
            const selector = document.getElementById('columnSelector');
            selector.innerHTML = allColumns.map(col => `
                <li>
                    <div class="dropdown-item">
                        <input type="checkbox" 
                               id="col_${col}" 
                               value="${col}" 
                               ${selectedColumns.includes(col) ? 'checked' : ''}>
                        <label for="col_${col}">${col}</label>
                    </div>
                </li>
            `).join('');
            selector.querySelectorAll('input').forEach(input => {
                input.addEventListener('change', updateColumns);
            });
        }

        // Update selected columns
        function updateColumns() {
            selectedColumns = Array.from(document.querySelectorAll('#columnSelector input:checked'))
                .map(input => input.value);
            if (selectedColumns.length === 0) selectedColumns = allColumns;
            renderTable();
        }

        // Render table
        function renderTable() {
            const thead = document.getElementById('tableHead');
            const tbody = document.getElementById('tableBody');

            // Apply filters
            let filteredData = currentData.filter(row => {
                return Object.entries(filters).every(([col, value]) => {
                    if (!value) return true;
                    return String(row[col] || '').toLowerCase().includes(value.toLowerCase());
                });
            });

            // Apply sorting
            if (sortColumn) {
                filteredData.sort((a, b) => {
                    const valA = a[sortColumn] || '';
                    const valB = b[sortColumn] || '';
                    return sortOrder === 'asc' 
                        ? String(valA).localeCompare(String(valB)) 
                        : String(valB).localeCompare(String(valA));
                });
            }

            // Render headers
            thead.innerHTML = `
                <tr>
                    ${selectedColumns.map(col => `
                        <th>
                            <div class="d-flex align-items-center">
                                <span>${col}</span>
                                <button class="sort-btn" data-col="${col}">
                                    ${sortColumn === col && sortOrder === 'asc' ? '▲' : 
                                      sortColumn === col && sortOrder === 'desc' ? '▼' : '↕'}
                                </button>
                            </div>
                            <input type="text" 
                                   class="filter-input mt-1" 
                                   data-col="${col}" 
                                   value="${filters[col] || ''}" 
                                   placeholder="Filter...">
                        </th>
                    `).join('')}
                </tr>
            `;

            // Render body
            tbody.innerHTML = filteredData.map(row => `
                <tr>
                    ${selectedColumns.map(col => `
                        <td>${row[col] || 'N/A'}</td>
                    `).join('')}
                </tr>
            `).join('');

            // Add event listeners
            document.querySelectorAll('.sort-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const col = btn.dataset.col;
                    sortColumn = (sortColumn === col && sortOrder === 'asc') ? col : col;
                    sortOrder = (sortColumn === col && sortOrder === 'asc') ? 'desc' : 'asc';
                    renderTable();
                });
            });

            document.querySelectorAll('.filter-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    filters[e.target.dataset.col] = e.target.value;
                    renderTable();
                });
            });
        }

        // Export to Excel
        document.getElementById('exportBtn').addEventListener('click', async () => {
            try {
                const filteredData = currentData.filter(row => {
                    return Object.entries(filters).every(([col, value]) => {
                        if (!value) return true;
                        return String(row[col] || '').toLowerCase().includes(value.toLowerCase());
                    });
                });
                if (sortColumn) {
                    filteredData.sort((a, b) => {
                        const valA = a[sortColumn] || '';
                        const valB = b[sortColumn] || '';
                        return sortOrder === 'asc' 
                            ? String(valA).localeCompare(String(valB)) 
                            : String(valB).localeCompare(String(valA));
                    });
                }

                const response = await fetch(`${apiBaseUrl}/export-history`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        columns: selectedColumns,
                        data: filteredData
                    })
                });

                if (!response.ok) {
                    if (response.status === 401) window.location.href = 'https://scan.xiaoxqian.xyz:8443/login.php';
                    throw new Error('Export failed: ' + response.statusText);
                }
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'history_export.xlsx';
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } catch (error) {
                console.error('Export error:', error);
                alert('Failed to export data: ' + error.message);
            }
        });

        // Run custom SQL query
        document.getElementById('runSqlBtn').addEventListener('click', async () => {
            const query = document.getElementById('sqlQuery').value.trim();
            if (!query) {
                alert('Please enter a SQL query.');
                return;
            }
            try {
                const url = `${apiBaseUrl}/custom-sql?query=${encodeURIComponent(query)}`;
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = 'https://scan.xiaoxqian.xyz:8443/login.php';
                    } else if (response.status === 422) {
                        const errorData = await response.json();
                        throw new Error('Invalid query format: ' + JSON.stringify(errorData));
                    }
                    throw new Error('SQL query failed: ' + response.statusText);
                }
                const data = await response.json();
                currentData = data.results;
                renderTable();
            } catch (error) {
                console.error('SQL error:', error);
                alert('SQL query failed: ' + error.message);
            }
        });

        // Initialize
        fetchHistory();
    </script>
</body>
</html>
