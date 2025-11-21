<?php
echo "<h3>🔍 فحص اتصال قاعدة البيانات - WoWonder</h3>";
echo "<p>المسار: http://localhost/dashboard/wowond/</p><hr>";

// اختبار جميع المستخدمين المحتملين
$host = "localhost";
$database = "wowonder_test";
$users = [
    ['root', ''],
    ['wowonder_test', ''],
    ['wowonder', '']
];

$success = false;

foreach ($users as $user) {
    list($username, $password) = $user;
    
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ccc;'>";
    echo "<strong>جاري اختبار المستخدم:</strong> '$username' بكلمة مرور فارغة...<br>";
    
    // اختبار الاتصال بـ MySQL
    $conn = @new mysqli($host, $username, $password);
    
    if ($conn->connect_error) {
        echo "❌ <span style='color:red'>فشل الاتصال بـ MySQL: " . $conn->connect_error . "</span><br>";
    } else {
        echo "✅ <span style='color:green'>نجاح الاتصال بـ MySQL!</span><br>";
        
        // اختبار قاعدة البيانات
        if ($conn->select_db($database)) {
            echo "✅ <span style='color:green'>قاعدة البيانات '$database' موجودة</span><br>";
            $success = true;
        } else {
            echo "❌ <span style='color:red'>قاعدة البيانات '$database' غير موجودة</span><br>";
            echo "📝 <em>سيحاول السكريبت إنشاؤها تلقائياً</em><br>";
        }
        
        $conn->close();
    }
    echo "</div>";
}

if ($success) {
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; margin: 10px 0;'>";
    echo "<h4>✅ جاهز للتثبيت!</h4>";
    echo "<p>الاتصال بقاعدة البيانات ناجح. يمكنك المتابعة إلى صفحة التثبيت.</p>";
    echo "<a href='install/' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>بدء التثبيت</a>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; margin: 10px 0;'>";
    echo "<h4>❌ هناك مشكلة</h4>";
    echo "<p>لم يتمكن أي مستخدم من الاتصال بقاعدة البيانات. راجع إعدادات MySQL.</p>";
    echo "</div>";
}

// فحص ملفات مهمة
echo "<hr><h3>📁 فحص الملفات المهمة</h3>";
$important_files = [
    'config.php' => 'ملف الإعدادات الرئيسي',
    'install/index.php' => 'صفحة التثبيت',
    'wowonder.sql' => 'قاعدة البيانات',
    '.htaccess' => 'إعدادات الخادم'
];

foreach ($important_files as $file => $description) {
    if (file_exists($file)) {
        echo "✅ <strong>$file:</strong> $description - <span style='color:green'>موجود</span><br>";
        
        // التحقق من الصلاحيات
        if ($file == 'config.php') {
            if (is_writable($file)) {
                echo "&nbsp;&nbsp;✅ صلاحيات الكتابة: <span style='color:green'>ممنوح</span><br>";
            } else {
                echo "&nbsp;&nbsp;❌ صلاحيات الكتابة: <span style='color:red'>ممنوع</span><br>";
            }
        }
    } else {
        echo "❌ <strong>$file:</strong> $description - <span style='color:red'>غير موجود</span><br>";
    }
}
?>