<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "walletdb";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

$message = '';

// معالجة POST (تسجيل مستخدم جديد)
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if(empty($username) || empty($email) || empty($password)) {
        $message = "❌ يرجى ملء جميع الحقول";
    } else {
        // تحقق إذا البريد موجود مسبقاً
        $check = $conn->query("SELECT * FROM users WHERE email='$email'");
        if($check->num_rows > 0){
            $message = "❌ البريد الإلكتروني موجود مسبقاً";
        } else {
            $sql = "INSERT INTO users (username, email, password, balance) VALUES ('$username','$email','$password', 0)";
            if($conn->query($sql) === TRUE){
                header("Location: login.php");
                exit;
            } else {
                $message = "❌ خطأ أثناء تسجيل الحساب: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
<meta charset="UTF-8">
<title>إنشاء حساب جديد - مدير المصاريف الذكي</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
  --brand-1: #1a3d7c;
  --brand-2: #4e6eb4;
  --accent: #2c3e50;
  --success:#2ecc71;
  --danger:#e74c3c;
  --muted:#7f8c8d;
  --card-bg: #ffffff;
}
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin:0;
    color: var(--accent);
}
.wrapper {
    width: 450px;
    max-width: 95%;
    background: var(--card-bg);
    border-radius: 16px;
    box-shadow: 0 18px 40px rgba(20,23,55,0.18);
    overflow: hidden;
    animation: fadeIn 0.7s ease;
    display:flex;
    flex-direction:column;
}
.header {
    padding: 28px;
    background: linear-gradient(90deg, var(--brand-1), var(--brand-2));
    color:white;
    text-align:center;
}
.header h1 { margin:0; font-size:24px; }
.header p { margin:6px 0 0 0; font-size:14px; }
.container { padding: 28px; }
h2 { text-align:center; margin-bottom:20px; font-weight:700; color:var(--accent); }
input { width:100%; padding:14px; margin:10px 0; border-radius:10px; border:1px solid #e6e9f2; font-size:15px; background:#fbfdff; transition: all 0.2s ease; }
input:focus { border-color: var(--brand-1); box-shadow: 0 6px 20px rgba(78,84,200,0.12); outline:none;}
button { width:100%; padding:12px; border:none; border-radius:10px; font-weight:700; cursor:pointer; transition: all 0.2s ease; }
.register-btn { background: var(--success); color:#fff; margin-top:10px; }
.login-btn { background:#2b6ea3; color:#fff; margin-top:8px; }
button:hover { opacity:0.9; }
.message { background: #fdecea; color: #c0392b; padding:12px; border-radius:10px; margin-bottom:14px; text-align:center; font-size:14px;}
.footer { text-align:center; padding:12px; font-size:12px; color:var(--muted); border-top:1px solid #ecf0f1; }
.actions { display:flex; flex-direction:column; gap:8px; margin-top:10px; }
@keyframes fadeIn { from{opacity:0; transform:translateY(-8px);} to{opacity:1; transform:translateY(0);} }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>مدير المصاريف الذكي</h1>
    <p>أنشئ حسابك الآن لتتبع وتحليل أموالك بذكاء</p>
  </div>

  <div class="container">
    <?php if($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <h2>📝 إنشاء حساب جديد</h2>
    <form method="POST" action="">
      <input type="text" name="username" placeholder="الاسم الكامل" required>
      <input type="email" name="email" placeholder="البريد الإلكتروني" required>
      <input type="password" name="password" placeholder="كلمة المرور" required>
      <div class="actions">
        <button type="submit" class="register-btn">تسجيل</button>
        <button type="button" class="login-btn" onclick="location.href='login.php'">العودة لتسجيل الدخول</button>
      </div>
    </form>
  </div>

  <div class="footer">
    &copy; <?php echo date('Y'); ?> مدير المصاريف الذكي. جميع الحقوق محفوظة.
  </div>
</div>
</body>
</html>