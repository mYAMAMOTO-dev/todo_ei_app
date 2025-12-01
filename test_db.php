<?php
echo '<pre>';

$dsn  = 'mysql:host=127.0.0.1;port=8889;dbname=eisenhower;charset=utf8mb4';
$user = 'root';
$pass = 'root';

echo "DSN:  {$dsn}\n";
echo "USER: {$user}\n";
echo "PASS: {$pass}\n\n";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "接続成功しました！\n";
} catch (PDOException $e) {
    echo "接続エラーが発生しました：\n";
    echo $e->getMessage() . "\n";
}
