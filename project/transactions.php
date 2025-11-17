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

// استعلام جميع المعاملات
$sql_tx = "SELECT date, type, amount, status FROM transactions WHERE user_id=? ORDER BY date DESC";
$stmt_tx = $conn->prepare($sql_tx);
$stmt_tx->bind_param("i", $user_id);
$stmt_tx->execute();
$result_tx = $stmt_tx->get_result();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>جميع المعاملات</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family:"Tahoma", sans-serif; background:#f4f6f9; margin:0; direction:rtl; }
header { background:#0066cc; color:white; text-align:center; padding:20px 0; }
header h1 { margin:0; font-size:28px; }
.container { width:90%; margin:30px auto; }
table { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
th, td { padding:12px; text-align:center; border-bottom:1px solid #eee; }
th { background:#0066cc; color:white; }
tr:hover { background:#f9f9f9; }
a.back { display:inline-block; margin-top:20px; color:#0066cc; text-decoration:none; font-weight:bold; transition:0.3s; }
a.back:hover { color:#004a99; text-decoration:underline; }
</style>
</head>
<body>

<header>
    <h1>🧾 جميع المعاملات</h1>
	  <h2>📋 قائمة جميع عمليات الإيداع والسحب</h2>
</header>

<div class="container">
    <table>
        <tr>
            <th>التاريخ</th>
            <th>النوع</th>
            <th>المبلغ</th>
            <th>الحالة</th>
        </tr>
        <?php if ($result_tx->num_rows > 0): ?>
            <?php while($row = $result_tx->fetch_assoc()): ?>
                <tr style="color:<?= strtolower(trim($row['type']))=='سحب' ? '#e74c3c' : '#2c3e50'; ?>">
                    <td><?= htmlspecialchars($row['date']); ?></td>
                    <td><?= htmlspecialchars($row['type']); ?></td>
                    <td><?= number_format($row['amount'],2); ?> JOD</td>
                    <td><?= htmlspecialchars($row['status']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">لا توجد معاملات حتى الآن.</td></tr>
        <?php endif; ?>
    </table>

    <a href="dashboard.php" class="back">⬅ العودة إلى الصفحة الرئيسية</a>
</div>

</body>
</html>