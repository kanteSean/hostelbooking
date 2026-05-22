<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "registration";

$conn = new mysqli($host, $user, $pass, $db, 3307);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fname    = $_POST['fname'];
    $mname    = $_POST['mname'];
    $lname    = $_POST['lname'];
    $sid      = $_POST['studentid'];
    $email    = $_POST['email'];
    $contact  = $_POST['contact'];
    $gender   = $_POST['gender'] ?? 'Not Specified';
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if ($password !== $confirm) {
        echo "<script>alert('Passwords do not match!'); window.history.back();</script>";
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // FIXED: removed duplicate contact, added confirm
    $stmt = $conn->prepare("
        INSERT INTO student 
        (fname, mname, lname, studentid, email, contact, gender, password, confirm) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // 9 placeholders = 9 variables
    $stmt->bind_param(
        "sssssssss",
        $fname,
        $mname,
        $lname,
        $sid,
        $email,
        $contact,
        $gender,
        $hashed_password,
        $confirm
    );

    if ($stmt->execute()) {
        echo "<script>alert('Registered successfully. Make your first booking'); window.location.href='login.php';</script>";
    } else {
        if ($conn->errno == 1062) {
            echo "<script>alert('Error: Student ID, Email, or Contact already in use.'); window.history.back();</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
    }

    $stmt->close();
}

$conn->close();
?>