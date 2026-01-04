<?php
// completed.php（完了済み一覧）

function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function hDate(?string $v): string
{
    return $v ? h($v) : '';
}

function buildQuery(array $base, array $override = []): string
{
    $q = array_merge($base, $override);
    // 空文字はURLから消す（見た目が綺麗＆余計な条件を送らない）
    $q = array_filter($q, fn($v) => $v !== '');
    return http_build_query($q);
}

// 検索条件（GET）
$start = $_GET['start'] ?? '';  // 例: 2025-12-01
$end   = $_GET['end'] ?? '';    // 例: 2025-12-31
$q     = $_GET['q'] ?? 'all';   // all / q1 / q2 / q3 / q4
$preset = $_GET['preset'] ?? '';

$todayDt = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
$today   = $todayDt->format('Y-m-d');
$todayMinus6  = (clone $todayDt)->modify('-6 days')->format('Y-m-d');
$todayMinus29 = (clone $todayDt)->modify('-29 days')->format('Y-m-d');

// 初期表示：今日
if ($start === '' && $end === '' && $preset === '') {
    $start = $today;
    $end   = $today;
}

// 「今日 / 1週間 / 1ヶ月/全期間」短縮ボタンを作る（今日含む）。プリセットが指定されているなら start/end を上書き
if ($preset === 'today') {
    $start = $today;
    $end   = $today;
} elseif ($preset === 'week') {     // 今日含む7日間：-6日〜今日
    $start = $todayMinus6;
    $end   = $today;
} elseif ($preset === 'month') {    // 今日含む30日間：-29日〜今日
    $start = $todayMinus29;
    $end   = $today;
} elseif ($preset === 'all') {
    // 全期間：日付条件を付けない
    $start = '';
    $end   = '';
}

// ==============================
// start > end のときは自動で入れ替える（A）
// ==============================
// 前提：start/end は 'YYYY-MM-DD' か ''（空）
// 空のときは比較しない
if ($start !== '' && $end !== '' && $start > $end) {
    // 入れ替え
    [$start, $end] = [$end, $start];

    // presetを自動推測させるためにクリア（カスタム扱いに寄せる）
    // ※これがないと、URLのpresetが残ったまま表示がズレる可能性がある
    $preset = '';
}

// ボタンの選択状態（表示用）：どのボタンをアクティブにするか（preset優先、無ければ推測）
$activePreset = $preset;

// presetが空のときは、start/endの内容から推測する
if ($activePreset === '') {
    // どちらも今日なら「今日」扱い
    if ($start === $today && $end === $today) {
        $activePreset = 'today';
    }
    // 今日含む7日間：-6日〜今日
    elseif ($start === $todayMinus6 && $end === $today) {
        $activePreset = 'week';
    }
    // 今日含む30日間：-29日〜今日
    elseif ($start === $todayMinus29 && $end === $today) {
        $activePreset = 'month';
    }
    // 両方空なら「全期間」扱い（allボタンの状態に揃える）
    elseif ($start === '' && $end === '') {
        $activePreset = 'all';
    }
    // それ以外は「カスタム」
    else {
        $activePreset = 'custom';
    }
}

// 表示モード名（常に表示する）（activePresetが確定してから作る）
$modeLabel = '';

if ($activePreset === 'today')  $modeLabel = '今日';
elseif ($activePreset === 'week')  $modeLabel = '1週間';
elseif ($activePreset === 'month') $modeLabel = '1ヶ月';
elseif ($activePreset === 'all')   $modeLabel = '全期間';
elseif ($activePreset === 'custom') $modeLabel = 'カスタム';

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

