<?php

$host = "localhost";
$user = "root";
$pass = "";
$db   = "registration";
$port = 3307;

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

/* GET FORM DATA */
$paymentid      = $_POST['paymentid'];
$payment_method = $_POST['payment_method'];
$amount         = $_POST['amount'];

/* INSERT INTO payment TABLE */
$sql = "INSERT INTO payment
(paymentid, payment_method, amount)
VALUES (?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "ssi",
    $paymentid,
    $payment_method,
    $amount
);

if (mysqli_stmt_execute($stmt)) {

    echo "
    <script>
        alert('Payment Recorded Successfully');
        window.location.href='payment.html';
    </script>
    ";

} else {

    echo "Error: " . mysqli_error($conn);

}

mysqli_close($conn);

?>