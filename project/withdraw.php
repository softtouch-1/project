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

// جلب الرصيد الحالي
$sql_user = "SELECT balance FROM users WHERE id=?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
$user_data = $res_user->fetch_assoc();
$balance = $user_data['balance'];

$message = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    if ($amount > 0 && $amount <= $balance) {
        // تحديث الرصيد
        $conn->query("UPDATE users SET balance = balance - $amount WHERE id = $user_id");

        // إضافة العملية
        $conn->query("INSERT INTO transactions (user_id, date, type, amount, status)
                      VALUES ($user_id, NOW(), 'سحب', $amount, 'مكتمل')");

        $message = "✅ تم سحب " . number_format($amount,2) . " JOD بنجاح!";
        $success = true;
    } else {
        $message = ($amount > $balance) ? "⚠ الرصيد غير كافٍ." : "⚠ الرجاء إدخال مبلغ صالح.";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>عملية السحب</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body {
    font-family:"Tahoma", sans-serif;
    background:#f4f6f9;
    margin:0;
    direction:rtl;
    color:#333;
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

.container {
    background:#fff;
    max-width:450px;
    margin:80px auto;
    padding:40px;
    border-radius:15px;
    box-shadow:0 10px 25px rgba(0,0,0,0.2);
    text-align:center;
    animation:fadeIn 1s ease-in-out;
}

@keyframes fadeIn {
    from {opacity:0; transform:translateY(20px);}
    to {opacity:1; transform:translateY(0);}
}

h2 {
    color:#0066cc;
    margin-bottom:20px;
}

input[type="number"] {
    width:80%;
    padding:12px;
    border:2px solid #0066cc;
    border-radius:12px;
    font-size:16px;
    text-align:center;
    margin-bottom:20px;
    outline:none;
    transition:0.3s;
}

input[type="number"]:focus {
    border-color:#004a99;
    box-shadow:0 0 8px rgba(0,102,204,0.3);
}

button {
    background-color:#0066cc;
    color:white;
    border:none;
    padding:12px 25px;
    font-size:16px;
    border-radius:12px;
    cursor:pointer;
    transition:0.3s;
}

button:hover {
    background-color:#004a99;
    transform:scale(1.05);
}

.message {
    margin-top:15px;
    font-size:16px;
    font-weight:bold;
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

/* نافذة منبثقة Modal */
.modal {
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.6);
    justify-content:center;
    align-items:center;
    animation:fadeIn 0.4s ease-in-out;
    z-index:1000;
}

.modal-content {
    background:#fff;
    padding:40px;
    border-radius:15px;
    text-align:center;
    box-shadow:0 5px 20px rgba(0,0,0,0.3);
    animation:scaleUp 0.4s ease-in-out;
    max-width:400px;
    margin:0 20px;
}

@keyframes scaleUp {
    from {transform:scale(0.8); opacity:0;}
    to {transform:scale(1); opacity:1;}
}

.modal-content h3 {
    color:#0066cc;
    margin-bottom:15px;
    font-size:22px;
}

.modal-content p {
    font-size:16px;
    margin-bottom:20px;
}

.modal-content button {
    background-color:#0066cc;
    padding:10px 20px;
    border-radius:8px;
    color:#fff;
    font-weight:bold;
    border:none;
    cursor:pointer;
    transition:0.3s;
}

.modal-content button:hover {
    background-color:#004a99;
}
</style>
</head>
<body>

<header>
  <h1>سحب الأموال</h1>
</header>

<div class="container">
    <h2>💸 أدخل المبلغ للسحب</h2>
    <p>رصيدك الحالي: <?= number_format($balance,2) ?> JOD</p>
    <form method="POST" action="">
        <input type="number" name="amount" placeholder="المبلغ (JOD)" step="0.01" required><br>
        <button type="submit">تأكيد السحب</button>

    </form>
    <?php if (!empty($message)): ?>
        <p class="message"><?= htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <a href="dashboard.php" class="back">⬅ العودة إلى الصفحة الرئيسية</a>
</div>

<?php if ($success): ?>
<div class="modal" id="successModal" style="display:flex;">
  <div class="modal-content">
    <h3>تمت العملية بنجاح ✅</h3>
    <p><?= htmlspecialchars($message); ?></p>
    <button onclick="document.getElementById('successModal').style.display='none'">حسناً</button>
  </div>
</div>
<?php endif; ?>
</body>
</html>