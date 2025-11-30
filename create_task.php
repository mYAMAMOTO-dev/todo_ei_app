<?php
// POST受け取り
$title = $_POST['title'] ?? '';
$memo = $_POST['memo'] ?? '';
$is_important = $_POST['is_important'] ?? '0';
$is_urgent = $_POST['is_urgent'] ?? '0';
$due_date = $_POST['due_date'] ?? '';

$errors = [];

// バリデーション
if ($title === '') {
    $errors[] = "タスク名は必須です。";
}

if ($due_date === '') {
    $errors[] = "期日は必須です。";
}

if (!empty($errors)) {
    header('Location: index.php?error=1');
    exit;
}

// DB接続
$pdo = new PDO(
    'mysql:host=localhost;dbname=eisenhower;charset=utf8mb4',
    'root',
    ''
);

// INSERT
$sql = "INSERT INTO tasks (title, memo, is_important, is_urgent, due_date, created_at)
        VALUES (:title, :memo, :imp, :urg, :due, NOW())";

$stmt = $pdo->prepare($sql);

$stmt->bindValue(':title', $title, PDO::PARAM_STR);
$stmt->bindValue(':memo', $memo, PDO::PARAM_STR);
$stmt->bindValue(':imp', (int)$is_important, PDO::PARAM_INT);
$stmt->bindValue(':urg', (int)$is_urgent, PDO::PARAM_INT);
$stmt->bindValue(':due', $due_date, PDO::PARAM_STR);

$stmt->execute();

// 一覧へ移動
header('Location: index.php?added=1');
exit;
