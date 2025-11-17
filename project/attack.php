<?php
session_start();
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "walletdb";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// جلب جميع الجداول وبياناتها
$database_tables = [];
$tables = $conn->query("SHOW TABLES");
while($table = $tables->fetch_array()) {
    $table_name = $table[0];
    $rows = $conn->query("SELECT * FROM $table_name");
    $database_tables[$table_name] = [];
    if($rows && $rows->num_rows > 0) {
        while($row = $rows->fetch_assoc()) {
            $database_tables[$table_name][] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
<meta charset="UTF-8">
<title>عرض قاعدة البيانات - تعليمي</title>
<style>
body{font-family:tahoma;background:#f0f2f5;padding:20px;}
.container{background:white;padding:20px;border-radius:10px;max-width:900px;margin:0 auto;box-shadow:0 5px 15px rgba(0,0,0,0.2);}
table{width:100%;border-collapse:collapse;margin-top:20px;}
th, td{border:1px solid #ccc;padding:8px;text-align:right;}
th{background:#3498db;color:white;}
h2{text-align:center;color:#e74c3c;margin-bottom:20px;}
</style>
</head>
<body>
<div class="container">
<h2>🚨 ثغرة SQL Injection - عرض جميع البيانات (تعليمي)</h2>
<?php
foreach($database_tables as $table_name => $rows) {
    echo "<h3>جدول: $table_name</h3>";
    if(count($rows) > 0){
        echo '<table><tr>';
        foreach(array_keys($rows[0]) as $col) echo "<th>{$col}</th>";
        echo '</tr>';
        foreach($rows as $row) {
            echo '<tr>';
            foreach($row as $cell) echo '<td>'.htmlspecialchars($cell).'</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>لا يوجد بيانات.</p>';
    }
}
?>
<a href="login.php">عودة لتسجيل الدخول</a>
</div>
</body>
</html>