<?php
session_start();
require_once __DIR__ . '/connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hostelid = trim($_POST['hostelid'] ?? '');
    $password  = trim($_POST['password']  ?? '');

    if ($hostelid === '' || $password === '') {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM hostel WHERE hostelid = ?");
        $stmt->bind_param("s", $hostelid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($hostel = $result->fetch_assoc()) {
            if ($password === $hostel['password']) {
                $_SESSION['manager_hostelid']   = $hostel['hostelid'];
                $_SESSION['manager_hostelname'] = $hostel['hostelname'];
                $_SESSION['manager_name']       = $hostel['hostelmanager'];
                header("Location: manager_dashboard.php");
                exit();
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "No hostel found with that ID.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hostel Manager Login</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #2A3F00 0%, #4a6741 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}
.login-wrap {
    background: white;
    border-radius: 14px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.25);
    width: 100%;
    max-width: 420px;
    overflow: hidden;
}
.login-header {
    background: #2A3F00;
    color: white;
    padding: 30px 35px;
    text-align: center;
}
.login-header h1 { font-size: 22px; margin-bottom: 5px; }
.login-header p  { font-size: 13px; opacity: 0.75; }
.login-body { padding: 30px 35px; }
label { display: block; font-size: 13px; font-weight: bold; color: #444; margin-bottom: 6px; }
input[type=text], input[type=password] {
    width: 100%; padding: 11px 14px; border: 1px solid #ddd;
    border-radius: 7px; font-size: 14px; margin-bottom: 18px;
    transition: border-color 0.2s;
}
input:focus { border-color: #2A3F00; outline: none; }
button {
    width: 100%; padding: 13px; background: #2A3F00; color: white;
    border: none; border-radius: 7px; font-size: 16px; cursor: pointer;
}
button:hover { background: #3d5c00; }
.error {
    background: #f8d7da; color: #721c24; padding: 11px 14px;
    border-radius: 7px; margin-bottom: 16px; font-size: 14px;
}
.back-link { text-align: center; margin-top: 18px; font-size: 13px; }
.back-link a { color: #2A3F00; }
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-header">
        <h1> Hostel Manager Portal</h1>
        <p>Login with your Hostel ID and password</p>
    </div>
    <div class="login-body">
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <label>Hostel ID</label>
            <input type="text" name="hostelid" placeholder="e.g. sf22" required>
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter your password" required>
            <button type="submit">Login</button>
        </form>
        <div class="back-link">
            <a href="login.php">← Student Login</a>
        </div>
    </div>
</div>
</body>
</html>
