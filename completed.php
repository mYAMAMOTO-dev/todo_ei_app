<?php
// completed.php（完了済み一覧）

// DB接続（index.phpと同じ）
$dsn  = 'mysql:host=127.0.0.1;port=8889;dbname=eisenhower;charset=utf8mb4';
$user = 'root';
$pass = 'root';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// 完了済み（deleted_at が入っているもの）を新しい順で取得
$sql = "
  SELECT id, title, memo, is_important, is_urgent, due_date, deleted_at
  FROM eisenhower_tasks
  WHERE deleted_at IS NOT NULL
  ORDER BY deleted_at DESC, id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$done_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 表示用：象限名
function quadrantLabel(int $imp, int $urg): string
{
    if ($imp === 1 && $urg === 1) return 'すぐやる（重要×緊急）';
    if ($imp === 1 && $urg === 0) return '計画してやる（重要×緊急でない）';
    if ($imp === 0 && $urg === 1) return '任せる（緊急×重要でない）';
    return 'やらない（重要でない×緊急でない）';
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>完了済みタスク</title>
    <style>
        body {
            background: #f6f6f6;
            font-family: system-ui, sans-serif;
            margin: 0;
        }

        .wrapper {
            max-width: 900px;
            margin: 0 auto;
            padding: 16px;
        }

        h1 {
            margin: 8px 0 12px;
        }

        .toplink a {
            text-decoration: none;
            color: #003366;
        }

        .list {
            background: #fff;
            border-radius: 12px;
            padding: 12px 16px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .08);
        }

        .item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
            text-align: left;
        }

        .item:last-child {
            border-bottom: none;
        }

        .meta {
            font-size: 12px;
            color: #555;
            margin-top: 4px;
        }

        .title {
            font-weight: 700;
        }

        .memo {
            margin-top: 4px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .empty {
            padding: 16px;
            color: #555;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="toplink"><a href="index.php">← メインに戻る</a></div>
        <h1>完了済みタスク</h1>

        <div class="list">
            <?php if (empty($done_tasks)): ?>
                <div class="empty">完了済みタスクはありません</div>
            <?php else: ?>
                <?php foreach ($done_tasks as $t): ?>
                    <div class="item">
                        <div class="title"><?php echo htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="meta">
                            象限: <?php echo htmlspecialchars(quadrantLabel((int)$t['is_important'], (int)$t['is_urgent']), ENT_QUOTES, 'UTF-8'); ?>
                            ／ 期日: <?php echo htmlspecialchars($t['due_date'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                            ／ 完了: <?php echo htmlspecialchars($t['deleted_at'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <?php if (!empty($t['memo'])): ?>
                            <div class="memo"><?php echo htmlspecialchars($t['memo'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>