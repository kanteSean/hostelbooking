<?php

$host = "localhost";
$user = "root";
$pass = "";
$db   = "registration";
$port = 3307;

/* DATABASE CONNECTION */
$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

/* CHECK IF FORM IS SUBMITTED */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // COLLECT FORM DATA
    $roomid    = mysqli_real_escape_string($conn, $_POST['roomid']);
    $hostelid  = mysqli_real_escape_string($conn, $_POST['hostelid']);
    $roomtype  = mysqli_real_escape_string($conn, $_POST['roomtype']);
    $price     = mysqli_real_escape_string($conn, $_POST['price']);
    $status    = mysqli_real_escape_string($conn, $_POST['status']);

    /* INSERT INTO ROOM TABLE */
    $sql = "INSERT INTO room
            (roomid, hostelid, roomtype, price, status)

            VALUES
            ('$roomid', '$hostelid', '$roomtype', '$price', '$status')";

    if (mysqli_query($conn, $sql)) {

        echo "
        <script>
            alert('Room added successfully');
            window.location.href='room.html';
        </script>
        ";

    } else {

        echo "Error: " . mysqli_error($conn);

    }

}

mysqli_close($conn);

?>