<?php

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "hostel";

// Create connection using MariaDB port 3307
$conn = mysqli_connect($servername, $username, $password, $dbname, 3307);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Run only when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect and sanitize form data
    $hostelid      = mysqli_real_escape_string($conn, $_POST['hostelid']);
    $hostelname    = mysqli_real_escape_string($conn, $_POST['hostelname']);
    $location      = mysqli_real_escape_string($conn, $_POST['location']);
    $overview      = mysqli_real_escape_string($conn, $_POST['overview']);
    $rooms         = (int)$_POST['rooms'];
    $hostelmanager = mysqli_real_escape_string($conn, $_POST['hostelmanager']);

    // Insert data into table hostel
    $sql = "INSERT INTO hostel 
            (hostelid, hostelname, location, overview, rooms, hostelmanager)
            VALUES 
            ('$hostelid', '$hostelname', '$location', '$overview', '$rooms', '$hostelmanager')";

    // Execute query
    if (mysqli_query($conn, $sql)) {

        echo "
        <div style='
            background:#d4edda;
            color:#155724;
            padding:15px;
            border:1px solid #c3e6cb;
            width:400px;
            margin:20px auto;
            font-family:Arial;
        '>

            <h3>Registration Successful!</h3>

            <a href='hostel.html'>Add Another Hostel</a>

        </div>";

    } else {

        echo "
        <div style='
            background:#f8d7da;
            color:#721c24;
            padding:15px;
            border:1px solid #f5c6cb;
            width:500px;
            margin:20px auto;
            font-family:Arial;
        '>

            <h3>Error Occurred</h3>

            " . mysqli_error($conn) . "

        </div>";
    }

} else {

    echo "Waiting for form submission...";
}

// Close connection
mysqli_close($conn);

?>