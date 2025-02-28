<?php
session_start();
if (!isset($_SESSION['token'])) {
    header("Location: /login.php"); // Relative path, will use current host
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
    <title>History - Network Scanner</title>
    <link href="/static/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .sql-query {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-family: monospace;
            resize: vertical;
            min-height: 60px;
            margin-bottom: 10px;
        }
        .filter-input {
            width: 100%;
            padding: 4px;
            font-size: 0.8rem;
            border: 1px solid #ced4da;
            border-radius: 3px;
        }
        .sort-btn {
            background: none;
            border: none;
            padding: 2px 6px;
            margin-left: 5px;
            cursor: pointer;
        }
        .card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 20px;
        }
        .sql-results {
            margin-top: 20px;
            display: none;
        }
        .column-badge {
            margin: 2px;
            padding: 4px 8px;
            background-color: #f0f0f0;
            border-radius: 4px;
            display: inline-block;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .column-badge.active {
            background-color: #007bff;
            color: white;
        }
        .column-manager {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .sql-error {
            display: none;
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <!-- 保持原有导航栏不变 -->
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
                    <li class="nav-item"><a class="nav-link" href="/config.php">Config</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/history.php">History</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- SQL 查询区域 -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Custom SQL Query</h5>
                <button id="toggleSqlBtn" class="btn btn-sm btn-outline-secondary">Collapse</button>
            </div>
            <div class="card-body" id="sqlQuerySection">
                <textarea class="sql-query" id="sqlQuery" placeholder="Enter custom SQL query (e.g: SELECT * FROM devices WHERE ip LIKE '%192.168%')"></textarea>
                <div class="d-flex justify-content-between">
                    <div>
                        <button id="runSqlBtn" class="btn btn-primary btn-sm">Run Query</button>
                        <button id="clearSqlBtn" class="btn btn-outline-secondary btn-sm">Clear</button>
                    </div>
                    <div>
                        <button id="exportSqlBtn" class="btn btn-outline-success btn-sm">Export Results</button>
                    </div>
                </div>
                
                <!-- SQL 错误信息区域 -->
                <div id="sqlError" class="sql-error"></div>
                
                <!-- SQL 查询加载指示器 -->
                <div id="sqlLoading" class="loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                
                <!-- SQL 查询结果区域 -->
                <div class="sql-results" id="sqlResults">
                    <h6 class="mt-3">Query Results</h6>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="sqlResultsTable">
                            <thead id="sqlResultsHead"></thead>
                            <tbody id="sqlResultsBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 历史记录区域 -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Scan History</h5>
                <button id="exportHistoryBtn" class="btn btn-outline-success btn-sm">Export to Excel</button>
            </div>
            <div class="card-body">
                <!-- 列管理区域 -->
                <div class="column-manager">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Column Management</h6>
                        <div>
                            <button id="selectAllColumns" class="btn btn-outline-primary btn-sm">Select All</button>
                            <button id="deselectAllColumns" class="btn btn-outline-secondary btn-sm">Deselect All</button>
                        </div>
                    </div>
                    <div id="columnBadges"></div>
                </div>
                
                <!-- 历史加载指示器 -->
                <div id="historyLoading" class="loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                
                <!-- 历史数据表格 -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="historyTable">
                        <thead id="tableHead"></thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="/static/js/bootstrap.min.js"></script>
    <script>
        // 状态管理
        let allColumns = [];
        let selectedColumns = [];
        let historyFilters = {};
        let sqlResultColumns = [];
        let sortColumn = null;
        let sortOrder = 'asc';
        let historyData = [];
        let sqlResultsData = [];

        // 从PHP会话获取token
        const token = '<?php echo htmlspecialchars($_SESSION['token']); ?>';
        const apiBaseUrl = '<?php echo $api_base_url; ?>';

        // DOM元素引用
        const historyTable = document.getElementById('historyTable');
        const tableHead = document.getElementById('tableHead');
        const tableBody = document.getElementById('tableBody');
        const columnBadges = document.getElementById('columnBadges');
        const sqlQuery = document.getElementById('sqlQuery');
        const runSqlBtn = document.getElementById('runSqlBtn');
        const clearSqlBtn = document.getElementById('clearSqlBtn');
        const exportHistoryBtn = document.getElementById('exportHistoryBtn');
        const exportSqlBtn = document.getElementById('exportSqlBtn');
        const sqlResultsSection = document.getElementById('sqlResults');
        const sqlResultsTable = document.getElementById('sqlResultsTable');
        const sqlResultsHead = document.getElementById('sqlResultsHead');
        const sqlResultsBody = document.getElementById('sqlResultsBody');
        const toggleSqlBtn = document.getElementById('toggleSqlBtn');
        const sqlQuerySection = document.getElementById('sqlQuerySection');
        const historyLoading = document.getElementById('historyLoading');
        const sqlLoading = document.getElementById('sqlLoading');
        const sqlError = document.getElementById('sqlError');
        const selectAllColumns = document.getElementById('selectAllColumns');
        const deselectAllColumns = document.getElementById('deselectAllColumns');

        // 获取历史数据
        async function fetchHistory() {
            try {
                historyLoading.style.display = 'block';
                const response = await fetch(`${apiBaseUrl}/history`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (!response.ok) {
                    if (response.status === 401) window.location.href = '/login.php';
                    throw new Error('Failed to fetch history: ' + response.statusText);
                }
                const data = await response.json();
                allColumns = data.columns;
                historyData = data.results;
                selectedColumns = selectedColumns.length ? selectedColumns : [...allColumns];
                renderColumnBadges();
                renderHistoryTable();
                historyLoading.style.display = 'none';
            } catch (error) {
                console.error('Error fetching history:', error);
                alert('Failed to load history data: ' + error.message);
                historyLoading.style.display = 'none';
            }
        }

        // 渲染列选择器徽章
        function renderColumnBadges() {
            columnBadges.innerHTML = allColumns.map(col => `
                <span class="column-badge ${selectedColumns.includes(col) ? 'active' : ''}" 
                      data-column="${col}">${col}</span>
            `).join('');
            
            document.querySelectorAll('.column-badge').forEach(badge => {
                badge.addEventListener('click', toggleColumn);
            });
        }

        // 切换列显示状态
        function toggleColumn(e) {
            const column = e.target.dataset.column;
            if (selectedColumns.includes(column)) {
                selectedColumns = selectedColumns.filter(col => col !== column);
                e.target.classList.remove('active');
            } else {
                selectedColumns.push(column);
                e.target.classList.add('active');
            }
            
            if (selectedColumns.length === 0) {
                selectedColumns = [allColumns[0]]; // 至少保留一列
                document.querySelector(`.column-badge[data-column="${allColumns[0]}"]`).classList.add('active');
            }
            
            renderHistoryTable();
        }

        // 选择所有列
        selectAllColumns.addEventListener('click', () => {
            selectedColumns = [...allColumns];
            document.querySelectorAll('.column-badge').forEach(badge => {
                badge.classList.add('active');
            });
            renderHistoryTable();
        });

        // 取消选择所有列
        deselectAllColumns.addEventListener('click', () => {
            // 保留第一列
            selectedColumns = [allColumns[0]];
            document.querySelectorAll('.column-badge').forEach(badge => {
                if (badge.dataset.column === allColumns[0]) {
                    badge.classList.add('active');
                } else {
                    badge.classList.remove('active');
                }
            });
            renderHistoryTable();
        });

        // 渲染历史数据表格
        function renderHistoryTable() {
            // 应用筛选
            let filteredData = historyData.filter(row => {
                return Object.entries(historyFilters).every(([col, value]) => {
                    if (!value) return true;
                    return String(row[col] || '').toLowerCase().includes(value.toLowerCase());
                });
            });

            // 应用排序
            if (sortColumn) {
                filteredData.sort((a, b) => {
                    const valA = a[sortColumn] || '';
                    const valB = b[sortColumn] || '';
                    return sortOrder === 'asc'
                        ? String(valA).localeCompare(String(valB))
                        : String(valB).localeCompare(String(valA));
                });
            }

            // 渲染表头
            tableHead.innerHTML = `
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
                                   value="${historyFilters[col] || ''}"
                                   placeholder="Filter...">
                        </th>
                    `).join('')}
                </tr>
            `;

            // 渲染表格内容
            tableBody.innerHTML = filteredData.map(row => `
                <tr>
                    ${selectedColumns.map(col => `
                        <td>${row[col] !== undefined && row[col] !== null ? row[col] : 'N/A'}</td>
                    `).join('')}
                </tr>
            `).join('');

            // 添加排序事件监听
            document.querySelectorAll('#tableHead .sort-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const col = btn.dataset.col;
                    if (sortColumn === col) {
                        sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortColumn = col;
                        sortOrder = 'asc';
                    }
                    renderHistoryTable();
                });
            });

            // 添加筛选事件监听
            document.querySelectorAll('#tableHead .filter-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    historyFilters[e.target.dataset.col] = e.target.value;
                    renderHistoryTable();
                });
            });
        }

        // 执行SQL查询 - 修改以匹配后端API格式
        async function runSqlQuery() {
            const query = sqlQuery.value.trim();
            if (!query) {
                alert('Please enter a SQL query.');
                return;
            }

            // 重置错误和结果状态
            sqlError.style.display = 'none';
            sqlResultsSection.style.display = 'none';
            sqlLoading.style.display = 'block';
            
            try {
                // 使用URL参数传递查询，这与原始代码一致
                const url = `${apiBaseUrl}/custom-sql?query=${encodeURIComponent(query)}`;
                
                // 使用GET或POST取决于原始API的设计，但保持请求方法与原始代码一致
                const response = await fetch(url, {
                    method: 'POST',  // 保持原始方法
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = '/login.php';
                        return;
                    }
                    
                    const errorText = await response.text();
                    let errorMessage = 'SQL query failed';
                    
                    try {
                        // 尝试解析为JSON
                        const errorData = JSON.parse(errorText);
                        errorMessage = errorData.error || errorData.message || 'SQL query failed';
                    } catch (e) {
                        // 如果不是JSON，使用原始文本
                        errorMessage = errorText || 'SQL query failed';
                    }
                    
                    throw new Error(errorMessage);
                }

                // 解析成功响应
                const data = await response.json();
                
                // 检查是否有有效结果
                if (!data || !data.results) {
                    throw new Error('Invalid response format from server');
                }
                
                sqlResultsData = data.results || [];
                sqlResultColumns = data.columns || Object.keys(sqlResultsData[0] || {});
                
                // 显示结果
                renderSqlResults();
                sqlResultsSection.style.display = 'block';
            } catch (error) {
                console.error('SQL error:', error);
                sqlError.textContent = 'SQL query failed: ' + error.message;
                sqlError.style.display = 'block';
                sqlResultsSection.style.display = 'none';
            } finally {
                sqlLoading.style.display = 'none';
            }
        }

        // 渲染SQL查询结果
        function renderSqlResults() {
            if (!sqlResultsData.length) {
                sqlResultsHead.innerHTML = '';
                sqlResultsBody.innerHTML = '<tr><td colspan="100%" class="text-center">No results found</td></tr>';
                return;
            }

            // 如果没有提供列，尝试从结果中推断
            if (!sqlResultColumns.length && sqlResultsData.length > 0) {
                sqlResultColumns = Object.keys(sqlResultsData[0]);
            }

            // 渲染表头
            sqlResultsHead.innerHTML = `
                <tr>
                    ${sqlResultColumns.map(col => `<th>${col}</th>`).join('')}
                </tr>
            `;

            // 渲染数据行
            sqlResultsBody.innerHTML = sqlResultsData.map(row => `
                <tr>
                    ${sqlResultColumns.map(col => `
                        <td>${row[col] !== undefined && row[col] !== null ? row[col] : 'N/A'}</td>
                    `).join('')}
                </tr>
            `).join('');
        }

        // 导出历史数据
        async function exportHistoryData() {
            try {
                // 应用当前筛选和排序
                let filteredData = historyData.filter(row => {
                    return Object.entries(historyFilters).every(([col, value]) => {
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
                    if (response.status === 401) window.location.href = '/login.php';
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
        }

        // 导出SQL查询结果
        async function exportSqlResults() {
            if (!sqlResultsData.length) {
                alert('No SQL results to export');
                return;
            }

            try {
                const response = await fetch(`${apiBaseUrl}/export-history`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        columns: sqlResultColumns,
                        data: sqlResultsData
                    })
                });

                if (!response.ok) {
                    if (response.status === 401) window.location.href = '/login.php';
                    throw new Error('Export failed: ' + response.statusText);
                }
                
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'sql_results_export.xlsx';
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } catch (error) {
                console.error('Export error:', error);
                alert('Failed to export SQL results: ' + error.message);
            }
        }

        // 切换SQL查询区域显示/隐藏
        toggleSqlBtn.addEventListener('click', () => {
            const sqlContent = sqlQuerySection.querySelector('.sql-query');
            const actionButtons = sqlQuerySection.querySelector('.d-flex');
            const results = sqlQuerySection.querySelector('.sql-results');
            const loading = sqlQuerySection.querySelector('.loading');
            const error = sqlQuerySection.querySelector('.sql-error');
            
            if (sqlContent.style.display === 'none') {
                sqlContent.style.display = '';
                actionButtons.style.display = '';
                if (results.style.display !== 'none') results.style.display = 'block';
                if (loading.style.display !== 'none') loading.style.display = 'block';
                if (error.style.display !== 'none') error.style.display = 'block';
                toggleSqlBtn.textContent = 'Collapse';
            } else {
                sqlContent.style.display = 'none';
                actionButtons.style.display = 'none';
                results.style.display = 'none';
                loading.style.display = 'none';
                error.style.display = 'none';
                toggleSqlBtn.textContent = 'Expand';
            }
        });

        // 清除SQL查询
        clearSqlBtn.addEventListener('click', () => {
            sqlQuery.value = '';
            sqlResultsSection.style.display = 'none';
            sqlError.style.display = 'none';
        });

        // 事件监听器
        runSqlBtn.addEventListener('click', runSqlQuery);
        exportHistoryBtn.addEventListener('click', exportHistoryData);
        exportSqlBtn.addEventListener('click', exportSqlResults);

        // 初始化页面
        fetchHistory();
    </script>
</body>
</html>
