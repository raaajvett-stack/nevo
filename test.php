<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "wowonder_test";

// اختبار الاتصال
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("❌ فشل الاتصال بـ MySQL: " . $conn->connect_error);
} else {
    echo "✅ الاتصال بـ MySQL ناجح<br>";
}

// اختبار قاعدة البيانات
if ($conn->select_db($db)) {
    echo "✅ الاتصال بقاعدة البيانات ناجح<br>";
} else {
    // محاولة إنشاء قاعدة البيانات
    if ($conn->query("CREATE DATABASE $db")) {
        echo "✅ تم إنشاء قاعدة البيانات بنجاح<br>";
    } else {
        echo "❌ فشل إنشاء قاعدة البيانات: " . $conn->error . "<br>";
    }
}

$conn->close();
?>