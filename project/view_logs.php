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

// ✅ الصفحة متاحة للجميع

// استعلام لسحب كل السجلات
$sql = "SELECT * FROM login_attempts ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
<meta charset="UTF-8">
<title>سجلات محاولات الدخول</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:'Segoe UI', Tahoma, Geneva, Verdana,sans-serif;background:#f5f6fa;margin:0;padding:0;}
.wrapper{max-width:1200px;margin:30px auto;padding:20px;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.1);}
h1{text-align:center;color:#1a3d7c;}
table{width:100%;border-collapse:collapse;margin-top:20px;}
th,td{padding:10px;border:1px solid #ddd;text-align:center;font-size:14px;}
th{background:#1a3d7c;color:#fff;}
.success{background:#dff0d8;color:#3c763d;}
.failed{background:#fdecea;color:#c0392b;}
.attack{background:#fff3cd;color:#856404;}
</style>
</head>
<body>
<div class="wrapper">
<h1>سجلات محاولات الدخول</h1>
<table>
<tr>
<th>#</th>
<th>البريد الإلكتروني</th>
<th>معرف المستخدم</th>
<th>IP</th>
<th>User Agent</th>
<th>النجاح</th>
<th>الوضع</th>
<th>السبب</th>
<th>الوقت</th>
</tr>
<?php
if($result && $result->num_rows > 0){
    $i = 1;
    while($row = $result->fetch_assoc()){
        // تصنيف الصف للون الخلفية
        $class = ($row['reason'] === 'sql_injection_detected') ? 'attack' : ($row['success'] ? 'success' : 'failed');

        echo "<tr class='{$class}'>";
        echo "<td>".$i++."</td>";
        echo "<td>".htmlspecialchars($row['email'] ?? '-')."</td>";
        echo "<td>".((!empty($row['user_id']) && $row['user_id'] != 0) ? (int)$row['user_id'] : '-')."</td>";
        echo "<td>".htmlspecialchars($row['ip'] ?? '-')."</td>";
        echo "<td>".htmlspecialchars($row['user_agent'] ?? '-')."</td>";

        // التعديل هنا: نعرض "صح" إذا success==1 أو إذا السبب هو sql_injection_detected
        $is_success_display = ((int)$row['success'] === 1) || (isset($row['reason']) && $row['reason'] === 'sql_injection_detected');
        echo "<td>".($is_success_display ? '✅' : '❌')."</td>";

        echo "<td>".htmlspecialchars($row['mode'] ?? '-')."</td>";
        echo "<td>".htmlspecialchars($row['reason'] ?? '-')."</td>";
        echo "<td>".htmlspecialchars($row['created_at'] ?? '-')."</td>";
        echo "</tr>";
    }
}else{
    echo "<tr><td colspan='9'>لا توجد سجلات حتى الآن.</td></tr>";
}
?>
</table>
</div>
</body>
</html>