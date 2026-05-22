<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hostel Management System</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #2A3F00 0%, #4a6741 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
header {
    text-align: center;
    color: white;
    margin-bottom: 50px;
}
header h1 { font-size: 36px; margin-bottom: 10px; }
header p  { font-size: 16px; opacity: 0.8; }

.cards {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
    justify-content: center;
}

.card {
    background: white;
    border-radius: 16px;
    padding: 40px 35px;
    text-align: center;
    width: 260px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    transition: transform 0.2s;
}
.card:hover { transform: translateY(-6px); }

.card .icon {
    font-size: 60px;
    margin-bottom: 18px;
}
.card h2 {
    font-size: 22px;
    color: #2A3F00;
    margin-bottom: 10px;
}
.card p {
    font-size: 14px;
    color: #777;
    margin-bottom: 25px;
    line-height: 1.6;
}
.card a {
    display: block;
    padding: 13px;
    background: #2A3F00;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: bold;
    transition: background 0.2s;
}
.card a:hover { background: #3d5c00; }

.card.manager a {
    background: #1a4f8a;
}
.card.manager a:hover { background: #0d3a6b; }

footer {
    color: rgba(255,255,255,0.6);
    font-size: 13px;
    margin-top: 50px;
}
</style>
</head>
<body>

<header>
    <h1>🏨 Hostel Management System</h1>
    <p>Welcome — please select your portal to continue</p>
</header>

<div class="cards">

    <div class="card">
        <div class="icon">🎓</div>
        <h2>Student Portal</h2>
        <p>Login to browse hostels, book rooms, make payments and manage your profile.</p>
        <a href="login.html">Student Login →</a>
    </div>

    <div class="card manager">
        <div class="icon">🏨</div>
        <h2>Manager Portal</h2>
        <p>Login to manage your hostel, track bookings, payments and add rooms.</p>
        <a href="manager_login.php">Manager Login →</a>
    </div>

</div>

<footer>Hostel Management System &copy; <?php echo date('Y'); ?></footer>

</body>
</html>
