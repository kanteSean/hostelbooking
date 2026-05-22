<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = mysqli_connect("localhost", "root", "", "registration", 3307);

if (!$conn) {
    die("DB Connection Failed: " . mysqli_connect_error());
}

/* ================= STUDENTS ================= */
$students = mysqli_query($conn, "SELECT * FROM student");

if (!$students) {
    die("Students query failed: " . mysqli_error($conn));
}

/* ================= HOSTELS ================= */
$hostels = mysqli_query($conn, "SELECT * FROM hostel");

if (!$hostels) {
    die("Hostels query failed: " . mysqli_error($conn));
}

/* ================= ROOMS (FIXED) ================= */
$rooms = mysqli_query($conn, "SELECT * FROM room");

if (!$rooms) {
    die("Rooms query failed: " . mysqli_error($conn));
}

/* ================= PAYMENTS ================= */
$payments = mysqli_query($conn, "SELECT * FROM payment");

if (!$payments) {
    die("Payments query failed: " . mysqli_error($conn));
}
?>