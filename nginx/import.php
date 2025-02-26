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
    <title>Import - Network Scanner</title>
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
                    <li class="nav-item"><a class="nav-link active" href="/import.php">Import</a></li>
                    <li class="nav-item"><a class="nav-link" href="/status.php">Status</a></li>
                    <li class="nav-item"><a class="nav-link" href="/config.php">Config</a></li>
                    <li class="nav-item"><a class="nav-link" href="/history.php">History</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h1 class="card-title mb-0">Import Configuration</h1>
            </div>
            <div class="card-body">
                <p class="mb-4">Upload an Excel file to configure IP ranges, SNMP communities, and SSH credentials. <a href="/static/template.xlsx" download class="text-primary">Download template</a></p>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['configFile'])) {
    $file = $_FILES['configFile'];
    
    // 特别注意：只使用文件名，不包含路径前缀
    // 这样后端即使添加/tmp/前缀也不会导致双重路径
    $file_name = basename($file['name']);
    
    // 临时存储上传的文件
    $temp_path = "/tmp/" . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $temp_path)) {
        $ch = curl_init("$api_base_url/upload-config");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $_SESSION['token']]);
        
        // 创建临时文件重命名，去掉路径前缀
        // 后端可能会自动添加/tmp/，所以我们只发送文件名
        // 使用CURLFile时只提供文件名部分
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new CURLFile($temp_path, '', $file_name)
        ]);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);
        
        // 清理临时文件
        unlink($temp_path);
        
        if ($result && isset($result['message'])) {
            echo "<div class='alert alert-success'>{$result['message']}</div>";
        } else {
            echo "<div class='alert alert-danger'>上传失败</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>无法移动上传的文件</div>";
    }
}
?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="configFile" class="form-label">Select Excel Config File:</label>
                        <input type="file" id="configFile" name="configFile" accept=".xlsx" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Upload and Scan</button>
                </form>
            </div>
        </div>
    </div>
    <script src="/static/js/bootstrap.min.js"></script>
</body>
</html>
