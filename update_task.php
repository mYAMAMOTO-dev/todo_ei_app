<?php
session_start();

// 1) POST受け取り
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title = trim($_POST['title'] ?? '');
$memo  = $_POST['memo'] ?? '';
$isImportant = isset($_POST['is_important']) ? (int)$_POST['is_important'] : 0;
$isUrgent    = isset($_POST['is_urgent']) ? (int)$_POST['is_urgent'] : 0;
$dueDate = $_POST['due_date'] ?? '';

if ($id <= 0 || $title === '' || $dueDate === '') {
    header('Location: index.php');
    exit;
}

// 2) DB接続
$dsn  = 'mysql:host=127.0.0.1;port=8889;dbname=eisenhower;charset=utf8mb4';
$user = 'root';
$pass = 'root';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// 3) UPDATE（deleted_at IS NULL を条件に入れて安全に）
$sql = "
  UPDATE eisenhower_tasks
  SET
    title = :title,
    memo = :memo,
    is_important = :imp,
    is_urgent = :urg,
    due_date = :due,
    updated_at = NOW()
  WHERE id = :id
    AND deleted_at IS NULL
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':title', $title, PDO::PARAM_STR);
$stmt->bindValue(':memo', $memo, PDO::PARAM_STR);
$stmt->bindValue(':imp', $isImportant, PDO::PARAM_INT);
$stmt->bindValue(':urg', $isUrgent, PDO::PARAM_INT);
$stmt->bindValue(':due', $dueDate, PDO::PARAM_STR);
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

header('Location: index.php');
exit;
