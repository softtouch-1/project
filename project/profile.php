<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

$conn = new mysqli("localhost", "root", "", "walletdb");
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// بيانات المستخدم
$sql_user = "SELECT username, email, balance FROM users WHERE id=?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
$user_data = $res_user->fetch_assoc();

$username = $user_data['username'];
$email = $user_data['email'];
$balance = $user_data['balance'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>ملف المستخدم</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body {
    font-family:"Tahoma", sans-serif;
    background:#f4f6f9;
    margin:0;
    direction:rtl;
}

header {
    background:#0066cc;
    color:white;
    text-align:center;
    padding:20px 0;
}

header h1 {
    margin:0;
    font-size:28px;
}

nav {
    background:#333;
    overflow:hidden;
}

nav a {
    float:right;
    color:#f2f2f2;
    text-align:center;
    padding:14px 20px;
    text-decoration:none;
    font-size:15px;
}

nav a:hover {
    background:#575757;
}

.container {
    width:90%;
    margin:30px auto;
}

.cards {
    display:flex;
    flex-wrap:wrap;
    gap:20px;
    margin-top:20px;
    justify-content:center;
}

.card {
    flex:1 1 200px;
    background:#fff;
    padding:20px;
    border-radius:12px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
    text-align:center;
    transition:0.3s;
}

.card h3 {
    margin:0;
    color:#0066cc;
    font-size:16px;
}

.card p {
    margin-top:10px;
    font-size:18px;
    color:#1e90ff;
}

.card:hover {
    transform:translateY(-5px);
    box-shadow:0 5px 15px rgba(0,0,0,0.15);
}

a.back {
    display:inline-block;
    margin-top:20px;
    color:#0066cc;
    text-decoration:none;
    font-weight:bold;
    transition:0.3s;
}

a.back:hover {
    color:#004a99;
    text-decoration:underline;
}
</style>
</head>
<body>

<header>
    <h1>ملف المستخدم</h1>
</header>

<nav>
    <a href="logout.php">تسجيل الخروج</a>
	<a href="settings.php">تغير كلمة المرور</a>
    <a href="dashboard.php">الرئيسية</a>
	  
</nav>

<div class="container">
    <h2>👤 معلومات المستخدم</h2>
    <div class="cards">
        <div class="card">
            <h3>اسم المستخدم</h3>
            <p><?= htmlspecialchars($username); ?></p>
        </div>
        <div class="card">
            <h3>البريد الإلكتروني</h3>
            <p><?= htmlspecialchars($email); ?></p>
        </div>
        <div class="card">
            <h3>الرصيد الحالي</h3>
            <p><?= number_format($balance,2); ?> JOD</p>
        </div>
    </div>
    <a href="dashboard.php"  class="back">⬅ العودة إلى الصفحة الرئيسية</a>
</div>

</body>
</html>