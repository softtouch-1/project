<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ---------- إعداد اتصال قاعدة البيانات ----------
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "walletdb";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

/* -------------------------
   دوال مساعدة
------------------------- */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($list[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/* أنماط كشف SQLi (تعليمي فقط) */
function looks_like_sqli($input) {
    if ($input === null || $input === '') return false;
    $input = strtolower($input);
    $patterns = [
        "/\bor\b\s*\d+\s*=\s*\d+/",
        "/\bor\b\s*['\"]?1['\"]?\s*=\s*['\"]?1['\"]?/",
        "/--/",
        "/;|\/\*/",
        "/\bunion\b\s+\bselect\b/",
        "/sleep\(/",
        "/benchmark\(/",
        "/\bxp_|sp_/",
        "/\bdrop\s+table\b/",
        "/\bdelete\s+from\b/",
        "/\binsert\s+into\b/",
        "/\bupdate\s+\w+\s+set\b/",
        "/\bcreate\s+table\b/"
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $input)) return true;
    }
    return false;
}

/* رموز المحاكاة الآمنة */
function sim_tokens() {
    return [
        'SIM_SQLI',
        'SIM_INJECT',
        'SIM_UNION',
        'SIM_BREAK',
        'SIM_TEST_ATTACK'
    ];
}

/* هل يحتوي النص على أي رمز محاكاة؟ */
function contains_sim_token($input) {
    if ($input === null || $input === '') return false;
    $tokens = sim_tokens();
    foreach ($tokens as $t) {
        if (stripos($input, $t) !== false) return true;
    }
    return false;
}

/* سجل محاولة الدخول / هجوم */
function log_login_attempt($conn, $email, $user_id, $ip, $ua, $success, $mode, $reason = null) {
    $sql = "INSERT INTO login_attempts (email, user_id, ip, user_agent, success, mode, reason) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $user_id_param = $user_id === null ? 0 : (int)$user_id;
        $stmt->bind_param("sississ", $email, $user_id_param, $ip, $ua, $success, $mode, $reason);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("log_login_attempt prepare failed: " . $conn->error);
    }
}

/* -------------------------
   جاهز بيانات العميل
------------------------- */
$client_ip = get_client_ip();
$client_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

$alert = '';
$message = '';

// تبديل وضعية الأمان (On/Off)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode_action']) && !isset($_POST['login'])) {
    $action = $_POST['mode_action'] === 'on' ? 'secure' : 'weak';
    $_SESSION['mode'] = $action;
    if ($action === 'secure') $_SESSION['just_enabled_secure'] = true;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
if (!empty($_SESSION['just_enabled_secure'])) {
    $alert = "🔒 تم تفعيل وضعية الأمان";
    unset($_SESSION['just_enabled_secure']);
}
$mode = isset($_SESSION['mode']) ? $_SESSION['mode'] : 'weak';

