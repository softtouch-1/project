<?php
session_start();
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user = $_SESSION['user'];
$user_id = $user['id'];

$conn = new mysqli("localhost","root","","walletdb");
if($conn->connect_error){ die("فشل الاتصال: ".$conn->connect_error); }

// إحصائيات دقيقة حسب عمليات المستخدم
$stmt_stats = $conn->prepare("
    SELECT
        SUM(CASE WHEN type='سحب' THEN amount ELSE 0 END) AS total_expense,
        SUM(CASE WHEN type='إيداع' THEN amount ELSE 0 END) AS total_income,
        COUNT(*) AS total_tx,
        AVG(CASE WHEN type='سحب' THEN amount ELSE NULL END) AS avg_expense,
        MAX(amount) AS max_tx
    FROM transactions
    WHERE user_id=?
");
$stmt_stats->bind_param("i",$user_id);
$stmt_stats->execute();
$res_stats = $stmt_stats->get_result();
$stats = $res_stats->fetch_assoc();

// جميع المعاملات (مرتبة من الأحدث)
$stmt_tx = $conn->prepare("SELECT id, date, type, amount, status FROM transactions WHERE user_id=? ORDER BY date DESC");
$stmt_tx->bind_param("i",$user_id);
$stmt_tx->execute();
$res_tx = $stmt_tx->get_result();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تقارير / ملخصات</title>
<style>
body { font-family:'Tahoma'; background: linear-gradient(135deg,#1e90ff,#187bcd); margin:0; color:#333; }
header { background:#0066cc; color:white; padding:20px; text-align:center; font-size:22px; font-weight:bold; }
nav { background:#004a99; overflow:hidden; }
nav a { float:right; display:block; color:#f2f2f2; text-align:center; padding:14px 20px; text-decoration:none; transition:0.3s; }
nav a:hover { background-color:#003366; }
.container { max-width:1200px; margin:30px auto; padding:20px; }
.cards { display:flex; flex-wrap:wrap; gap:20px; justify-content:center; margin-bottom:30px; }
.card { flex:1 1 220px; background:#fff; padding:20px; border-radius:12px; text-align:center; box-shadow:0 5px 20px rgba(0,0,0,0.2); }
.card h3 { color:#1e90ff; margin-bottom:10px; }
.card p { font-size:18px; color:#333; font-weight:bold; }
table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 5px 20px rgba(0,0,0,0.2); margin-top:20px; }
th, td { padding:12px; text-align:center; border-bottom:1px solid #eee; }
th { background:#1e90ff; color:#fff; }
tr:hover { background:#f2f2f2; }
.withdraw { color:#e74c3c; font-weight:bold; }
.deposit { color:#2c3e50; font-weight:bold; }
button.print-btn { background:#0066cc; color:#fff; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; margin-bottom:20px; font-size:16px; }
button.print-btn:hover { background:#004a99; transform:scale(1.05); transition:0.3s; }

/* معلومات المستخدم للطباعة */
.print-user-info { display:none; font-size:16px; margin-bottom:15px; font-weight:bold; }

/* تنسيق الطباعة */
@media print {
    body { background:#fff; color:#000; font-size:14px; }
    nav, .print-btn { display:none !important; }
    .container, table { box-shadow:none; border-radius:0; }
    .print-user-info { display:block !important; }
}
</style>
</head>
<body>

<header>📊 تقارير وملخصات المحفظة</header>

<div class="container">

    <!-- معلومات المستخدم للطباعة -->
    <div class="print-user-info">
        اسم المستخدم: <?= htmlspecialchars($user['username']); ?><br>
        البريد الإلكتروني: <?= htmlspecialchars($user['email']); ?>
    </div>

    <div class="cards">
        <div class="card">
            <h3>إجمالي المصاريف</h3>
            <p><?= number_format($stats['total_expense'],2); ?> JOD</p>
        </div>
        <div class="card">
            <h3>إجمالي الإيرادات</h3>
            <p><?= number_format($stats['total_income'],2); ?> JOD</p>
        </div>
        <div class="card">
            <h3>أكبر عملية</h3>
            <p><?= number_format($stats['max_tx'],2); ?> JOD</p>
        </div>
        <div class="card">
            <h3>عدد العمليات</h3>
            <p><?= $stats['total_tx']; ?></p>
        </div>
        <div class="card">
            <h3>متوسط المصاريف</h3>
            <p><?= number_format($stats['avg_expense'],2); ?> JOD</p>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">🖨 طباعة التقرير</button>

    <h3>🧾 جميع المعاملات</h3>
    <table>
        <tr>
            <th>التاريخ</th>
            <th>النوع</th>
            <th>المبلغ</th>
            <th>الحالة</th>
        </tr>
        <?php if($res_tx->num_rows>0): ?>
            <?php while($row=$res_tx->fetch_assoc()): ?>
                <tr class="<?= $row['type']=='سحب'?'withdraw':'deposit'; ?>">
                    <td><?= date('d/m/Y H:i', strtotime($row['date'])); ?></td>
                    <td><?= $row['type']; ?></td>
                    <td><?= number_format($row['amount'],2); ?> JOD</td>
                    <td><?= $row['status']; ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">لا توجد معاملات حتى الآن.</td></tr>
        <?php endif; ?>
    </table>
<br>
    <nav>
        <a href="dashboard.php">⬅ العودة إلى الصفحة الرئيسية</a>
    </nav>

</div>

</body>
</html>