switch ($q) {
    case 'q1':
        $where .= " AND is_important=1 AND is_urgent=1";
        break;
    case 'q2':
        $where .= " AND is_important=1 AND is_urgent=0";
        break;
    case 'q3':
        $where .= " AND is_important=0 AND is_urgent=1";
        break;
    case 'q4':
        $where .= " AND is_important=0 AND is_urgent=0";
        break;
    default:
        // all のときは追加しない
        break;
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

        .is-active {
            font-weight: 700;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <h1>完了済みタスク</h1>

        <p class="completed-link-wrap">
            <a href="index.php" class="btn-sub">← メインに戻る</a>
        </p>

        <?php
        // フォームの現在状態をベースにしたクエリ（短縮ボタンで使う）
        $baseQuery = [
            'q' => $q,
            'start' => $start,
            'end' => $end,
        ];
        ?>

        <form method="get" class="task-form">

            <!-- 開始日 -->
            <div class="form-row">
                <label for="start">開始日</label>
                <input id="start" type="date" name="start" value="<?php echo h($start); ?>">
            </div>

            <!-- 終了日 -->
            <div class="form-row">
                <label for="end">終了日</label>
                <input id="end" type="date" name="end" value="<?php echo h($end); ?>">
            </div>

            <!-- 短縮ボタン（プリセット） -->
            <div class="form-row">

                <a class="<?php echo ($activePreset === 'today') ? 'is-active' : ''; ?>"
                    href="completed.php?<?php echo h(buildQuery($baseQuery, ['preset' => 'today'])); ?>">今日</a>

                <a class="<?php echo ($activePreset === 'week') ? 'is-active' : ''; ?>"
                    href="completed.php?<?php echo h(buildQuery($baseQuery, ['preset' => 'week'])); ?>">1週間</a>

                <a class="<?php echo ($activePreset === 'month') ? 'is-active' : ''; ?>"
                    href="completed.php?<?php echo h(buildQuery($baseQuery, ['preset' => 'month'])); ?>">1ヶ月</a>

                <a class="<?php echo ($activePreset === 'all') ? 'is-active' : ''; ?>"
                    href="completed.php?<?php echo h(buildQuery($baseQuery, ['preset' => 'all'])); ?>">全期間</a>

                <?php if ($activePreset === 'custom'): ?>
                    <span>（カスタム）</span>
                <?php endif; ?>
            </div>

            <!-- 現在の条件表示（常に表示） -->
            <div class="form-row">
                現在：<?php echo h($modeLabel); ?>
                （<?php echo h($start ?: '未指定'); ?> 〜 <?php echo h($end ?: '未指定'); ?>）
            </div>

            <!-- 象限 -->
            <div class="form-row">
                <label>象限:
                    <select name="q">
                        <option value="all" <?php echo $q === 'all' ? 'selected' : ''; ?>>すべて</option>
                        <option value="q1" <?php echo $q === 'q1' ? 'selected' : ''; ?>>すぐやる（重要×緊急）</option>
                        <option value="q2" <?php echo $q === 'q2' ? 'selected' : ''; ?>>計画してやる（重要×緊急でない）</option>
                        <option value="q3" <?php echo $q === 'q3' ? 'selected' : ''; ?>>任せる（緊急×重要でない）</option>
                        <option value="q4" <?php echo $q === 'q4' ? 'selected' : ''; ?>>やらない（重要でない×緊急でない）</option>
                    </select>
                </label>
            </div>

            <div class="form-row-submit">
                <button type="submit">検索</button>
                <a href="completed.php" class="btn-sub" style="margin-left:8px;">リセット</a>
            </div>

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
                            <div class="title"><?php echo h($t['title'] ?? ''); ?>
                            </div>
                            <div class="meta">
                                期日: <?php echo h($t['due_date'] ?? '-'); ?>
                                ／ 完了: <?php echo h($t['deleted_at'] ?? ''); ?>

                            </div>
                            <?php if (!empty($t['memo'])): ?>
                                <div class="memo"><?php echo h($t['memo']); ?></div>
                            <?php endif; ?>


                            <!-- 既に付けた「未完了に戻す」ボタンはここに置いたままでOK -->
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