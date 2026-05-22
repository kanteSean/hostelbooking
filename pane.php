<?php require_once 'admin.php'; ?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>Admin Dashboard</title>

<style>

body{
    font-family: Arial, sans-serif;
    background:#f0f8ff;
    margin:0;
    padding:0;
}

header{
    background:#2A3F00;
    color:white;
    padding:20px;
    text-align:center;
}

nav{
    background:#A0A0A4;
    padding:12px;
    text-align:center;
}

nav a{
    color:#2A3F00;
    text-decoration:none;
    font-weight:bold;
    margin:0 20px;
}

nav a:hover{
    color:white;
}

section{
    width:95%;
    margin:auto;
    padding:20px;
}

h2{
    color:#2A3F00;
    margin-bottom:15px;
}

table{
    width:100%;
    border-collapse:collapse;
    background:white;
    margin-bottom:40px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

th{
    background:#2A3F00;
    color:white;
    padding:12px;
}

td{
    border:1px solid #ccc;
    padding:10px;
    text-align:center;
}

tr:nth-child(even){
    background:#f9f9f9;
}

.action-btn{
    padding:6px 12px;
    text-decoration:none;
    border-radius:4px;
    color:white;
    font-size:14px;
}

.edit-btn{
    background:#007bff;
}

.delete-btn{
    background:#dc3545;
}

.edit-btn:hover{
    background:#0056b3;
}

.delete-btn:hover{
    background:#a71d2a;
}

</style>

</head>

<body>

<header>
    <h1>Admin Dashboard</h1>
</header>

<nav>
    <a href="#students">Students</a>
    <a href="#hostels">Hostels</a>
    <a href="#rooms">Rooms</a>
    <a href="#payments">Payments</a>
</nav>

<!-- ================= STUDENTS ================= -->

<section id="students">

<h2>Registered Students</h2>

<table>

<tr>
    <th>Student ID</th>
    <th>First Name</th>
    <th>Middle Name</th>
    <th>Last Name</th>
    <th>Email</th>
    <th>Gender</th>
    <th>Contact</th>
    <th>Actions</th>
</tr>

<?php while($row = mysqli_fetch_assoc($students)) { ?>

<tr>
    <td><?php echo $row['studentid']; ?></td>
    <td><?php echo $row['fname']; ?></td>
    <td><?php echo $row['mname']; ?></td>
    <td><?php echo $row['lname']; ?></td>
    <td><?php echo $row['email']; ?></td>
    <td><?php echo $row['gender']; ?></td>
    <td><?php echo $row['contact']; ?></td>

    <td>
        <a class="action-btn edit-btn"
           href="edit_student.php?id=<?php echo $row['studentid']; ?>">
           Edit
        </a>

        <a class="action-btn delete-btn"
           href="delete_student.php?id=<?php echo $row['studentid']; ?>"
           onclick="return confirm('Delete this student?')">
           Delete
        </a>
    </td>
</tr>

<?php } ?>

</table>

</section>

<!-- ================= HOSTELS ================= -->

<section id="hostels">

<h2>Hostels</h2>

<table>

<tr>
    <th>Hostel ID</th>
    <th>Hostel Name</th>
    <th>Location</th>
    <th>Overview</th>
    <th>Total Rooms</th>
    <th>Hostel Manager</th>
    <th>Actions</th>
</tr>

<?php while($row = mysqli_fetch_assoc($hostels)) { ?>

<tr>

    <td><?php echo $row['hostelid']; ?></td>
    <td><?php echo $row['hostelname']; ?></td>
    <td><?php echo $row['location']; ?></td>
    <td><?php echo $row['overview']; ?></td>
    <td><?php echo $row['rooms']; ?></td>
    <td><?php echo $row['hostelmanager']; ?></td>

    <td>

        <a class="action-btn edit-btn"
           href="edit_hostel.php?id=<?php echo $row['hostelid']; ?>">
           Edit
        </a>

        <a class="action-btn delete-btn"
           href="delete_hostel.php?id=<?php echo $row['hostelid']; ?>"
           onclick="return confirm('Delete this hostel?')">
           Delete
        </a>

    </td>

</tr>

<?php } ?>

</table>

</section>

<!-- ================= ROOMS ================= -->

<section id="rooms">

<h2>Rooms</h2>

<table>

<tr>
    <th>Room ID</th>
    <th>Hostel ID</th>
    <th>Room Type</th>
    <th>Price</th>
    <th>Status</th>
    <th>Actions</th>
</tr>

<?php while($row = mysqli_fetch_assoc($rooms)) { ?>

<tr>

    <td><?php echo $row['roomid']; ?></td>
    <td><?php echo $row['hostelid']; ?></td>
    <td><?php echo $row['roomtype']; ?></td>
    <td>UGX <?php echo number_format($row['price']); ?></td>
    <td><?php echo $row['status']; ?></td>

    <td>

        <a class="action-btn edit-btn"
           href="edit_room.php?id=<?php echo $row['roomid']; ?>">
           Edit
        </a>

        <a class="action-btn delete-btn"
           href="delete_room.php?id=<?php echo $row['roomid']; ?>"
           onclick="return confirm('Delete this room?')">
           Delete
        </a>

    </td>

</tr>

<?php } ?>

</table>

</section>

<!-- ================= PAYMENTS ================= -->

<section id="payments">

<h2>Payments</h2>

<table>

<tr>
    <th>Payment ID</th>
    <th>Booking ID</th>
    <th>Method</th>
    <th>Amount</th>
    <th>Status</th>
</tr>

<?php while($row = mysqli_fetch_assoc($payments)) { ?>

<tr>

    <td><?php echo $row['paymentid']; ?></td>
    <td><?php echo $row['bookingid']; ?></td>
    <td><?php echo $row['payment_method']; ?></td>
    <td>UGX <?php echo number_format($row['amount']); ?></td>
    <td><?php echo $row['status']; ?></td>

</tr>

<?php } ?>

</table>

</section>

</body>
</html>