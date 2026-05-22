<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/connect.php';

// ✅ Correct column names: studentid, fname, mname, lname, email, gender, contact
$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

$success = null;
$error   = null;

/* ================= HANDLE PROFILE UPDATE ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    $fname   = trim($conn->real_escape_string($_POST['fname']));
    $mname   = trim($conn->real_escape_string($_POST['mname']));
    $lname   = trim($conn->real_escape_string($_POST['lname']));
    $email   = trim($conn->real_escape_string($_POST['email']));
    $gender  = trim($conn->real_escape_string($_POST['gender']));
    $contact = trim($conn->real_escape_string($_POST['contact']));

    $update = $conn->query("
        UPDATE student SET
            fname   = '$fname',
            mname   = '$mname',
            lname   = '$lname',
            email   = '$email',
            gender  = '$gender',
            contact = '$contact'
        WHERE studentid = '$student_id'
    ");

    if ($update) {
        $_SESSION['student_name'] = $fname . ' ' . $lname;
        $student_name = $_SESSION['student_name'];
        $success = "Profile updated successfully!";
    } else {
        $error = "Update failed: " . $conn->error;
    }
}

/* ================= FETCH STUDENT DATA ================= */

$stmt = $conn->prepare("SELECT * FROM student WHERE studentid = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result  = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    echo "Student record not found.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Profile</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f8ff; display: flex; min-height: 100vh; }

/* SIDEBAR */
.sidebar { width: 230px; background: #2A3F00; color: white; display: flex; flex-direction: column; min-height: 100vh; position: fixed; top: 0; left: 0; }
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
.topbar { background: white; padding: 18px 30px; border-bottom: 1px solid #ddd; font-size: 20px; font-weight: bold; color: #2A3F00; }
.content { padding: 25px 30px; }

/* PROFILE CARD */
.profile-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 30px; max-width: 650px; }
.profile-avatar-section { display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
.profile-avatar { width: 75px; height: 75px; background: #2A3F00; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: bold; flex-shrink: 0; }
.profile-avatar-section h2 { color: #2A3F00; margin-bottom: 4px; }
.profile-avatar-section p  { color: #888; font-size: 14px; }

/* FORM */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.full { grid-column: 1 / -1; }
label { font-size: 13px; font-weight: bold; color: #444; }
input[type=text], input[type=email], input[type=tel], select, textarea {
    padding: 10px 12px; border: 1px solid #ddd; border-radius: 7px; font-size: 14px;
    color: #333; background: #fafafa; width: 100%; transition: border-color 0.2s;
}
input:focus, select:focus, textarea:focus { border-color: #2A3F00; outline: none; background: white; }
.readonly-field { background: #f0f0f0 !important; color: #888; cursor: not-allowed; }
textarea { resize: vertical; min-height: 70px; }
.btn-save { display: inline-block; padding: 12px 30px; background: #2A3F00; color: white; border: none; border-radius: 7px; font-size: 15px; cursor: pointer; margin-top: 10px; }
.btn-save:hover { background: #3d5c00; }
.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
.alert-success { background: #d4edda; color: #155724; }
.alert-error   { background: #f8d7da; color: #721c24; }
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
        <a href="student_dashboard_v2.php"> Hostel Booking</a>
        <a href="student_profile.php" class="active">👤 My Profile</a>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php"> Logout</a>
    </div>
</div>

<!-- MAIN -->
<div class="main">
    <div class="topbar"> My Profile</div>
    <div class="content">

        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-avatar-section">
                <div class="profile-avatar"><?php echo strtoupper(substr($student['fname'], 0, 1)); ?></div>
                <div>
                    <h2><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></h2>
                    <p>Student ID: <?php echo htmlspecialchars($student['studentid']); ?></p>
                </div>
            </div>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="fname" value="<?php echo htmlspecialchars($student['fname']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="mname" value="<?php echo htmlspecialchars($student['mname'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="lname" value="<?php echo htmlspecialchars($student['lname']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Student ID</label>
                        <input type="text" class="readonly-field" value="<?php echo htmlspecialchars($student['studentid']); ?>" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">-- Select --</option>
                            <option value="Male"   <?php echo ($student['gender'] === 'Male')   ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($student['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other"  <?php echo ($student['gender'] === 'Other')  ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full">
                        <label>Contact / Address</label>
                        <textarea name="contact"><?php echo htmlspecialchars($student['contact'] ?? ''); ?></textarea>
                    </div>
                </div>

                <button class="btn-save" type="submit" name="update_profile">💾 Save Changes</button>
            </form>
        </div>

    </div>
</div>

</body>
</html>
