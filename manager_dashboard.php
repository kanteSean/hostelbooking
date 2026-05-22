<?php
session_start();

// Guard: only managers
if (!isset($_SESSION['manager_hostelid'])) {
    header("Location: manager_login.php");
    exit();
}

require_once __DIR__ . '/connect.php';

$hostelid      = $_SESSION['manager_hostelid'];
$hostelname    = $_SESSION['manager_hostelname'];
$manager_name  = $_SESSION['manager_name'];

$hid = $conn->real_escape_string($hostelid);

/* ===== COLLATION HELPER ===== */
// All string comparisons use COLLATE to avoid mixed-collation errors
$col = "COLLATE utf8mb4_unicode_ci";

/* ===== HANDLE: ADD ROOM ===== */
$room_success = $room_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    $roomid   = $conn->real_escape_string(trim($_POST['roomid']));
    $roomtype = $conn->real_escape_string(trim($_POST['roomtype']));
    $price    = floatval($_POST['price']);

    $chk = $conn->query("SELECT roomid FROM room WHERE roomid $col = '$roomid'");
    if ($chk && $chk->num_rows > 0) {
        $room_error = "Room ID '$roomid' already exists.";
    } else {
        $ins = $conn->query("
            INSERT INTO room (roomid, hostelid, roomtype, price, status)
            VALUES ('$roomid', '$hid', '$roomtype', '$price', 'available')
        ");
        $room_success = $ins ? "Room '$roomid' added successfully!" : "Failed: " . $conn->error;
    }
}

/* ===== HANDLE: EDIT HOSTEL DETAILS ===== */
$edit_success = $edit_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_hostel'])) {
    $new_name     = $conn->real_escape_string(trim($_POST['hostelname']));
    $new_location = $conn->real_escape_string(trim($_POST['location']));
    $new_overview = $conn->real_escape_string(trim($_POST['overview']));
    $new_manager  = $conn->real_escape_string(trim($_POST['hostelmanager']));
    $new_password = trim($_POST['new_password']);

    $pass_sql = '';
    if ($new_password !== '') {
        $safe_pass = $conn->real_escape_string($new_password);
        $pass_sql = ", password = '$safe_pass'";
    }

    $upd = $conn->query("
        UPDATE hostel SET
            hostelname    = '$new_name',
            location      = '$new_location',
            overview      = '$new_overview',
            hostelmanager = '$new_manager'
            $pass_sql
        WHERE hostelid $col = '$hid'
    ");

    if ($upd) {
        $_SESSION['manager_hostelname'] = $new_name;
        $_SESSION['manager_name']       = $new_manager;
        $hostelname   = $new_name;
        $manager_name = $new_manager;
        $edit_success = "Hostel details updated successfully!";
    } else {
        $edit_error = "Update failed: " . $conn->error;
    }
}

/* ===== HANDLE: DELETE ROOM ===== */
$del_error = null;
if (isset($_GET['delete_room'])) {
    $del_rid = $conn->real_escape_string($_GET['delete_room']);
    // Only delete if not currently booked
    $chk = $conn->query("SELECT status FROM room WHERE roomid $col = '$del_rid' AND hostelid $col = '$hid'");
    if ($chk && $chk->num_rows > 0) {
        $r = $chk->fetch_assoc();
        if ($r['status'] === 'booked') {
            $del_error = "Cannot delete room '$del_rid' — it is currently booked.";
        } else {
            $conn->query("DELETE FROM room WHERE roomid $col = '$del_rid' AND hostelid $col = '$hid'");
        }
    }
}

/* ===== FETCH DATA ===== */

// Hostel details
$hostel_q = $conn->query("SELECT * FROM hostel WHERE hostelid $col = '$hid'");
$hostel   = $hostel_q ? $hostel_q->fetch_assoc() : [];

// Rooms for this hostel
$rooms_q = $conn->query("SELECT * FROM room WHERE hostelid $col = '$hid' ORDER BY roomid");
$rooms = [];
if ($rooms_q) while ($r = $rooms_q->fetch_assoc()) $rooms[] = $r;

