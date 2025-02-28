<?php
session_start();

$host = $_SERVER['HTTP_HOST']; // Get current host (e.g., scan1.xiaoxqian.xyz:8443)
$api_base_url = "https://$host/api"; // Dynamic API base URL


function verify_token($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    try {
        $payload = json_decode(base64_decode($parts[1]), true);
        return isset($payload['exp']) && $payload['exp'] > time();
    } catch (Exception $e) {
        return false;
    }
}

if (isset($_SESSION['token']) && verify_token($_SESSION['token'])) {
    header("Location: /status.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    ob_start();
    
    $data = http_build_query(['username' => $username, 'password' => $password]);
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $data
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents("$api_base_url/token", false, $context);
    
    if ($response === false) {
        $error = "Failed to connect to API: " . error_get_last()['message'];
    } else {
        $result = json_decode($response, true);
        if ($result && isset($result['access_token'])) {
            $_SESSION['token'] = $result['access_token'];
            ob_end_clean();
            header("Location: /status.php");
            exit;
        } else {
            $error = $result['detail'] ?? 'Login failed. Please check your credentials.';
        }
    }
    
    ob_end_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Network Scanner</title>
    <link href="/static/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
        }
        .login-card h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e3c72;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .login-card .form-control {
            border-radius: 10px;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            transition: border-color 0.3s ease;
        }
        .login-card .form-control:focus {
            border-color: #2a5298;
            box-shadow: 0 0 0 0.2rem rgba(42, 82, 152, 0.25);
        }
        .login-card .btn-primary {
            background-color: #2a5298;
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        .login-card .btn-primary:hover {
            background-color: #1e3c72;
        }
        .alert {
            border-radius: 10px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Network Scanner</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="e.g., admin" required>
            </div>
            <div>
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="e.g., admin123" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>
        <p class="text-center text-muted mt-3">Forgot your password? Contact your administrator.</p>
    </div>
    <script src="/static/js/bootstrap.min.js"></script>
</body>
</html>
