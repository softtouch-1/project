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
$sql_user = "SELECT balance, username FROM users WHERE id=?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
$user_data = $res_user->fetch_assoc();
$balance = $user_data['balance'];
$username = $user_data['username'];

// آخر 10 معاملات
$sql_tx = "SELECT date, type, amount, status FROM transactions WHERE user_id=? ORDER BY date DESC LIMIT 5";
$stmt_tx = $conn->prepare($sql_tx);
$stmt_tx->bind_param("i", $user_id);
$stmt_tx->execute();
$result_tx = $stmt_tx->get_result();

// التحليل المالي
$sql_summary = "SELECT 
    SUM(CASE WHEN type='سحب' THEN amount ELSE 0 END) AS total_expense,
    SUM(CASE WHEN type='إيداع' THEN amount ELSE 0 END) AS total_income,
    MAX(amount) AS max_tx,
    COUNT(*) AS total_tx,
    AVG(CASE WHEN type='سحب' THEN amount ELSE NULL END) AS avg_expense
FROM transactions WHERE user_id=?";
$stmt_sum = $conn->prepare($sql_summary);
$stmt_sum->bind_param("i", $user_id);
$stmt_sum->execute();
$res_sum = $stmt_sum->get_result();
$summary = $res_sum->fetch_assoc();

$total_expense = $summary['total_expense'] ?? 0;
$total_income = $summary['total_income'] ?? 0;
$max_tx = $summary['max_tx'] ?? 0;
$total_tx = $summary['total_tx'] ?? 0;
$avg_expense = $summary['avg_expense'] ?? 0;

// بيانات الرسم البياني لكل معاملة
$sql_chart = "SELECT 
    DATE_FORMAT(date,'%Y-%m-%d %H:%i') as date_label,
    CASE WHEN type='سحب' THEN amount ELSE 0 END as expense,
    CASE WHEN type='إيداع' THEN amount ELSE 0 END as income
FROM transactions
WHERE user_id=?
ORDER BY date ASC";
$stmt_chart = $conn->prepare($sql_chart);
$stmt_chart->bind_param("i", $user_id);
$stmt_chart->execute();
$res_chart = $stmt_chart->get_result();

$chart_labels = [];
$chart_expense = [];
$chart_income = [];
while($row = $res_chart->fetch_assoc()){
    $chart_labels[] = $row['date_label'];
    $chart_expense[] = $row['expense'];
    $chart_income[] = $row['income'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>مدير المصاريف الذكي</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family:"Tahoma", sans-serif; background:#f4f6f9; margin:0; direction:rtl; }
header { background:#0066cc; color:white; text-align:center; padding:20px 0; }
header h1 { margin:0; font-size:28px; }
header p { margin:5px 0 0; }
nav { background:#333; overflow:hidden; }
nav a { float:right; color:#f2f2f2; text-align:center; padding:14px 20px; text-decoration:none; font-size:15px; }
nav a:hover { background:#575757; }
.container { width:90%; margin:30px auto; }
.cards { display:flex; flex-wrap:wrap; gap:20px; margin-bottom:30px; }
.card { flex:1; min-width:180px; background:#fff; padding:20px; border-radius:12px; box-shadow:0 3px 10px rgba(0,0,0,0.1); text-align:center; }
.card h3 { margin:0; color:#333; font-size:16px; }
.card p { margin-top:10px; font-size:22px; color:#0066cc; }
.actions { margin:25px 0; text-align:center; }
.actions button { background:#0066cc; color:white; padding:10px 20px; margin:10px; border:none; border-radius:8px; cursor:pointer; transition:0.3s; }
.actions button:hover { background:#004a99; }
table { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
th, td { padding:12px; text-align:center; border-bottom:1px solid #eee; }
th { background:#0066cc; color:white; }
tr:hover { background:#f9f9f9; }
footer { text-align:center; font-size:13px; color:#777; padding:15px 0; background:#f1f1f1; margin-top:40px; border-top:1px solid #ddd; }
canvas { background:#fff; border-radius:12px; padding:15px; box-shadow:0 3px 10px rgba(0,0,0,0.1); margin-bottom:30px; }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<header>
  <h1>مرحباً، <?php echo htmlspecialchars($username); ?> 👋</h1>
  <p>مدير المصاريف الذكي - تحكم كامل بمصاريفك وإيراداتك</p>
</header>

<nav>
  <a href="logout.php">تسجيل الخروج</a>
    <a href="transactions.php">المعاملات</a>
	    <a href="reports.php">التقارير والطباعة</a>

	    <a href="profile.php">المعلومات الشخصية</a>


  <a href="dashboard.php">الرئيسية</a>
</nav>

<div class="container">
  <div class="cards">
    <div class="card"><h3>رصيدك الحالي</h3><p><?php echo number_format($balance,2); ?> JOD</p></div>
    <div class="card"><h3>إجمالي المصاريف</h3><p><?php echo number_format($total_expense,2); ?> JOD</p></div>
    <div class="card"><h3>إجمالي الإيرادات</h3><p><?php echo number_format($total_income,2); ?> JOD</p></div>
    <div class="card"><h3>أكبر عملية</h3><p><?php echo number_format($max_tx,2); ?> JOD</p></div>
    <div class="card"><h3>عدد العمليات الكلي</h3><p><?php echo $total_tx; ?></p></div>
    <div class="card"><h3>متوسط المصاريف</h3><p><?php echo number_format($avg_expense,2); ?> JOD</p></div>
  </div>
<div class="actions">
    <button onclick="location.href='deposit.php'">إيداع</button>
    <button onclick="location.href='withdraw.php'">سحب</button>
	<button onclick="location.href='salary.php'">الراتب الشهري</button>
		
 <button onclick="location.href='categories.php'">تخصيص الراتب</button>
  </div>
  <h3>📊  رسم بياني لمصاريف والإيرادات لكل المعاملات</h3>
  <canvas id="financeChart" height="150"></canvas>

  <h3>🧾 آخر المعاملات</h3>
  <table>
    <tr><th>التاريخ</th><th>النوع</th><th>المبلغ</th><th>الحالة</th></tr>
    <?php if ($result_tx->num_rows>0): ?>
      <?php while($row=$result_tx->fetch_assoc()): ?>
        <tr style="color:<?php echo ($row['type']=='سحب')?'#e74c3c':'#2c3e50'; ?>">
          <td><?php echo htmlspecialchars($row['date']); ?></td>
          <td><?php echo htmlspecialchars($row['type']); ?></td>
          <td><?php echo number_format($row['amount'],2); ?> JOD</td>
          <td><?php echo htmlspecialchars($row['status']); ?></td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="4">لا توجد معاملات حتى الآن.</td></tr>
    <?php endif; ?>
  </table>
</div>

<footer>© <?php echo date("Y"); ?> جميع الحقوق محفوظة - مدير المصاريف الذكي</footer>

<script>
const ctx = document.getElementById('financeChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [
            { label: 'المصاريف', data: <?php echo json_encode($chart_expense); ?>, borderColor:'#e74c3c', fill:false, tension:0.2 },
            { label: 'الإيرادات', data: <?php echo json_encode($chart_income); ?>, borderColor:'#2c3e50', fill:false, tension:0.2 }
        ]
    },
    options: {
        responsive:true,
        plugins:{ legend:{ position:'top' } },
        scales: { y:{ beginAtZero:true } }
    }
});
</script>

</body>
</html>