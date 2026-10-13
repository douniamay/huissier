<?php
// صفحة تثبيت لمرة واحدة: تنشئ الجداول وحساب الأستاذة. احذفها من الاستضافة بعد النجاح.
require __DIR__ . '/api/config.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function page($body) {
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تثبيت</title>'
       . '<style>body{font-family:system-ui,Tahoma;background:#f7f4ef;margin:0;padding:24px;color:#0f1e36}.c{max-width:460px;margin:auto;background:#fff;border:1px solid #ece6db;border-radius:16px;padding:24px}'
       . 'label{display:block;font-weight:700;font-size:13px;margin:14px 0 6px}input[type=text],input[type=email],input[type=password]{width:100%;box-sizing:border-box;padding:11px;border:1px solid #ece6db;border-radius:10px;font-size:15px}'
       . 'button{margin-top:18px;width:100%;padding:13px;border:0;border-radius:10px;background:#0b1d33;color:#fff;font-weight:700;font-size:15px}.e{background:#fef2f2;color:#b4232a;padding:10px;border-radius:10px;margin-top:12px}.ok{background:#ecfdf5;color:#065f46;padding:12px;border-radius:10px}</style></head><body><div class="c">'
       . $body . '</div></body></html>';
    exit;
}

if (strncmp(INSTALL_KEY, 'CHANGE_ME', 9) === 0 || mb_strlen(INSTALL_KEY) < 12) {
    page('<h2>غيّر INSTALL_KEY أولاً</h2><p>افتح <code>api/config.php</code> وضع مفتاحاً طويلاً عشوائياً (12 حرفاً على الأقل) ثم أعد رفع الملف.</p>');
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key   = (string)($_POST['key'] ?? '');
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['pass'] ?? '');
    $conf  = (string)($_POST['conf'] ?? '');
    $reset = !empty($_POST['reset']);

    if (!hash_equals(INSTALL_KEY, $key)) $err = 'مفتاح التثبيت غير صحيح';
    elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'الاسم والبريد مطلوبان بصيغة صحيحة';
    elseif (strlen($pass) < 8) $err = 'كلمة السر 8 أحرف على الأقل';
    elseif ($pass !== $conf) $err = 'كلمتا السر غير متطابقتين';

    if ($err === '') {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $tail = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k VARCHAR(50) PRIMARY KEY, v TEXT NOT NULL) $tail");
            $pdo->exec("CREATE TABLE IF NOT EXISTS services (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(120) NOT NULL, description VARCHAR(500) NOT NULL DEFAULT '', icon VARCHAR(30) NOT NULL DEFAULT 'scale', enabled TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0) $tail");
            $pdo->exec("CREATE TABLE IF NOT EXISTS requests (id INT AUTO_INCREMENT PRIMARY KEY, ref VARCHAR(20) NULL, name VARCHAR(100) NOT NULL, phone VARCHAR(30) NOT NULL, type VARCHAR(40) NOT NULL, wilaya VARCHAR(60) NOT NULL DEFAULT '', details TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'جديد', ip_hash CHAR(64) NOT NULL, created_at DATETIME NOT NULL, INDEX(status), INDEX(created_at), INDEX(ip_hash)) $tail");
            $pdo->exec("CREATE TABLE IF NOT EXISTS admins (id INT AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL) $tail");
            $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id INT AUTO_INCREMENT PRIMARY KEY, ip_hash CHAR(64) NOT NULL, attempted_at DATETIME NOT NULL, INDEX(ip_hash), INDEX(attempted_at)) $tail");

            // قيم أولية: الحقول غير المؤكدة تبقى فارغة فلا تظهر في الموقع
            $seed = ['office_name' => 'مكتب الأستاذة طاهري فتيحة - محضر قضائي', 'lawyer_name' => 'طاهري فتيحة',
                     'years_lawyer' => '20', 'years_bailiff' => '6',
                     'city' => 'تلمسان', 'court' => 'محكمة أولاد ميمون'];
            $ins = $pdo->prepare('INSERT IGNORE INTO settings (k, v) VALUES (?, ?)');
            foreach (['office_name','lawyer_name','tagline','phone','whatsapp','email','address','hours','map_query','license_no','years_lawyer','years_bailiff','cases_count','city','court'] as $k) {
                $ins->execute([$k, $seed[$k] ?? '']);
            }
            if ((int)$pdo->query('SELECT COUNT(*) FROM services')->fetchColumn() === 0) {
                $sv = $pdo->prepare('INSERT INTO services (title, description, icon, enabled, sort_order) VALUES (?,?,?,1,?)');
                $sv->execute(['التبليغ القضائي', 'تبليغ الإعلانات القضائية والسندات التنفيذية الرسمية بسرعة ودقة قانونية مع إعداد محاضر تبليغ معتمدة.', 'scale', 1]);
                $sv->execute(['تنفيذ الأحكام', 'السهر على التنفيذ الجبري للأحكام والأوامر القضائية وفق الأطر القانونية وباحترام حقوق الأطراف.', 'gavel', 2]);
                $sv->execute(['المعاينة الميدانية', 'إنجاز محاضر معاينة واقعية وموضوعية لإثبات الحالة وحفظ الحقوق بكل حياد ومصداقية.', 'eye', 3]);
                $sv->execute(['محضر جمعية عامة', 'معاينة وحضور اجتماعات الشركات والجمعيات العامة وإعداد محاضرها الرسمية المعتمدة قانوناً.', 'building', 4]);
            }

            $exists = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            if ($exists > 0 && !$reset) {
                $err = 'يوجد حساب بالفعل. فعّل خيار «إعادة تعيين الحساب» إن كنت تريد استبداله.';
            } else {
                if ($exists > 0) $pdo->exec('DELETE FROM admins');
                $pdo->prepare('INSERT INTO admins (full_name, email, password_hash, created_at) VALUES (?,?,?,?)')
                    ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
                page('<div class="ok"><b>تم التثبيت بنجاح ✓</b><br>الحساب: ' . h($email) . '</div>'
                   . '<div class="e"><b>مهم جداً:</b> احذف الملف <code>install.php</code> من الاستضافة الآن.</div>');
            }
        } catch (Throwable $e) {
            error_log('[huissier-install] ' . $e->getMessage());
            $err = 'تعذّر الاتصال بقاعدة البيانات. تحقق من القيم في api/config.php';
        }
    }
}

page('<h2 style="margin-top:0">تثبيت لوحة المحضر القضائي</h2>'
   . ($err ? '<div class="e">' . h($err) . '</div>' : '')
   . '<form method="post" autocomplete="off">'
   . '<label>مفتاح التثبيت (INSTALL_KEY من config.php)</label><input type="password" name="key" required>'
   . '<label>الاسم الكامل</label><input type="text" name="name" value="' . h($_POST['name'] ?? 'طاهري فتيحة') . '" required>'
   . '<label>البريد الإلكتروني (للدخول)</label><input type="email" name="email" value="' . h($_POST['email'] ?? '') . '" required>'
   . '<label>كلمة السر (8 أحرف على الأقل)</label><input type="password" name="pass" required>'
   . '<label>تأكيد كلمة السر</label><input type="password" name="conf" required>'
   . '<label style="font-weight:400"><input type="checkbox" name="reset" value="1"> إعادة تعيين الحساب (إن وُجد حساب سابق)</label>'
   . '<button type="submit">تثبيت</button></form>');
