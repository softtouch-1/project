<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];

// اتصال قاعدة البيانات
$conn = new mysqli("localhost","root","","walletdb");
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

$message = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // جلب كلمة المرور الحقيقية من قاعدة البيانات
    $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stored_result = $stmt->get_result()->fetch_assoc();
    $stored_password = $stored_result['password'];

    // مقارنة كلمة المرور الحالية نصيًا
    if($current === $stored_password){
        if($new === $confirm){

            // تحديث الكلمة الجديدة نصيًا بدون تشفير
            $stmt_update = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt_update->bind_param("si", $new, $user_id);

            if($stmt_update->execute()){
                $message = "✅ تم تغيير كلمة المرور بنجاح!";
            } else {
                $message = "⚠ حدث خطأ أثناء تحديث كلمة المرور.";
            }

        } else {
            $message = "⚠ كلمة المرور الجديدة غير متطابقة.";
        }
    } else {
        $message = "⚠ كلمة المرور الحالية غير صحيحة.";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تغيير كلمة المرور</title>

<style>
body { 
    font-family: 'Tahoma';
    background: linear-gradient(135deg,#1e90ff,#187bcd);
    margin: 0;
    color: #333;
}
header {
    background: #0066cc;
    color: white;
    padding: 20px;
    text-align: center;
    font-size: 22px;
    font-weight: bold;
}
.container {
    max-width: 450px;
    margin: 60px auto;
    background: #fff;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    text-align: center;
}
h2 {
    color: #1e90ff;
    margin-bottom: 20px;
}
input[type="password"] {
    width: 80%;
    padding: 12px;
    border: 2px solid #1e90ff;
    border-radius: 8px;
    font-size: 16px;
    margin-bottom: 15px;
    text-align: center;
    outline: none;
    transition: 0.3s;
}
input[type="password"]:focus {
    border-color: #187bcd;
    box-shadow: 0 0 5px rgba(30,144,255,0.5);
}
button {
    background-color: #1e90ff;
    color: white;
    border: none;
    padding: 12px 25px;
    font-size: 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.3s;
}
button:hover {
    background-color: #187bcd;
    transform: scale(1.05);
}
.message {
    margin-top: 15px;
    font-size: 16px;
    font-weight: bold;
}
a.back {
    display: inline-block;
    margin-top: 20px;
    color: #1e90ff;
    text-decoration: none;
    font-weight: bold;
}
a.back:hover {
    color: #004a99;
    text-decoration: underline;
}
</style>
</head>

<body>

<header>🔒 تغيير كلمة المرور</header>

<div class="container">
    <h2>أدخل كلمة المرور الحالية والجديدة</h2>

    <form method="POST" action="">
        <input type="password" name="current_password" placeholder="كلمة المرور الحالية" required><br>
        <input type="password" name="new_password" placeholder="كلمة المرور الجديدة" required><br>
        <input type="password" name="confirm_password" placeholder="تأكيد كلمة المرور الجديدة" required><br>
        <button type="submit">تحديث كلمة المرور</button>
    </form>

    <?php if(!empty($message)): ?>
        <p class="message"><?= htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <a href="dashboard.php" class="back">⬅ العودة إلى الصفحة الرئيسية</a>
</div>

</body>
</html>