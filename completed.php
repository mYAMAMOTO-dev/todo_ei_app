<?php
// completed.php（完了済み一覧）

// 検索条件（GET）
$start = $_GET['start'] ?? '';  // 例: 2025-12-01
$end   = $_GET['end'] ?? '';    // 例: 2025-12-31
$q     = $_GET['q'] ?? 'all';   // all / q1 / q2 / q3 / q4
$preset = $_GET['preset'] ?? '';

$todayDt = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
$today   = $todayDt->format('Y-m-d');

// 初期表示：今日
if ($start === '' && $end === '' && $preset === '') {
    $start = $today;
    $end   = $today;
}

// 「今日 / 1週間 / 1ヶ月/全期間」短縮ボタンを作る（今日含む）
if ($preset === 'today') {
    $start = $today;
    $end   = $today;
} elseif ($preset === 'week') {     // 今日含む7日間：-6日〜今日
    $start = (clone $todayDt)->modify('-6 days')->format('Y-m-d');
    $end   = $today;
} elseif ($preset === 'month') {    // 今日含む30日間：-29日〜今日
    $start = (clone $todayDt)->modify('-29 days')->format('Y-m-d');
    $end   = $today;
} elseif ($preset === 'all') {
    // 全期間：日付条件を付けない
    $start = '';
    $end   = '';
}


// DB接続（index.phpと同じ）
$dsn  = 'mysql:host=127.0.0.1;port=8889;dbname=eisenhower;charset=utf8mb4';
$user = 'root';
$pass = 'root';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);


// 「期間＋象限」の組み立て式に置き換え
$where = "deleted_at IS NOT NULL";
$params = [];

// 期間（deleted_at で検索）
if ($start !== '') {
    $where .= " AND deleted_at >= :start";
    $params[':start'] = $start . " 00:00:00";
}
if ($end !== '') {
    $where .= " AND deleted_at <= :end";
    $params[':end'] = $end . " 23:59:59";
}

// 象限（is_important / is_urgent で検索）
if ($q === 'q1') {
    $where .= " AND is_important=1 AND is_urgent=1";
}
if ($q === 'q2') {
    $where .= " AND is_important=1 AND is_urgent=0";
}
if ($q === 'q3') {
    $where .= " AND is_important=0 AND is_urgent=1";
}
if ($q === 'q4') {
    $where .= " AND is_important=0 AND is_urgent=0";
}

$sql = "
  SELECT id, title, memo, is_important, is_urgent, due_date, deleted_at
  FROM eisenhower_tasks
  WHERE $where
  ORDER BY deleted_at DESC, id DESC
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->execute();
$done_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// “象限ごとに分ける”＋“件数集計” を追加
$buckets = [
    'q1' => [],
    'q2' => [],
    'q3' => [],
    'q4' => [],
];

foreach ($done_tasks as $t) {
    $imp = (int)$t['is_important'];
    $urg = (int)$t['is_urgent'];

    if ($imp === 1 && $urg === 1) $buckets['q1'][] = $t;
    elseif ($imp === 1 && $urg === 0) $buckets['q2'][] = $t;
    elseif ($imp === 0 && $urg === 1) $buckets['q3'][] = $t;
    else $buckets['q4'][] = $t;
}

$total = count($done_tasks);
$c1 = count($buckets['q1']);
$c2 = count($buckets['q2']);
$c3 = count($buckets['q3']);
$c4 = count($buckets['q4']);





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

        <!-- 検索ホーム -->
        <form method="get" style="margin:12px 0;">
            <label>開始日:
                <input type="date" name="start" value="<?php echo htmlspecialchars($start, ENT_QUOTES); ?>">
            </label>
            <label>終了日:
                <input type="date" name="end" value="<?php echo htmlspecialchars($end, ENT_QUOTES); ?>">
            </label>

            <!-- 「今日 / 1週間 / 1ヶ月」短縮ボタンを作る（今日含む） -->
            <div style="margin:12px 0;">
                <a href="completed.php?preset=today&q=<?php echo htmlspecialchars($q, ENT_QUOTES); ?>">今日</a>
                <a href="completed.php?preset=week&q=<?php echo htmlspecialchars($q, ENT_QUOTES); ?>">1週間</a>
                <a href="completed.php?preset=month&q=<?php echo htmlspecialchars($q, ENT_QUOTES); ?>">1ヶ月</a>
                <a href="completed.php?preset=all&q=<?php echo htmlspecialchars($q, ENT_QUOTES); ?>">全期間</a>
            </div>


            <label>象限:
                <select name="q">
                    <option value="all" <?php echo $q === 'all' ? 'selected' : ''; ?>>すべて</option>
                    <option value="q1" <?php echo $q === 'q1' ? 'selected' : ''; ?>>すぐやる（重要×緊急）</option>
                    <option value="q2" <?php echo $q === 'q2' ? 'selected' : ''; ?>>計画してやる（重要×緊急でない）</option>
                    <option value="q3" <?php echo $q === 'q3' ? 'selected' : ''; ?>>任せる（緊急×重要でない）</option>
                    <option value="q4" <?php echo $q === 'q4' ? 'selected' : ''; ?>>やらない（重要でない×緊急でない）</option>
                </select>
            </label>

            <button type="submit">検索</button>
            <a href="completed.php" style="margin-left:8px;">リセット</a>
        </form>


        <!-- 一覧表示部分を「象限セクション表示」 -->
        <div class="list">
            <div class="meta" style="margin-bottom:10px;">
                合計: <?php echo $total; ?>件 /
                Q1: <?php echo $c1; ?> /
                Q2: <?php echo $c2; ?> /
                Q3: <?php echo $c3; ?> /
                Q4: <?php echo $c4; ?>
            </div>

            <?php
            $labels = [
                'q1' => 'すぐやる（重要×緊急）',
                'q2' => '計画してやる（重要×緊急でない）',
                'q3' => '任せる（緊急×重要でない）',
                'q4' => 'やらない（重要でない×緊急でない）',
            ];
            ?>

            <?php foreach (['q1', 'q2', 'q3', 'q4'] as $key): ?>
                <h2 style="margin:14px 0 8px;"><?php echo $labels[$key]; ?>（<?php echo count($buckets[$key]); ?>件）</h2>

                <?php if (empty($buckets[$key])): ?>
                    <div class="empty">該当なし</div>
                <?php else: ?>
                    <?php foreach ($buckets[$key] as $t): ?>
                        <div class="item">
                            <div class="title"><?php echo htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="meta">
                                期日: <?php echo htmlspecialchars($t['due_date'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                ／ 完了: <?php echo htmlspecialchars($t['deleted_at'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <?php if (!empty($t['memo'])): ?>
                                <div class="memo"><?php echo htmlspecialchars($t['memo'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>

                            <!-- 既に付けた「未完了に戻す」ボタンはここに置いたままでOK -->
                            <!-- 戻すボタン追加 -->
                            <form action="restore_task.php" method="post" style="margin-top:8px;">
                                <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                                <button type="submit"
                                    onclick="return confirm('未完了に戻しますか？')">未完了に戻す</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</body>

</html>