/* -------------------------
   معالجة تسجيل الدخول
------------------------- */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($mode === 'weak') {
        // --- إذا يحتوي أي رمز محاكاة: عرض "محاكاة اختراق" (عرض بيانات القاعدة قراءة فقط) ---
        if (contains_sim_token($email) || contains_sim_token($password)) {
            // سجل الحدث كنجاح محاكاة
            log_login_attempt($conn, $email, 0, $client_ip, $client_ua, 1, $mode, 'simulated_sqli_show_db');

            // *** جلب وعرض محتوى قواعد البيانات للعرض التعليمي ***
            // لاحظ: نعرض القراءة فقط وباستخدام htmlspecialchars لتجنب XSS
            $users_res = $conn->query("SELECT * FROM users");
            $tx_res = $conn->query("SELECT * FROM transactions");

            // صفحة عرض مؤقتة (تعليمية)
            echo "<!doctype html><html dir='rtl'><head><meta charset='utf-8'><title>محاكاة اختراق - عرض قاعدة البيانات</title>
            <style>
              body{font-family:Tahoma, Arial; background:#f6f8fb; color:#222; padding:20px;}
              h1{color:#b30000;}
              table{width:100%;border-collapse:collapse;margin-bottom:24px;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,0.06);}
              th,td{padding:10px;border:1px solid #e6eef7;text-align:right;font-size:13px;}
              th{background:#0b4a6f;color:#fff;}
              .note{margin-bottom:12px;color:#555;}
              .back{display:inline-block;margin-top:12px;padding:8px 12px;background:#0b4a6f;color:#fff;border-radius:8px;text-decoration:none;}
            </style>
            </head><body>";

            // عرض جدول users
            if ($users_res && $users_res->num_rows > 0) {
                echo "<h2>جدول users</h2><table><thead><tr>";
                // استخدم مفاتيح الصف الأول كعناوين
                $first = $users_res->fetch_assoc();
                $cols = array_keys($first);
                foreach ($cols as $c) echo "<th>" . htmlspecialchars($c) . "</th>";
                echo "</tr></thead><tbody>";
                // طبع الصف الأول
                echo "<tr>";
                foreach ($first as $v) echo "<td>" . htmlspecialchars((string)$v) . "</td>";
                echo "</tr>";
                // بقية الصفوف
                while ($r = $users_res->fetch_assoc()) {
                    echo "<tr>";
                    foreach ($cols as $c) echo "<td>" . htmlspecialchars((string)($r[$c] ?? '')) . "</td>";
                    echo "</tr>";
                }
                echo "</tbody></table>";
            } else {
                echo "<h2>جدول users</h2><p>لا توجد بيانات في جدول المستخدمين.</p>";
            }

            // عرض جدول transactions
            if ($tx_res && $tx_res->num_rows > 0) {
                echo "<h2>جدول transactions</h2><table><thead><tr>";
                $firstT = $tx_res->fetch_assoc();
                $colsT = array_keys($firstT);
                foreach ($colsT as $c) echo "<th>" . htmlspecialchars($c) . "</th>";
                echo "</tr></thead><tbody>";
                echo "<tr>";
                foreach ($firstT as $v) echo "<td>" . htmlspecialchars((string)$v) . "</td>";
                echo "</tr>";
                while ($r = $tx_res->fetch_assoc()) {
                    echo "<tr>";
                    foreach ($colsT as $c) echo "<td>" . htmlspecialchars((string)($r[$c] ?? '')) . "</td>";
                    echo "</tr>";
                }
                echo "</tbody></table>";
            } else {
                echo "<h2>جدول transactions</h2><p>لا توجد معاملات حتى الآن.</p>";
            }

            echo "<a class='back' href='login.php'>عودة</a>";
            echo "</body></html>";
            exit;
        }

        // --- الحالة العادية في الضعيف: استعلام قابل للاختراق كما قبل ---
        $sql = "SELECT * FROM users WHERE email='$email' AND password='$password'";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $user = $res->fetch_assoc();
            $_SESSION['user'] = $user;
            $user_id = isset($user['id']) ? (int)$user['id'] : null;
            log_login_attempt($conn, $email, $user_id, $client_ip, $client_ua, 1, $mode, 'success');
            header("Location: dashboard.php");
            exit;
        } else {
            log_login_attempt($conn, $email, 0, $client_ip, $client_ua, 0, $mode, 'wrong_credentials');
            $message = "❌ البريد الإلكتروني أو كلمة المرور غير صحيحة (وضع ضعيف)";
        }

    } else {
        // ---- الوضع المحمي: نمنع رموز المحاكاة وأنماط SQLi ----
        if (contains_sim_token($email) || contains_sim_token($password)) {
            log_login_attempt($conn, $email, 0, $client_ip, $client_ua, 0, $mode, 'sql_injection_sim_token_blocked');
            $message = "🚫 تم رصد إدخال اختبار غير مسموح وتم رفضه.";
        } elseif (looks_like_sqli($email) || looks_like_sqli($password)) {
            log_login_attempt($conn, $email, 0, $client_ip, $client_ua, 0, $mode, 'sql_injection_detected_blocked');
            $message = "🚫 تم رصد إدخال ضار وتم رفضه.";
        } else {
            // prepared statement لمنع الحقن فعليًا
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND password = ?");
            if ($stmt) {
                $stmt->bind_param("ss", $email, $password);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $user = $res->fetch_assoc();
                    $_SESSION['user'] = $user;
                    $user_id = isset($user['id']) ? (int)$user['id'] : null;
                    log_login_attempt($conn, $email, $user_id, $client_ip, $client_ua, 1, $mode, 'success');
                    header("Location: dashboard.php");
                    exit;
                } else {
                    log_login_attempt($conn, $email, 0, $client_ip, $client_ua, 0, $mode, 'wrong_credentials');
                    $message = "❌ البريد الإلكتروني أو كلمة المرور غير صحيحة (وضع محمي)";
                }
                $stmt->close();
            } else {
                error_log("Prepare failed: " . $conn->error);
                log_login_attempt($conn, $email, 0, $client_ip, $client_ua, 0, $mode, 'prepare_failed');
                $message = "حدث خطأ داخلي. الرجاء المحاولة لاحقًا.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
<meta charset="UTF-8">
<title>تسجيل الدخول - مدير المصاريف الذكي</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--brand-1:#1a3d7c;--brand-2:#4e6eb4;--accent:#2c3e50;--success:#2ecc71;--danger:#e74c3c;--muted:#7f8c8d;--card-bg:#ffffff;}
*{box-sizing:border-box;}
body{font-family:'Segoe UI', Tahoma, Geneva, Verdana,sans-serif;background:linear-gradient(135deg,var(--brand-1),var(--brand-2));display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;color:var(--accent);}
.wrapper{width:450px;max-width:95%;background:var(--card-bg);border-radius:16px;box-shadow:0 18px 40px rgba(20,23,55,0.18);overflow:hidden;animation:fadeIn 0.7s ease;display:flex;flex-direction:column;}
.header{padding:28px;background:linear-gradient(90deg,var(--brand-1),var(--brand-2));color:white;text-align:center;}
.header h1{margin:0;font-size:24px;}
.header p{margin:6px 0 0 0;font-size:14px;}
.mode-panel{display:flex;justify-content:space-between;padding:12px 18px;background:#f5f6fa;border-bottom:1px solid #ecf0f1;}
.mode-label{font-weight:700;padding:6px 10px;border-radius:8px;background:#ecf0f1;font-size:13px;}
.container{padding:28px;}
h2{text-align:center;margin-bottom:20px;font-weight:700;color:var(--accent);}
input{width:100%;padding:14px;margin:10px 0;border-radius:10px;border:1px solid #e6e9f2;font-size:15px;background:#fbfdff;transition:all 0.2s ease;}
input:focus{border-color:var(--brand-1);box-shadow:0 6px 20px rgba(78,84,200,0.12);outline:none;}
button{width:100%;padding:12px;border:none;border-radius:10px;font-weight:700;cursor:pointer;transition:all 0.2s ease;}
.login-btn{background:var(--accent);color:#fff;margin-top:10px;}
.register-btn{background:#2b6ea3;color:#fff;margin-top:8px;}
.on-btn{background:var(--success);color:#fff;}
.off-btn{background:var(--danger);color:#fff;}
button:hover{opacity:0.9;}
.alert{background:#e8f5ee;color:#0a6b3a;padding:12px;border-radius:10px;margin-bottom:14px;text-align:center;font-size:14px;}
.message{background:#fdecea;color:#c0392b;padding:12px;border-radius:10px;margin-bottom:14px;text-align:center;font-size:14px;}
.hint{font-size:13px;color:var(--muted);margin-top:12px;text-align:center;}
.footer{text-align:center;padding:12px;font-size:12px;color:var(--muted);border-top:1px solid #ecf0f1;}
.actions{display:flex;gap:10px;flex-direction:column;margin-top:10px;}
.actions button{width:100%;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>مدير المصاريف الذكي</h1>
    <p>تحليلات ذكية لميزانيتك ومصرفك الشخصي</p>
  </div>

  <div class="mode-panel">
    الوضع الحالي: <span class="mode-label"><?php echo htmlspecialchars($mode === 'secure' ? 'محمي (Secure)' : 'ضعيف (Weak)'); ?></span>
    <div style="display:flex;gap:6px;">
      <form method="POST" style="margin:0;"><input type="hidden" name="mode_action" value="on"><button type="submit" class="on-btn">تفعيل الأمان</button></form>
      <form method="POST" style="margin:0;"><input type="hidden" name="mode_action" value="off"><button type="submit" class="off-btn">وضع ضعيف</button></form>
    </div>
  </div>

  <div class="container">
    <?php if ($alert): ?><div class="alert"><?php echo htmlspecialchars($alert); ?></div><?php endif; ?>
    <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <h2>🏦 تسجيل الدخول</h2>
    <form method="POST">
      <input type="email" name="email" placeholder="البريد الإلكتروني" required>
      <input type="password" name="password" placeholder="كلمة المرور" required>
      <div class="actions">
        <button type="submit" name="login" class="login-btn">دخول</button>
        <button type="button" class="register-btn" onclick="location.href='register.php'">إنشاء حساب جديد</button>
      </div>
    </form>
    <div class="hint">في الوضع الضعيف يمكنك اختبار رموز المحاكاة (مثل <code>SIM_SQLI</code>) لتجربة عرض بيانات القاعدة؛ بينما في الوضع المحمي تُمنع هذه المحاولات.</div>
    <div style="margin-top:10px;font-size:12px;color:#666;">قائمة رموز المحاكاة الحالية: <?php echo implode(', ', sim_tokens()); ?></div>
  </div>

  <div class="footer">
    &copy; <?php echo date('Y'); ?> مدير المصاريف الذكي. جميع الحقوق محفوظة.
  </div>
</div>
</body>
</html>