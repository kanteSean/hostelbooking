<?php
// login.php
session_start();
require_once __DIR__ . '/connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $student_id = trim($_POST['student_id'] ?? '');
    $password   = trim($_POST['password']   ?? '');

    if ($student_id === '' || $password === '') {
        $error = "Please fill in all fields.";
    } else {

        $stmt = $conn->prepare("SELECT * FROM student WHERE studentid = ?");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($student = $result->fetch_assoc()) {

            if (password_verify($password, $student['password'])) {

                $_SESSION['student_id']   = $student['studentid'];
                $_SESSION['student_name'] = $student['fname'] . ' ' . $student['lname'];

                header("Location: student_dashboard_v2.php");
                exit();

            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "No student found with that ID.";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Student Login</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, sans-serif;
    background: #f0f8ff;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}
.login-box {
    background: white;
    padding: 40px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    width: 100%;
    max-width: 400px;
}
.login-box h2 {
    text-align: center;
    color: #2A3F00;
    margin-bottom: 25px;
}
label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
input[type=text], input[type=password] {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #ccc;
    border-radius: 6px;
    margin-bottom: 18px;
    font-size: 14px;
}
input[type=text]:focus, input[type=password]:focus {
    border-color: #2A3F00;
    outline: none;
}
button {
    width: 100%;
    padding: 12px;
    background: #2A3F00;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
}
button:hover { background: #3d5c00; }
.error {
    background: #f8d7da;
    color: #721c24;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
    font-size: 14px;
}
</style>
</head>
<body>
<div class="login-box">
    <h2>Student Login</h2>
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST">
        <label>Student ID</label>
        <input type="text" name="student_id" placeholder="e.g. 24/u/ite/102" required>
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter your password" required>
        <button type="submit">Login</button>
		<a href="registration.html"> Register</a>
    </form>
</div>
</body>
</html>
