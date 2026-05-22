<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/connect.php';

$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

/* ================= AUTO-EXPIRE BOOKINGS (30 min timer) ================= */
// Any booking still 'Pending' with no payment after 30 mins → free the room
// Fix collation mismatch: use COLLATE on JOIN and WHERE string comparisons
$conn->query("
    UPDATE booking b
    JOIN room r ON b.roomid COLLATE utf8mb4_unicode_ci = r.roomid COLLATE utf8mb4_unicode_ci
    SET b.status = 'Expired', r.status = 'available'
    WHERE b.status COLLATE utf8mb4_unicode_ci = 'Pending'
      AND b.bookingid COLLATE utf8mb4_unicode_ci
          NOT IN (SELECT paymentid COLLATE utf8mb4_unicode_ci FROM payment)
      AND b.booking_date <= NOW() - INTERVAL 30 MINUTE
");
// Also expire by created timestamp if you have one; using booking_date as fallback above.
// For accurate 30-min countdown, we compare booking insert time. Since booking table has
// no created_at column visible, we rely on booking_date for now (see note at bottom).

/* ================= FETCH HOSTELS ================= */
$hostels = $conn->query("SELECT * FROM hostel");
if (!$hostels) die("Hostels query failed: " . $conn->error);

/* ================= FETCH ROOMS ================= */
$rooms           = [];
$selected_hostel = null;

if (!empty($_GET['hostelid'])) {
    $hid      = $conn->real_escape_string($_GET['hostelid']);
    $hostel_q = $conn->query("SELECT * FROM hostel WHERE hostelid='$hid'");
    if ($hostel_q && $hostel_q->num_rows > 0) {
        $selected_hostel = $hostel_q->fetch_assoc();
    }
    $room_q = $conn->query("SELECT * FROM room WHERE hostelid='$hid'");
    if ($room_q) {
        while ($r = $room_q->fetch_assoc()) $rooms[] = $r;
    }
}

/* ================= BOOKING ================= */
$booking_code  = null;
$booking_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $roomid   = $conn->real_escape_string($_POST['roomid']);
    $hostelid = $conn->real_escape_string($_POST['hostelid']);
    $date     = $conn->real_escape_string($_POST['booking_date']);
    $code     = 'BK' . strtoupper(substr(md5(time() . $roomid . $student_id), 0, 8));

    $insert = $conn->query("
        INSERT INTO booking (bookingid, studentid, roomid, hostelid, booking_date, status)
        VALUES ('$code', '$student_id', '$roomid', '$hostelid', '$date', 'Pending')
    ");

    if ($insert) {
        $booking_code = $code;
        $conn->query("UPDATE room SET status='booked' WHERE roomid='$roomid'");
        if ($selected_hostel) {
            $hid    = $conn->real_escape_string($selected_hostel['hostelid']);
            $room_q = $conn->query("SELECT * FROM room WHERE hostelid='$hid'");
            $rooms  = [];
            if ($room_q) while ($r = $room_q->fetch_assoc()) $rooms[] = $r;
        }
    } else {
        $booking_error = $conn->error;
    }
}

/* ================= PAYMENT ================= */
$pay_success = null;
$pay_error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $pay_bookingid = $conn->real_escape_string($_POST['pay_bookingid']);
    $pay_method    = $conn->real_escape_string($_POST['payment_method']);
    $pay_amount    = floatval($_POST['amount']);

    // Verify the booking belongs to this student and is Pending
    $chk = $conn->query("
        SELECT b.*, r.price FROM booking b
        JOIN room r ON b.roomid COLLATE utf8mb4_unicode_ci = r.roomid COLLATE utf8mb4_unicode_ci
        WHERE b.bookingid COLLATE utf8mb4_unicode_ci = '$pay_bookingid'
          AND b.studentid COLLATE utf8mb4_unicode_ci = '$student_id'
          AND b.status COLLATE utf8mb4_unicode_ci = 'Pending'
    ");

    if ($chk && $chk->num_rows > 0) {
        $booking_row = $chk->fetch_assoc();

        // Check not already paid
        $already = $conn->query("SELECT paymentid FROM payment WHERE paymentid COLLATE utf8mb4_unicode_ci = '$pay_bookingid'");
        if ($already && $already->num_rows > 0) {
            $pay_error = "This booking has already been paid.";
        } else {
            $ins = $conn->query("
                INSERT INTO payment (paymentid, payment_method, amount)
                VALUES ('$pay_bookingid', '$pay_method', '$pay_amount')
            ");
            if ($ins) {
                $conn->query("UPDATE booking SET status='Confirmed' WHERE bookingid COLLATE utf8mb4_unicode_ci = '$pay_bookingid'");
                $pay_success = "Payment of UGX " . number_format($pay_amount) . " received! Booking <strong>$pay_bookingid</strong> is now Confirmed.";
            } else {
                $pay_error = "Payment failed: " . $conn->error;
            }
        }
    } else {
        $pay_error = "Booking not found, already paid, or does not belong to you.";
    }
}

/* ================= MY BOOKINGS (for records tab & payment tab) ================= */
$my_bookings = [];
$bq = $conn->query("
    SELECT b.*, r.roomtype, r.price, h.hostelname,
           p.payment_method, p.amount AS paid_amount
    FROM booking b
    LEFT JOIN room r    ON b.roomid   COLLATE utf8mb4_unicode_ci = r.roomid   COLLATE utf8mb4_unicode_ci
    LEFT JOIN hostel h  ON b.hostelid COLLATE utf8mb4_unicode_ci = h.hostelid COLLATE utf8mb4_unicode_ci
    LEFT JOIN payment p ON p.paymentid COLLATE utf8mb4_unicode_ci = b.bookingid COLLATE utf8mb4_unicode_ci
    WHERE b.studentid COLLATE utf8mb4_unicode_ci = '$student_id'
    ORDER BY b.booking_date DESC
");
if ($bq) while ($row = $bq->fetch_assoc()) $my_bookings[] = $row;

/* ================= PENDING BOOKINGS (for payment dropdown) ================= */
$pending_bookings = array_filter($my_bookings, fn($b) => $b['status'] === 'Pending');

/* ================= ACTIVE TAB ================= */
$active = $_GET['tab'] ?? 'booking';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Dashboard</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f8ff; display: flex; min-height: 100vh; }

/* SIDEBAR */
.sidebar { width: 230px; background: #2A3F00; color: white; display: flex; flex-direction: column; min-height: 100vh; position: fixed; top: 0; left: 0; z-index: 100; }
.sidebar-header { padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.15); text-align: center; }
.sidebar-header .avatar { width: 60px; height: 60px; background: #fff; color: #2A3F00; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; margin: 0 auto 10px; }
.sidebar-header h3 { font-size: 14px; word-break: break-word; }
.sidebar-header p  { font-size: 12px; opacity: 0.7; margin-top: 3px; }
.sidebar nav { flex: 1; padding: 20px 0; }
.sidebar nav a { display: flex; align-items: center; gap: 12px; padding: 13px 22px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: background 0.2s; }
.sidebar nav a:hover, .sidebar nav a.active { background: rgba(255,255,255,0.15); color: white; }
.sidebar-footer { padding: 15px 22px; border-top: 1px solid rgba(255,255,255,0.15); }
.sidebar-footer a { display: flex; align-items: center; gap: 10px; color: #ff8080; text-decoration: none; font-size: 14px; }

/* MAIN */
.main { margin-left: 230px; flex: 1; display: flex; flex-direction: column; }
.topbar { background: white; padding: 18px 30px; border-bottom: 1px solid #ddd; font-size: 20px; font-weight: bold; color: #2A3F00; display: flex; align-items: center; justify-content: space-between; }
.content { padding: 25px 30px; }

/* CARDS */
.hostel-card, .room-card, .record-card {
    background: white; padding: 18px 20px; margin: 10px 0;
    border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.hostel-card h3 { color: #2A3F00; margin-bottom: 5px; }
.hostel-card p  { color: #666; font-size: 14px; margin-bottom: 12px; }
.room-card p, .record-card p { margin-bottom: 6px; font-size: 14px; }
.hostel-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 15px; }

/* BUTTONS */
.btn { display: inline-block; padding: 9px 18px; background: #2A3F00; color: white; text-decoration: none; border-radius: 6px; font-size: 14px; border: none; cursor: pointer; }
.btn:hover { background: #3d5c00; }
.btn-outline { background: white; color: #2A3F00; border: 2px solid #2A3F00; }
.btn-outline:hover { background: #f0f8e8; }
.btn-pay { background: #0066cc; }
.btn-pay:hover { background: #0052a3; }
.btn-download { background: #555; font-size: 13px; padding: 7px 14px; }
.btn-download:hover { background: #333; }

/* BADGES */
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
.badge-available  { background: #d4edda; color: #155724; }
.badge-booked     { background: #fff3cd; color: #856404; }
.badge-confirmed  { background: #cce5ff; color: #004085; }
.badge-expired    { background: #f8d7da; color: #721c24; }
.badge-pending    { background: #fff3cd; color: #856404; }

/* ALERTS */
.alert { padding: 13px 18px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
.alert-success { background: #d4edda; color: #155724; }
.alert-error   { background: #f8d7da; color: #721c24; }
.alert-info    { background: #d1ecf1; color: #0c5460; }

/* BOOKING FORM */
.booking-form { display: flex; align-items: center; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
.booking-form input[type=date] { padding: 8px 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }

/* PAYMENT FORM */
.pay-form { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 28px; max-width: 520px; }
.pay-form h3 { color: #2A3F00; margin-bottom: 20px; font-size: 18px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: bold; color: #444; margin-bottom: 6px; }
.form-group select, .form-group input[type=number], .form-group input[type=text] {
    width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 7px;
    font-size: 14px; color: #333; background: #fafafa;
}
.form-group select:focus, .form-group input:focus { border-color: #2A3F00; outline: none; background: white; }
.readonly-field { background: #f0f0f0 !important; color: #888; cursor: not-allowed; }

/* RECORDS TABLE */
.records-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
.records-table th { background: #2A3F00; color: white; padding: 12px 15px; text-align: left; font-size: 13px; }
.records-table td { padding: 11px 15px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
.records-table tr:last-child td { border-bottom: none; }
.records-table tr:hover td { background: #f9f9f9; }

/* TIMER */
.timer-badge { display: inline-flex; align-items: center; gap: 5px; background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
.timer-expired { background: #f8d7da; color: #721c24; }

h2 { color: #2A3F00; margin-bottom: 15px; }
.section-gap { margin-top: 35px; }
.no-records { color: #888; font-size: 14px; padding: 20px; text-align: center; background: white; border-radius: 10px; }
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="avatar"><?php echo strtoupper(substr($student_name, 0, 1)); ?></div>
        <h3><?php echo htmlspecialchars($student_name); ?></h3>
        <p>ID: <?php echo htmlspecialchars($student_id); ?></p>
    </div>
    <nav>
        <a href="student_dashboard_v2.php?tab=booking"  class="<?php echo $active==='booking'  ? 'active':''; ?>"> Hostel Booking</a>
        <a href="student_dashboard_v2.php?tab=payment"  class="<?php echo $active==='payment'  ? 'active':''; ?>"> Make Payment</a>
        <a href="student_dashboard_v2.php?tab=records"  class="<?php echo $active==='records'  ? 'active':''; ?>"> My Records</a>
        <a href="student_profile.php"> My Profile</a>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php"> Logout</a>
    </div>
</div>

<!-- MAIN -->
<div class="main">

<?php
/* ============================================================
   TAB: BOOKING
============================================================ */
if ($active === 'booking'):
?>
    <div class="topbar"> Hostel Booking</div>
    <div class="content">

        <?php if ($booking_code): ?>
            <div class="alert alert-success">
                ✅ Room booked! Your booking code is: <strong><?php echo htmlspecialchars($booking_code); ?></strong><br>
                ⏰ <strong>You have 30 minutes to complete payment</strong> before the room is released.
                Go to <a href="student_dashboard_v2.php?tab=payment" style="color:#155724;font-weight:bold;">Make Payment</a>.
            </div>
        <?php endif; ?>

        <?php if ($booking_error): ?>
            <div class="alert alert-error">❌ Booking failed: <?php echo htmlspecialchars($booking_error); ?></div>
        <?php endif; ?>

        <h2>Available Hostels</h2>
        <div class="hostel-grid">
        <?php while ($h = $hostels->fetch_assoc()): ?>
            <div class="hostel-card">
                <h3><?php echo htmlspecialchars($h['hostelname']); ?></h3>
                <p>📍 <?php echo htmlspecialchars($h['location']); ?></p>
                <a class="btn" href="student_dashboard_v2.php?tab=booking&hostelid=<?php echo urlencode($h['hostelid']); ?>">View Rooms</a>
            </div>
        <?php endwhile; ?>
        </div>

        <?php if ($selected_hostel): ?>
            <h2 class="section-gap">Rooms in <?php echo htmlspecialchars($selected_hostel['hostelname']); ?></h2>
            <?php if (count($rooms) > 0): ?>
                <?php foreach ($rooms as $room): ?>
                    <div class="room-card">
                        <p><strong>Room:</strong> <?php echo htmlspecialchars($room['roomid']); ?></p>
                        <p><strong>Type:</strong> <?php echo htmlspecialchars($room['roomtype']); ?></p>
                        <?php if ($room['status'] !== 'booked'): ?>
                            <p><strong>Price:</strong> UGX <?php echo number_format($room['price']); ?></p>
                        <?php endif; ?>
                        <p><strong>Status:</strong>
                            <?php if ($room['status'] === 'booked'): ?>
                                <span class="badge badge-booked">Booked</span>
                            <?php else: ?>
                                <span class="badge badge-available">Available</span>
                            <?php endif; ?>
                        </p>
                        <?php if ($room['status'] !== 'booked'): ?>
                            <form method="POST" action="student_dashboard_v2.php?tab=booking&hostelid=<?php echo urlencode($selected_hostel['hostelid']); ?>">
                                <input type="hidden" name="roomid"   value="<?php echo htmlspecialchars($room['roomid']); ?>">
                                <input type="hidden" name="hostelid" value="<?php echo htmlspecialchars($selected_hostel['hostelid']); ?>">
                                <div class="booking-form">
                                    <input type="date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                                    <button class="btn" type="submit" name="submit_booking">Book Now</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-records">No rooms found for this hostel.</p>
            <?php endif; ?>
        <?php endif; ?>

    </div>

<?php
/* ============================================================
   TAB: PAYMENT
============================================================ */
elseif ($active === 'payment'):
?>
    <div class="topbar"> Make Payment</div>
    <div class="content">

        <?php if ($pay_success): ?>
            <div class="alert alert-success">✅ <?php echo $pay_success; ?></div>
        <?php endif; ?>
        <?php if ($pay_error): ?>
            <div class="alert alert-error">❌ <?php echo htmlspecialchars($pay_error); ?></div>
        <?php endif; ?>

        <?php if (empty($pending_bookings)): ?>
            <div class="alert alert-info">
                ℹ️ You have no pending bookings awaiting payment.
                <a href="student_dashboard_v2.php?tab=booking" style="color:#0c5460;font-weight:bold;">Make a booking first →</a>
            </div>
        <?php else: ?>

        <div class="pay-form">
            <h3> Pay for Your Booking</h3>

            <form method="POST">
                <div class="form-group">
                    <label>Select Booking Code</label>
                    <select name="pay_bookingid" id="booking_select" onChange="fillAmount(this)" required>
                        <option value="">-- Choose a booking --</option>
                        <?php foreach ($pending_bookings as $pb): ?>
                            <option value="<?php echo htmlspecialchars($pb['bookingid']); ?>"
                                    data-price="<?php echo $pb['price']; ?>"
                                    data-room="<?php echo htmlspecialchars($pb['roomid']); ?>"
                                    data-hostel="<?php echo htmlspecialchars($pb['hostelname']); ?>">
                                <?php echo htmlspecialchars($pb['bookingid']); ?>
                                — <?php echo htmlspecialchars($pb['hostelname']); ?>
                                Room <?php echo htmlspecialchars($pb['roomid']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="booking_info" style="display:none;">
                    <label>Booking Details</label>
                    <input type="text" id="booking_detail" class="readonly-field" readonly>
                </div>

                <!-- 30-min countdown shown per pending booking -->
                <?php foreach ($pending_bookings as $pb): ?>
                    <div class="timer-badge" id="timer_<?php echo $pb['bookingid']; ?>" style="margin-bottom:12px;display:none;">
                        ⏱ Time remaining: <span class="countdown" data-booked="<?php echo $pb['booking_date']; ?>">calculating...</span>
                    </div>
                <?php endforeach; ?>

                <div class="form-group">
                    <label>Amount (UGX)</label>
                    <input type="number" name="amount" id="amount_field" min="1" required placeholder="Auto-filled from room price">
                </div>

                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" required>
                        <option value="">-- Select method --</option>
                        <option value="Mobile Money">Mobile Money</option>
                        <option value="Bank Transfer">Bank </option>
                        <option value="Cash">Cash</option>
                        
                    </select>
                </div>

                <button class="btn btn-pay" type="submit" name="submit_payment">✅ Confirm Payment</button>
            </form>
        </div>

        <script>
        function fillAmount(sel) {
            const opt = sel.options[sel.selectedIndex];
            const price = opt.getAttribute('data-price');
            const room  = opt.getAttribute('data-room');
            const hostel= opt.getAttribute('data-hostel');
            const bookid= opt.value;

            document.getElementById('amount_field').value = price || '';

            const infoBox = document.getElementById('booking_info');
            if (bookid) {
                document.getElementById('booking_detail').value =
                    hostel + ' — Room ' + room + ' — UGX ' + Number(price).toLocaleString();
                infoBox.style.display = 'block';
            } else {
                infoBox.style.display = 'none';
            }

            // Show correct timer
            document.querySelectorAll('[id^="timer_"]').forEach(t => t.style.display = 'none');
            if (bookid) {
                const timer = document.getElementById('timer_' + bookid);
                if (timer) timer.style.display = 'inline-flex';
            }
        }

        // Countdown timers — 30 min from booking_date
        function updateCountdowns() {
            document.querySelectorAll('.countdown').forEach(el => {
                const bookedAt = new Date(el.getAttribute('data-booked'));
                const deadline = new Date(bookedAt.getTime() + 30 * 60 * 1000);
                const now      = new Date();
                const diff     = deadline - now;

                if (diff <= 0) {
                    el.textContent = 'Expired — room may be released';
                    el.closest('.timer-badge').classList.add('timer-expired');
                } else {
                    const m = Math.floor(diff / 60000);
                    const s = Math.floor((diff % 60000) / 1000);
                    el.textContent = m + 'm ' + (s < 10 ? '0' : '') + s + 's';
                }
            });
        }
        updateCountdowns();
        setInterval(updateCountdowns, 1000);
        </script>

        <?php endif; ?>
    </div>

<?php
/* ============================================================
   TAB: RECORDS
============================================================ */
elseif ($active === 'records'):
?>
    <div class="topbar">
         My Booking & Payment Records
        <?php if (!empty($my_bookings)): ?>
            <a class="btn btn-download" href="student_dashboard_v2.php?tab=records&download=1"> </a>
        <?php endif; ?>
    </div>
    <div class="content">

        <?php
        /* ---- CSV DOWNLOAD ---- */
        if (isset($_GET['download']) && $_GET['download'] == 1 && !empty($my_bookings)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="my_bookings_' . $student_id . '_' . date('Ymd') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Booking ID','Hostel','Room','Type','Booking Date','Status','Payment Method','Amount Paid (UGX)']);
            foreach ($my_bookings as $bk) {
                fputcsv($out, [
                    $bk['bookingid'],
                    $bk['hostelname'] ?? '-',
                    $bk['roomid'],
                    $bk['roomtype'] ?? '-',
                    $bk['booking_date'],
                    $bk['status'],
                    $bk['payment_method'] ?? '-',
                    $bk['paid_amount'] ? number_format($bk['paid_amount']) : '-',
                ]);
            }
            fclose($out);
            exit();
        }
        ?>

        <?php if (empty($my_bookings)): ?>
            <p class="no-records">You have no booking records yet.</p>
        <?php else: ?>

            <table class="records-table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Hostel</th>
                        <th>Room</th>
                        <th>Type</th>
                        <th>Booking Date</th>
                        <th>Status</th>
                        <th>Payment Method</th>
                        <th>Amount Paid</th>
                        <th>Timer</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($my_bookings as $bk): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($bk['bookingid']); ?></strong></td>
                        <td><?php echo htmlspecialchars($bk['hostelname'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($bk['roomid']); ?></td>
                        <td><?php echo htmlspecialchars($bk['roomtype'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($bk['booking_date']); ?></td>
                        <td>
                            <?php
                            $st = $bk['status'];
                            $cls = match($st) {
                                'Confirmed' => 'badge-confirmed',
                                'Pending'   => 'badge-pending',
                                'Expired'   => 'badge-expired',
                                default     => 'badge-booked',
                            };
                            ?>
                            <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($st); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($bk['payment_method'] ?? '-'); ?></td>
                        <td>
                            <?php echo $bk['paid_amount'] ? 'UGX ' . number_format($bk['paid_amount']) : '-'; ?>
                        </td>
                        <td>
                            <?php if ($bk['status'] === 'Pending'): ?>
                                <span class="timer-badge">
                                    ⏱ <span class="countdown" data-booked="<?php echo $bk['booking_date']; ?>">...</span>
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <script>
            function updateCountdowns() {
                document.querySelectorAll('.countdown').forEach(el => {
                    const bookedAt = new Date(el.getAttribute('data-booked'));
                    const deadline = new Date(bookedAt.getTime() + 30 * 60 * 1000);
                    const now      = new Date();
                    const diff     = deadline - now;
                    if (diff <= 0) {
                        el.textContent = 'Expired';
                        el.closest('.timer-badge').classList.add('timer-expired');
                    } else {
                        const m = Math.floor(diff / 60000);
                        const s = Math.floor((diff % 60000) / 1000);
                        el.textContent = m + 'm ' + (s < 10 ? '0' : '') + s + 's';
                    }
                });
            }
            updateCountdowns();
            setInterval(updateCountdowns, 1000);
            </script>

        <?php endif; ?>
    </div>

<?php endif; ?>

</div><!-- /main -->
</body>
</html>
