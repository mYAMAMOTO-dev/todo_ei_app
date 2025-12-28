<?php

// POST受け取り
$title = $_POST['title'] ?? '';
$memo = $_POST['memo'] ?? '';
$is_important = $_POST['is_important'] ?? '0';
$is_urgent = $_POST['is_urgent'] ?? '0';
$due_date = $_POST['due_date'] ?? '';

$errors = [];
$inputs = [];

// 入力保持用
$inputs['title']        = $title;
$inputs['memo']         = $memo;
$inputs['is_important'] = $is_important;
$inputs['is_urgent']    = $is_urgent;
$inputs['due_date']     = $due_date;


// タスク名必須
if ($title === '') {
    $errors['title'] = "タスク名は必須です。";
}

// 期日チェック
if ($due_date === '') {
    $errors['due_date'] = "期日は必須です。";
} else {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
        $errors['due_date'] = "期日の形式が正しくありません（YYYY-MM-DD）";
    } else {
        $y = (int)substr($due_date, 0, 4);
        $m = (int)substr($due_date, 5, 2);
        $d = (int)substr($due_date, 8, 2);
        if (!checkdate($m, $d, $y)) {
            $errors['due_date'] = "存在しない日付が指定されています。";
        }
    }
}

// ❗ エラーがあれば入力とエラーを保持して戻す
if (!empty($errors)) {
    // エラーになった項目だけ入力値を消す（他は残す）
    foreach ($errors as $field => $message) {
        $inputs[$field] = '';
    }

    session_start();
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_inputs'] = $inputs;
    header('Location: index.php');
    exit;
}





// $errors = [];

// // =========================================
// // バリデーション
// // =========================================

// // タスク名必須
// if ($title === '') {
//     $errors[] = "タスク名は必須です。";
// }

// // 期日必須
// if ($due_date === '') {
//     $errors[] = "期日は必須です。";
// }

// if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
//     $errors[] = "期日の形式が正しくありません。（YYYY-MM-DD）";
// } else {
//     // 実在する日付かチェック
//     $y = (int)substr($due_date, 0, 4);
//     $m = (int)substr($due_date, 5, 2);
//     $d = (int)substr($due_date, 8, 2);
//     if (!checkdate($m, $d, $y)) {
//         $errors[] = "存在しない日付が指定されています。";
//     }
// }
// // エラーが存在する場合、入力を消す
// if (!empty($errors)) {
//     header('Location: index.php?error=1');
//     exit;
// }

// DB接続
$dsn  = 'mysql:host=127.0.0.1;port=8889;dbname=eisenhower;charset=utf8mb4'; //localhostから変更
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    echo '接続エラー: ' . $e->getMessage();
    exit;
}

// INSERT
$sql =
    // "INSERT INTO tasks (title, memo, is_important, is_urgent, due_date, created_at)
    "INSERT INTO eisenhower_tasks (title, memo, is_important, is_urgent, due_date, created_at)
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
