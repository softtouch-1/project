<?php
session_start();
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user']['id'];
$username = $_SESSION['user']['username'] ?? 'غير معروف';
$email = $_SESSION['user']['email'] ?? 'غير معروف';

$conn = new mysqli("localhost","root","","walletdb");
if($conn->connect_error){ die("فشل الاتصال: ".$conn->connect_error); }

$month = date("Y-m");
$budget = $conn->prepare("SELECT * FROM budgets WHERE user_id=? AND month_year=?");
$budget->bind_param("is",$user_id,$month);
$budget->execute();
$budget_res = $budget->get_result();
$budget_data = $budget_res->fetch_assoc();
if(!$budget_data){
    die("<h2 style='color:white;text-align:center;margin-top:50px;'>⚠ لم تقم بإضافة ميزانية لهذا الشهر.<br><a href='salary.php' style='color:yellow;'>إضافة ميزانية</a></h2>");
}

// عرض رسالة مرة واحدة بعد إعادة التوجيه
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// تصفير daily_spent تلقائيًا إذا تغيّر اليوم
$today = date("Y-m-d");
$check_date = $conn->prepare("SELECT MAX(transaction_date) AS last_date FROM category_transactions WHERE user_id=?");
$check_date->bind_param("i",$user_id);
$check_date->execute();
$check_res = $check_date->get_result();
$last = $check_res->fetch_assoc();
$last_date = $last['last_date'] ?? $today;

if($last_date != $today){
    $reset = $conn->prepare("UPDATE categories SET daily_spent=0 WHERE user_id=?");
    $reset->bind_param("i",$user_id);
    $reset->execute();
}

// إضافة فئة جديدة
if(isset($_POST['add_cat'])){
    $name = $_POST['cat_name'];
    $insert = $conn->prepare("INSERT INTO categories(user_id, category_name) VALUES(?,?)");
    $insert->bind_param("is",$user_id,$name);
    $insert->execute();

    $_SESSION['message'] = "✅ تمت إضافة الفئة بنجاح!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// إضافة مصروف لفئة
if(isset($_POST['add_expense'])){
    $cat = $_POST['cat_select'];
    $amount = $_POST['amount'];
    $desc = $_POST['description'] ?? null;

    $new_remaining = $budget_data['remaining_salary'] - $amount;
    if($new_remaining < 0){
        $_SESSION['message'] = "⚠ لا يوجد مبلغ كافٍ في الميزانية.";
    } else {
        $update = $conn->prepare("UPDATE budgets SET remaining_salary=? WHERE id=?");
        $update->bind_param("di",$new_remaining,$budget_data['id']);
        $update->execute();

        $update2 = $conn->prepare("
            UPDATE categories 
            SET spent_amount = spent_amount + ?, 
                daily_spent = daily_spent + ?
            WHERE user_id=? AND category_name=?
        ");
        $update2->bind_param("ddis",$amount,$amount,$user_id,$cat);
        $update2->execute();

        $log = $conn->prepare("
            INSERT INTO category_transactions(user_id, category_name, amount, transaction_date, description) 
            VALUES(?,?,?,?,?)
        ");
        $log->bind_param("isdss",$user_id,$cat,$amount,$today,$desc);
        $log->execute();

        $_SESSION['message'] = "✅ تمت إضافة المصروف بنجاح!";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// جلب الفئات
$categories = $conn->prepare("SELECT * FROM categories WHERE user_id=?");
$categories->bind_param("i",$user_id);
$categories->execute();
$cat_res = $categories->get_result();
$categories_array = $cat_res->fetch_all(MYSQLI_ASSOC);

// مجموع مصروفات اليوم
$today_stmt = $conn->prepare("
    SELECT SUM(amount) AS total_today 
    FROM category_transactions 
    WHERE user_id=? AND transaction_date=?
");
$today_stmt->bind_param("is", $user_id, $today);
$today_stmt->execute();
$today_res = $today_stmt->get_result();
$today_data = $today_res->fetch_assoc();
$total_today = $today_data['total_today'] ?? 0;
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إدارة الفئات</title>
<style>
body{font-family:Tahoma;background:linear-gradient(135deg,#1e90ff,#187bcd);margin:0;color:white;}
.container{max-width:900px;margin:30px auto;background:#fff;padding:25px;color:black;border-radius:15px;box-shadow:0 10px 25px rgba(0,0,0,0.3);}
h2{color:#1e90ff;text-align:center;margin-bottom:20px;}
.box{background:#f7f7f7;padding:15px;border-radius:10px;margin-bottom:20px;}
input,select,textarea{width:90%;padding:10px;margin-bottom:10px;border:2px solid #1e90ff;border-radius:8px;}
button, .back-btn{background:#1e90ff;color:white;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-size:16px;margin:5px;}
button:hover, .back-btn:hover{background:#004a99;}
table{width:100%;border-collapse:collapse;margin-top:20px;}
th,td{padding:12px;text-align:center;border-bottom:1px solid #eee;}
th{background:#1e90ff;color:white;}
.message{text-align:center;font-size:18px;font-weight:bold;margin-top:10px;}
a.back-btn{text-decoration:none;}
/* عند الطباعة نعرض الاسم والايميل */
#print-area-user{display:none;}
@media print {
    body * {visibility:hidden;}
    #print-area, #print-area * {visibility:visible;}
    #print-area {position:absolute;top:0;left:0;width:100%;}
    #print-area-user{display:block;margin-bottom:10px;}
}
</style>
</head>
<body>

<div class="container">

<h2>📂 إدارة فئات المصاريف</h2>

<h3>💰 المتبقي من الميزانية: <?= $budget_data['remaining_salary'] ?> JOD</h3>

<?php if($message): ?>
<p class="message"><?= $message ?></p>
<?php endif; ?>

<div class="box">
    <h3>➕ إضافة فئة جديدة</h3>
    <form method="post">
        <input type="text" name="cat_name" placeholder="اسم الفئة (مثال: مطاعم)" required>
        <button name="add_cat">إضافة</button>
    </form>
</div>

<div class="box">
    <h3>➖ إضافة مصروف لفئة</h3>
    <form method="post">
        <select name="cat_select" required>
            <?php foreach($categories_array as $c): ?>
            <option><?= $c['category_name'] ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="amount" placeholder="المبلغ" required>
       
        <button name="add_expense">خصم</button>
    </form>
</div>

<div id="print-area">
    <div id="print-area-user">
        <p><strong>اسم المستخدم:</strong> <?= $username ?></p>
        <p><strong>البريد الإلكتروني:</strong> <?= $email ?></p>
    </div>
    <h3>📊 ملخص الفئات</h3>
    <table>
    <tr>
        <th>الفئة</th>
        <th>المبلغ المصروف الشهري</th>
        <th>المصروف اليومي</th>
    </tr>
    <?php foreach($categories_array as $row): ?>
    <tr>
        <td><?= $row['category_name'] ?></td>
        <td><?= $row['spent_amount'] ?> JOD</td>
        <td><?= $row['daily_spent'] ?> JOD</td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>

<div style="text-align:center; margin-top:20px;">
    <button onclick="window.print()">🖨 طباعة الملخص</button>
    <a href="dashboard.php" class="back-btn">⬅ العودة للصفحة الرئيسية</a>
</div>

</div>
</body>
</html>