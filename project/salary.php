<?php
session_start();
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user']['id'];

$conn = new mysqli("localhost","root","","walletdb");
if($conn->connect_error){ die("فشل الاتصال: " . $conn->connect_error); }

$message = "";

// عند إرسال الراتب
if($_SERVER['REQUEST_METHOD'] === "POST"){
    $salary = $_POST['salary'];
    $month = date("Y-m");

    // تحقق إن ميزانية الشهر موجودة أو لا
    $check = $conn->prepare("SELECT * FROM budgets WHERE user_id=? AND month_year=?");
    $check->bind_param("is", $user_id, $month);
    $check->execute();
    $res = $check->get_result();

    if($res->num_rows > 0){
        $message = "⚠ لديك ميزانية مُسجلة لهذا الشهر.";
    } else {
        $insert = $conn->prepare("INSERT INTO budgets(user_id, month_year, total_salary, remaining_salary) VALUES (?,?,?,?)");
        $insert->bind_param("isss", $user_id, $month, $salary, $salary);
        if($insert->execute()){
            $message = "✅ تم تسجيل ميزانية الشهر بنجاح!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إضافة الميزانية الشهرية</title>
<style>
body { font-family:Tahoma; background: linear-gradient(135deg,#1e90ff,#187bcd); margin:0; }
.container { max-width:450px; margin:70px auto; background:#fff; padding:25px; border-radius:15px; text-align:center; box-shadow:0 8px 25px rgba(0,0,0,0.2); }
input { width:80%; padding:12px; border:2px solid #1e90ff; border-radius:8px; font-size:18px; text-align:center; margin-bottom:15px; }
button { background:#0066cc; padding:12px 25px; font-size:18px; border:none; color:white; border-radius:8px; cursor:pointer; }
button:hover { background:#004a99; }
.message { margin-top:15px; font-size:16px; font-weight:bold; }

.back-btn{
    display: inline-block;
    background-color: #1e90ff;
    color: white;
    padding: 10px 25px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 16px;
    font-weight: bold;
    transition: background 0.3s ease;
}
.back-btn:hover{
    background-color: #004a99;
}
</style>
</style>
</head>
<body>

<div class="container">
    <h2>💰 إضافة الميزانية الشهرية</h2>
    <form method="post">
        <input type="number" name="salary" placeholder="أدخل الراتب الشهري" required>
        <button type="submit">حفظ الميزانية</button>
    </form>

    <?php if(!empty($message)): ?>
    <p class="message"><?= $message ?></p>
    <?php endif; ?>
	 <!-- زر العودة للصفحة الرئيسية -->
<div style="text-align:center; margin-top:20px;">
    <a href="dashboard.php" class="back-btn">⬅ العودة للصفحة الرئيسية</a>
</div>

<!-- CSS -->

</div>

</body>
</html>