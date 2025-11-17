<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if(!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

// الاتصال بقاعدة البيانات
$conn = new mysqli("localhost", "root", "", "walletdb");
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// جلب الرصيد الحالي
$sql_balance = "SELECT balance FROM users WHERE id = $user_id";
$result = $conn->query($sql_balance);
$balance = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['balance'] : 0;

// معالجة عملية التحويل
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_email = $_POST['recipient_email'];
    $amount = floatval($_POST['amount']);

    // التحقق من صحة المبلغ والبريد المستلم
    $recipient_sql = "SELECT id, name, balance FROM users WHERE email = '$recipient_email' LIMIT 1";
    $recipient_result = $conn->query($recipient_sql);

    if ($amount <= 0) {
        $message = "⚠️ الرجاء إدخال مبلغ صالح.";
    } elseif ($amount > $balance) {
        $message = "⚠️ الرصيد غير كافٍ لإتمام التحويل.";
    } elseif (!$recipient_result || $recipient_result->num_rows == 0) {
        $message = "⚠️ المستخدم المستلم غير موجود.";
    } else {
        $recipient = $recipient_result->fetch_assoc();
        $recipient_id = $recipient['id'];

        // خصم المبلغ من حسابك
        $conn->query("UPDATE users SET balance = balance - $amount WHERE id = $user_id");
        // إضافة المبلغ لحساب المستلم
        $conn->query("UPDATE users SET balance = balance + $amount WHERE id = $recipient_id");

        // تسجيل العملية في جدول المعاملات
        $conn->query("INSERT INTO transactions (user_id, date, type, amount, status) VALUES ($user_id, NOW(), 'تحويل إلى $recipient_email', $amount, 'مكتمل')");
        $conn->query("INSERT INTO transactions (user_id, date, type, amount, status) VALUES ($recipient_id, NOW(), 'تم الاستلام من {$user['email']}', $amount, 'مكتمل')");

        $balance -= $amount; // تحديث الرصيد المحلي للعرض
        $message = "✅ تم تحويل " . number_format($amount,2) . " JOD إلى $recipient_email بنجاح!";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تحويل الأموال</title>
<style>
body { font-family: 'Tahoma'; background-color: #f5f6fa; margin:0; padding:0; }
header { background-color: #1e90ff; color:white; text-align:center; padding:15px; font-size:22px; }
.container { width:85%; max-width:600px; margin:40px auto; background:white; border-radius:15px; padding:30px; box-shadow:0 5px 15px rgba(0,0,0,0.1); text-align:center; }
h2 { color:#333; }
input[type="number"], input[type="email"] { width:80%; padding:12px; margin:15px 0; border-radius:8px; border:1px solid #ccc; text-align:center; font-size:16px; }
button { background-color:#1e90ff; color:white; padding:12px 25px; border:none; border-radius:8px; cursor:pointer; font-size:16px; margin:10px; transition:0.3s; }
button:hover { background-color:#187bcd; transform:scale(1.05); }
.message { font-size:16px; margin-top:15px; }
.success { color:green; }
.error { color:red; }
.back-btn { background-color:#333; }
.back-btn:hover { background-color:#575757; }
</style>
</head>
<body>

<header>💱 صفحة تحويل الأموال</header>

<div class="container">
    <h2>رصيدك الحالي: <?php echo number_format($balance,2); ?> JOD</h2>

    <form method="post">
        <input type="email" name="recipient_email" placeholder="البريد الإلكتروني للمستلم" required><br>
        <input type="number" name="amount" placeholder="المبلغ المراد تحويله" step="0.01" min="0" required><br>
        <button type="submit">تحويل الآن</button>
    </form>

    <?php if ($message): ?>
        <div class="message <?php echo (strpos($message,'✅')!==false)?'success':'error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <button class="back-btn" onclick="window.location.href='dashboard.php'">🏠 العودة إلى الصفحة الرئيسية</button>
</div>

</body>
</html>