// Bookings for this hostel (with student name)
$bookings_q = $conn->query("
    SELECT b.*, s.fname, s.lname, s.email, r.roomtype, r.price
    FROM booking b
    LEFT JOIN student s ON s.studentid $col = b.studentid $col
    LEFT JOIN room r    ON r.roomid    $col = b.roomid    $col
    WHERE b.hostelid $col = '$hid'
    ORDER BY b.booking_date DESC
");
$bookings = [];
if ($bookings_q) while ($r = $bookings_q->fetch_assoc()) $bookings[] = $r;

// Payments for this hostel (via booking)
$payments_q = $conn->query("
    SELECT p.*, b.studentid, b.roomid, b.booking_date, b.status AS booking_status,
           s.fname, s.lname
    FROM payment p
    JOIN booking b ON b.bookingid $col = p.paymentid $col
    JOIN student s ON s.studentid $col = b.studentid $col
    WHERE b.hostelid $col = '$hid'
    ORDER BY b.booking_date DESC
");
$payments = [];
if ($payments_q) while ($r = $payments_q->fetch_assoc()) $payments[] = $r;

// Stats
$total_rooms     = count($rooms);
$available_rooms = count(array_filter($rooms, fn($r) => $r['status'] === 'available'));
$booked_rooms    = count(array_filter($rooms, fn($r) => $r['status'] === 'booked'));
$total_bookings  = count($bookings);
$confirmed_bk    = count(array_filter($bookings, fn($b) => $b['status'] === 'Confirmed'));
$pending_bk      = count(array_filter($bookings, fn($b) => $b['status'] === 'Pending'));
$total_revenue   = array_sum(array_column($payments, 'amount'));

$active = $_GET['tab'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manager Dashboard — <?php echo htmlspecialchars($hostelname); ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f8ff; display: flex; min-height: 100vh; }

/* SIDEBAR */
.sidebar { width: 240px; background: #2A3F00; color: white; display: flex; flex-direction: column; min-height: 100vh; position: fixed; top: 0; left: 0; z-index: 100; }
.sidebar-header { padding: 22px 18px; border-bottom: 1px solid rgba(255,255,255,0.15); text-align: center; }
.sidebar-header .avatar { width: 64px; height: 64px; background: #fff; color: #2A3F00; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: bold; margin: 0 auto 10px; }
.sidebar-header h3 { font-size: 15px; word-break: break-word; }
.sidebar-header p  { font-size: 12px; opacity: 0.7; margin-top: 3px; }
.sidebar nav { flex: 1; padding: 18px 0; }
.sidebar nav a { display: flex; align-items: center; gap: 12px; padding: 13px 20px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: background 0.2s; }
.sidebar nav a:hover, .sidebar nav a.active { background: rgba(255,255,255,0.15); color: white; }
.sidebar-footer { padding: 14px 20px; border-top: 1px solid rgba(255,255,255,0.15); }
.sidebar-footer a { display: flex; align-items: center; gap: 10px; color: #ff8080; text-decoration: none; font-size: 14px; }

/* MAIN */
.main { margin-left: 240px; flex: 1; display: flex; flex-direction: column; }
.topbar { background: white; padding: 18px 28px; border-bottom: 1px solid #ddd; font-size: 20px; font-weight: bold; color: #2A3F00; }
.content { padding: 24px 28px; }

/* STAT CARDS */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); text-align: center; border-top: 4px solid #2A3F00; }
.stat-card .num { font-size: 32px; font-weight: bold; color: #2A3F00; }
.stat-card .lbl { font-size: 13px; color: #777; margin-top: 5px; }
.stat-card.blue  { border-top-color: #0066cc; } .stat-card.blue .num  { color: #0066cc; }
.stat-card.amber { border-top-color: #e6a817; } .stat-card.amber .num { color: #e6a817; }
.stat-card.red   { border-top-color: #dc3545; } .stat-card.red .num   { color: #dc3545; }
.stat-card.gold  { border-top-color: #c8960c; } .stat-card.gold .num  { color: #c8960c; }

/* TABLES */
.data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.06); margin-bottom: 30px; }
.data-table th { background: #2A3F00; color: white; padding: 12px 14px; text-align: left; font-size: 13px; }
.data-table td { padding: 11px 14px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #f9fdf5; }

/* BADGES */
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
.badge-available { background: #d4edda; color: #155724; }
.badge-booked    { background: #fff3cd; color: #856404; }
.badge-confirmed { background: #cce5ff; color: #004085; }
.badge-pending   { background: #fff3cd; color: #856404; }
.badge-expired   { background: #f8d7da; color: #721c24; }

/* FORMS */
.form-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 26px; max-width: 580px; margin-bottom: 30px; }
.form-card h3 { color: #2A3F00; margin-bottom: 20px; font-size: 17px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.full { grid-column: 1/-1; }
.form-group label { font-size: 13px; font-weight: bold; color: #444; }
.form-group input, .form-group select, .form-group textarea {
    padding: 10px 12px; border: 1px solid #ddd; border-radius: 7px;
    font-size: 14px; background: #fafafa; width: 100%;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #2A3F00; outline: none; background: white; }
.form-group textarea { resize: vertical; min-height: 70px; }
.btn { display: inline-block; padding: 10px 22px; border: none; border-radius: 7px; font-size: 14px; cursor: pointer; color: white; }
.btn-green  { background: #2A3F00; } .btn-green:hover  { background: #3d5c00; }
.btn-blue   { background: #0066cc; } .btn-blue:hover   { background: #0052a3; }
.btn-red    { background: #dc3545; font-size: 12px; padding: 5px 12px; } .btn-red:hover { background: #a71d2a; }
.btn-small  { font-size: 12px; padding: 5px 12px; }

/* ALERTS */
.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
.alert-success { background: #d4edda; color: #155724; }
.alert-error   { background: #f8d7da; color: #721c24; }

h2 { color: #2A3F00; margin-bottom: 16px; font-size: 18px; }
.section-gap { margin-top: 30px; }
.no-data { color: #888; font-size: 14px; padding: 20px; text-align: center; background: white; border-radius: 10px; }
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="avatar"><?php echo strtoupper(substr($hostelname, 0, 1)); ?></div>
        <h3><?php echo htmlspecialchars($hostelname); ?></h3>
        <p>Manager: <?php echo htmlspecialchars($manager_name); ?></p>
    </div>
    <nav>
        <a href="manager_dashboard.php?tab=overview"  class="<?php echo $active==='overview' ?'active':''; ?>">📊 Overview</a>
        <a href="manager_dashboard.php?tab=bookings"  class="<?php echo $active==='bookings' ?'active':''; ?>">📋 Bookings</a>
        <a href="manager_dashboard.php?tab=payments"  class="<?php echo $active==='payments' ?'active':''; ?>">💳 Payments</a>
        <a href="manager_dashboard.php?tab=rooms"     class="<?php echo $active==='rooms'    ?'active':''; ?>">🛏 Rooms</a>
        <a href="manager_dashboard.php?tab=edit"      class="<?php echo $active==='edit'     ?'active':''; ?>">✏️ Edit Hostel</a>
    </nav>
    <div class="sidebar-footer">
        <a href="manager_logout.php"> Logout</a>
    </div>
</div>

<!-- MAIN -->
<div class="main">

<?php if ($active === 'overview'): ?>
<!-- ===== OVERVIEW ===== -->
<div class="topbar">Overview — <?php echo htmlspecialchars($hostelname); ?></div>
<div class="content">

    <div class="stats-grid">
        <div class="stat-card"><div class="num"><?php echo $total_rooms; ?></div><div class="lbl">Total Rooms</div></div>
        <div class="stat-card"><div class="num"><?php echo $available_rooms; ?></div><div class="lbl">Available</div></div>
        <div class="stat-card red"><div class="num"><?php echo $booked_rooms; ?></div><div class="lbl">Booked</div></div>
        <div class="stat-card blue"><div class="num"><?php echo $total_bookings; ?></div><div class="lbl">Total Bookings</div></div>
        <div class="stat-card"><div class="num"><?php echo $confirmed_bk; ?></div><div class="lbl">Confirmed</div></div>
        <div class="stat-card amber"><div class="num"><?php echo $pending_bk; ?></div><div class="lbl">Pending Payment</div></div>
        <div class="stat-card gold"><div class="num">UGX <?php echo number_format($total_revenue); ?></div><div class="lbl">Total Revenue</div></div>
    </div>

    <!-- Hostel Info Card -->
    <h2>Hostel Details</h2>
    <div class="form-card" style="max-width:700px;">
        <table style="width:100%;font-size:14px;border-collapse:collapse;">
            <?php foreach ([
                ['Hostel ID',   $hostel['hostelid']      ?? '-'],
                ['Name',        $hostel['hostelname']     ?? '-'],
                ['Location',    $hostel['location']       ?? '-'],
                ['Overview',    $hostel['overview']       ?? '-'],
                ['Total Rooms', $hostel['rooms']          ?? '-'],
                ['Manager',     $hostel['hostelmanager']  ?? '-'],
            ] as [$label, $val]): ?>
            <tr style="border-bottom:1px solid #f0f0f0;">
                <td style="padding:10px;font-weight:bold;color:#555;width:35%;"><?php echo $label; ?></td>
                <td style="padding:10px;color:#222;"><?php echo htmlspecialchars($val); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Recent Bookings preview -->
    <h2 class="section-gap">Recent Bookings</h2>
    <?php $recent = array_slice($bookings, 0, 5); ?>
    <?php if (empty($recent)): ?>
        <p class="no-data">No bookings yet.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Booking ID</th><th>Student</th><th>Room</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $b): ?>
        <tr>
            <td><?php echo htmlspecialchars($b['bookingid']); ?></td>
            <td><?php echo htmlspecialchars(($b['fname'] ?? '') . ' ' . ($b['lname'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($b['roomid'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($b['booking_date']); ?></td>
            <td><?php
                $cls = match($b['status']) { 'Confirmed'=>'badge-confirmed','Pending'=>'badge-pending','Expired'=>'badge-expired', default=>'badge-booked' };
                echo "<span class='badge $cls'>" . htmlspecialchars($b['status']) . "</span>";
            ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <a href="manager_dashboard.php?tab=bookings" class="btn btn-green">View All Bookings →</a>
    <?php endif; ?>
</div>

<?php elseif ($active === 'bookings'): ?>
<!-- ===== BOOKINGS ===== -->
<div class="topbar"> Students Who Booked Your Hostel</div>
<div class="content">
    <?php if (empty($bookings)): ?>
        <p class="no-data">No bookings for your hostel yet.</p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Booking ID</th>
                <th>Student Name</th>
                <th>Email</th>
                <th>Room</th>
                <th>Room Type</th>
                <th>Price (UGX)</th>
                <th>Booking Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($b['bookingid']); ?></strong></td>
            <td><?php echo htmlspecialchars(($b['fname'] ?? '') . ' ' . ($b['lname'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($b['email'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($b['roomid'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($b['roomtype'] ?? '-'); ?></td>
            <td><?php echo $b['price'] ? number_format($b['price']) : '-'; ?></td>
            <td><?php echo htmlspecialchars($b['booking_date']); ?></td>
            <td><?php
                $cls = match($b['status']) { 'Confirmed'=>'badge-confirmed','Pending'=>'badge-pending','Expired'=>'badge-expired', default=>'badge-booked' };
                echo "<span class='badge $cls'>" . htmlspecialchars($b['status']) . "</span>";
            ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php elseif ($active === 'payments'): ?>
<!-- ===== PAYMENTS ===== -->
<div class="topbar"> Payment Tracking</div>
<div class="content">

    <!-- Revenue summary -->
    <div class="stats-grid" style="margin-bottom:24px;">
        <div class="stat-card gold"><div class="num">UGX <?php echo number_format($total_revenue); ?></div><div class="lbl">Total Revenue</div></div>
        <div class="stat-card blue"><div class="num"><?php echo count($payments); ?></div><div class="lbl">Payments Received</div></div>
        <div class="stat-card amber"><div class="num"><?php echo $pending_bk; ?></div><div class="lbl">Awaiting Payment</div></div>
    </div>

    <?php if (empty($payments)): ?>
        <p class="no-data">No payments recorded for your hostel yet.</p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Payment ID</th>
                <th>Student</th>
                <th>Room</th>
                <th>Booking Date</th>
                <th>Method</th>
                <th>Amount (UGX)</th>
                <th>Booking Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($p['paymentid']); ?></strong></td>
            <td><?php echo htmlspecialchars(($p['fname'] ?? '') . ' ' . ($p['lname'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($p['roomid'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($p['booking_date']); ?></td>
            <td><?php echo htmlspecialchars($p['payment_method'] ?? '-'); ?></td>
            <td><strong><?php echo number_format($p['amount']); ?></strong></td>
            <td><span class="badge badge-confirmed"><?php echo htmlspecialchars($p['booking_status'] ?? '-'); ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php elseif ($active === 'rooms'): ?>
<!-- ===== ROOMS ===== -->
<div class="topbar">🛏 Room Management</div>
<div class="content">

    <?php if ($room_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($room_success); ?></div><?php endif; ?>
    <?php if ($room_error):   ?><div class="alert alert-error">❌ <?php echo htmlspecialchars($room_error); ?></div><?php endif; ?>
    <?php if ($del_error):    ?><div class="alert alert-error">❌ <?php echo htmlspecialchars($del_error); ?></div><?php endif; ?>

    <!-- Add Room Form -->
    <div class="form-card">
        <h3>➕ Add New Room</h3>
        <form method="POST" action="manager_dashboard.php?tab=rooms">
            <div class="form-row">
                <div class="form-group">
                    <label>Room ID</label>
                    <input type="text" name="roomid" placeholder="e.g. R005" required>
                </div>
                <div class="form-group">
                    <label>Room Type</label>
                    <select name="roomtype" required>
                        <option value="">-- Select --</option>
                        <option value="Single">Single</option>
                        <option value="Double">Double</option>
                        <option value="Triple">Triple</option>
                        <option value="Self Contained">Self Contained</option>
                        <option value="Shared">Shared</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Price (UGX)</label>
                    <input type="number" name="price" placeholder="e.g. 500000" min="0" required>
                </div>
            </div>
            <button class="btn btn-green" type="submit" name="add_room">Add Room</button>
        </form>
    </div>

    <!-- Rooms Table -->
    <h2>Your Rooms (<?php echo $total_rooms; ?> total)</h2>
    <?php if (empty($rooms)): ?>
        <p class="no-data">No rooms added yet.</p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr><th>Room ID</th><th>Type</th><th>Price (UGX)</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rooms as $r): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($r['roomid']); ?></strong></td>
            <td><?php echo htmlspecialchars($r['roomtype']); ?></td>
            <td><?php echo number_format($r['price']); ?></td>
            <td>
                <?php $cls = $r['status'] === 'available' ? 'badge-available' : 'badge-booked'; ?>
                <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($r['status']); ?></span>
            </td>
            <td>
                <?php if ($r['status'] !== 'booked'): ?>
                <a href="manager_dashboard.php?tab=rooms&delete_room=<?php echo urlencode($r['roomid']); ?>"
                   class="btn btn-red"
                   onclick="return confirm('Delete room <?php echo $r['roomid']; ?>?')">
                   🗑 Delete
                </a>
                <?php else: ?>
                <span style="color:#999;font-size:12px;">Cannot delete (booked)</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php elseif ($active === 'edit'): ?>
<!-- ===== EDIT HOSTEL ===== -->
<div class="topbar">✏️ Edit Hostel Details</div>
<div class="content">

    <?php if ($edit_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($edit_success); ?></div><?php endif; ?>
    <?php if ($edit_error):   ?><div class="alert alert-error">❌ <?php echo htmlspecialchars($edit_error); ?></div><?php endif; ?>

    <div class="form-card" style="max-width:620px;">
        <h3>✏️ Update Your Hostel Information</h3>
        <form method="POST" action="manager_dashboard.php?tab=edit">
            <div class="form-row">
                <div class="form-group">
                    <label>Hostel ID</label>
                    <input type="text" value="<?php echo htmlspecialchars($hostel['hostelid'] ?? ''); ?>" readonly style="background:#f0f0f0;color:#888;cursor:not-allowed;">
                </div>
                <div class="form-group">
                    <label>Hostel Name</label>
                    <input type="text" name="hostelname" value="<?php echo htmlspecialchars($hostel['hostelname'] ?? ''); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($hostel['location'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Hostel Manager Name</label>
                    <input type="text" name="hostelmanager" value="<?php echo htmlspecialchars($hostel['hostelmanager'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full">
                    <label>Overview / Description</label>
                    <textarea name="overview"><?php echo htmlspecialchars($hostel['overview'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group full">
                    <label>New Password <span style="font-weight:normal;color:#999;">(leave blank to keep current password)</span></label>
                    <input type="password" name="new_password" placeholder="Enter new password to change it">
                </div>
            </div>
            <button class="btn btn-green" type="submit" name="edit_hostel"> Save Changes</button>
        </form>
    </div>
</div>

<?php endif; ?>

</div><!-- /main -->
</body>
</html>
