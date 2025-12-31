<?php
// 1) POST受け取り
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// 2) DB接続（create_task.phpと同じでOK）
$dsn  = 'mysql:host=127.0.0.1;port=8889;dbname=eisenhower;charset=utf8mb4';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die('接続エラー: ' . $e->getMessage());
}

// 3) 論理削除（deleted_at に現在時刻）
$sql = "UPDATE eisenhower_tasks
        SET deleted_at = NOW()
        WHERE id = :id AND deleted_at IS NULL";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

// 4) 戻る
header('Location: index.php');
exit;
