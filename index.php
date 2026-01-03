<!-- 4象限に表示（Read） -->

<?php
// ==============================
// セッション開始
// ==============================
session_start();
// ==============================
// バリデーションエラーや入力値の復元用
// （create_task.php から戻ってきた時用）
// ==============================

$form_errors = $_SESSION['form_errors'] ?? [];
$form_inputs = $_SESSION['form_inputs'] ?? [];
// 使い終わったら消す
unset($_SESSION['form_errors'], $_SESSION['form_inputs']);


// DB接続
$dsn  = 'mysql:host=127.0.0.1;port=8889;dbname=eisenhower;charset=utf8mb4';
$user = 'root';
$pass = 'root';

// PDOでDB接続（例外モード）
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);


// 今日の日付（期限切れ判定用）
$today = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');


/**
 * 象限ごとにタスクを取得する共通関数
 */
function fetchTasksByQuadrant(
    PDO $pdo,
    string $today,
    int $isImportant,
    int $isUrgent
): array {

    // 論理削除されていないタスクだけを取得
    // 重要度・緊急度で象限を分ける
    // 期限切れ → 期日なし → 通常 の順で並び替え

    $sql = "
      SELECT *
      FROM eisenhower_tasks
      WHERE deleted_at IS NULL
        AND is_important = :imp
        AND is_urgent = :urg
      ORDER BY
        CASE
          WHEN due_date IS NOT NULL AND due_date < :today THEN 0   -- 期限切れを上に
          ELSE 1
        END ASC,
        CASE
          WHEN due_date IS NULL THEN 1                             -- 期日なしは最後
          ELSE 0
        END ASC,
        due_date ASC,
        created_at ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':imp', $isImportant, PDO::PARAM_INT);
    $stmt->bindValue(':urg', $isUrgent, PDO::PARAM_INT);
    $stmt->bindValue(':today', $today, PDO::PARAM_STR);
    $stmt->execute();
    // 配列で返す
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 各象限のタスクを取得
$tasks_q1 = fetchTasksByQuadrant($pdo, $today, 1, 1); // 重要×緊急
$tasks_q2 = fetchTasksByQuadrant($pdo, $today, 1, 0); // 重要×緊急でない
$tasks_q3 = fetchTasksByQuadrant($pdo, $today, 0, 1); // 重要でない×緊急
$tasks_q4 = fetchTasksByQuadrant($pdo, $today, 0, 0); // 重要でない×緊急でない
?>


<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>アイゼンハワーマトリクス配色プレビュー</title>
    <style>
        body {
            background-color: #f6f6f6;
            font-family: sans-serif;
            text-align: center;
            padding: 20px;
        }

        .quadrant {
            border-radius: 12px;
            padding: 20px;
            color: #333;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .q1 {
            background-color: #FFCCCC;
            color: #660000;
        }

        .q2 {
            background-color: #CCEEFF;
            color: #003366;
        }

        .q3 {
            background-color: #FFF0B3;
            color: #665500;
        }

        .q4 {
            background-color: #DDFFDD;
            color: #004400;
        }

        .button {
            display: inline-block;
            background-color: #2E8B57;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            margin-top: 10px;
        }

        .expired .due {
            color: red;
            opacity: 0.9;
        }

        .task-form {
            max-width: 900px;
            margin: 0 auto 24px;
            padding: 16px 20px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            text-align: left;
        }

        .form-row {
            margin-bottom: 10px;
        }

        .required {
            color: #C00000;
            font-size: 12px;
            margin-left: 4px;
        }

        .form-row input[type="text"],
        .form-row input[type="date"],
        .form-row textarea {
            width: 100%;
            padding: 6px 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        .form-row-submit {
            text-align: right;
        }

        .form-row-submit button {
            padding: 6px 16px;
            border-radius: 999px;
            border: none;
            background: #2E8B57;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
        }

        /* --------------- */
        /* ベース：スマホ */
        /* --------------- */

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f6f6f6;
        }

        .wrapper {
            min-height: 100vh;
            padding: 16px;
        }

        /* 4象限コンテナ：まずは1列 */
        .matrix {
            display: grid;
            grid-template-columns: 1fr;
            /* スマホは1列 */
            gap: 16px;
        }

        /* 象限カード（背景色は今のままでOK） */
        .quadrant {
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }

        /* 象限タイトル（常に表示） */
        .quadrant h2 {
            margin: 0 0 8px;
            font-size: 16px;
        }

        /* 中のタスクカード */

        /* カード本体 */
        .card {
            position: relative;
            background: #fff;
            border-radius: 8px;
            padding: 8px 10px 40px;
            margin-top: 8px;
            text-align: left;
        }

        /* 期日・タイトル・メモの共通スタイル */
        .card .due,
        .card .title,
        .card .memo {
            font-size: 14px;
            line-height: 1.4;
        }

        /* メモ1行省略 */
        .card .memo {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            margin-top: 2px;
        }

        /* 完了ボタン（右下固定） */
        .card .button,
        .card .btn-done {
            position: absolute;
            right: 10px;
            bottom: 10px;
            margin-top: 0;
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            border: none;
            background-color: #2E8B57;
            color: #fff;
            font-size: 13px;
            cursor: pointer;
        }



        /* ---------------------- */
        /* タブレット以上：2列    */
        /* ---------------------- */
        @media (min-width: 768px) {
            .wrapper {
                padding: 24px;
            }

            .matrix {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                /* 2×2 */
                gap: 20px;
                max-width: 900px;
                margin: 0 auto;
                /* だんだん中央寄せに寄っていく感じ */
            }

            .quadrant h2 {
                font-size: 17px;
            }

            .card .due,
            .card .title,
            .card .memo {
                font-size: 14px;
            }

        }

        /* ---------------------- */
        /* PC：幅を絞って中央寄せ */
        /* ---------------------- */
        @media (min-width: 1200px) {
            .matrix {
                max-width: 1000px;
                /* 横幅を狭くするポイント */
                gap: 24px;
            }

            .quadrant {
                padding: 20px;
            }

            .quadrant h2 {
                font-size: 18px;
            }

            .card .due,
            .card .title,
            .card .memo {
                font-size: 15px;
            }
        }

        /* ---- modal ---- */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 9999;
        }

        .modal-overlay.is-open {
            display: flex;
        }

        .modal {
            width: min(720px, 100%);
            background: #fff;
            border-radius: 12px;
            padding: 14px 16px 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            text-align: left;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .modal-close {
            border: none;
            background: transparent;
            font-size: 22px;
            cursor: pointer;
        }

        /* =========================
   タスク登録アコーディオン
   ========================= */

        /* 外枠（見た目はお好みで調整OK） */
        .task-accordion {
            max-width: 900px;
            margin: 0 auto 12px;
        }

        /* ヘッダー（スマホでだけ表示する） */
        .task-accordion__header {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            /* まず中央寄せ（後で左右配置にもできる） */
            gap: 8px;

            padding: 8px 12px;
            border-radius: 6px;

            border: 1px solid #2E8B57;
            background: #fff;
            color: #2E8B57;

            cursor: pointer;
        }

        /* + / - の見た目 */
        .task-accordion__icon {
            font-weight: 700;
        }

        /* ------- スマホ：初期は閉じる ------- */
        /* bodyはデフォルト非表示 */
        .task-accordion__body {
            display: none;
        }

        /* 開いている時だけ表示 */
        .task-accordion.is-open .task-accordion__body {
            display: block;
        }

        /* ------- PC/タブレット：折りたたみOFF ------- */
        @media (min-width: 768px) {

            /* ヘッダーは表示しない（記号が謎にならない） */
            .task-accordion__header {
                display: none;
            }

            /* フォームは常に表示（is-open有無に関係なく） */
            .task-accordion__body {
                display: block;
            }
        }

        /* =========================
   タスクカード（.card）見た目調整
   ========================= */

        /* クリックできるカード感 */
        .card {
            cursor: pointer;
            /* “押せる”感 */
            border: 1px solid rgba(0, 0, 0, 0.06);
            transition: transform 120ms ease, box-shadow 120ms ease, border-color 120ms ease;
        }

        /* hover（PC向け） */
        .card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.10);
            border-color: rgba(0, 0, 0, 0.12);
        }

        /* キーボード操作でも見えるように（将来のための保険） */
        .card:focus-within {
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.18);
            border-color: rgba(46, 139, 87, 0.45);
        }

        /* 期日：少し小さく、目立ちすぎない */
        .card .due {
            font-size: 12.5px;
            opacity: 0.9;
            margin-bottom: 2px;
        }

        /* タイトル：少し強調 */
        .card .title {
            font-weight: 700;
            margin-bottom: 2px;

            /* 長いタイトルが崩れないように（念のため） */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* メモ：1行省略（すでにあるが “見た目” を整える） */
        .card .memo {
            opacity: 0.85;
        }

        /* -----------------------------
   高さを揃える（ここが本題）
   ----------------------------- */

        /*
  今の card は padding: 8px 10px 40px; で
  下にボタン分の余白を確保しています。
  「高さを揃える」ために min-height を入れます。

  ※数字は“まずの目安”です。タスク量を見ながら調整します。
*/
        .card {
            min-height: 112px;
            /* 高さを揃える核：まずこれで */
            padding-bottom: 44px;
            /* 右下ボタンのための確保（既存と同等） */
        }

        /* ボタンとカードクリックの干渉を減らす（押しやすさ） */
        .card .btn-done {
            cursor: pointer;
            /* ボタンはボタンで押せる感 */
        }

        /* 完了ボタンの中のフォームがカード全体を覆わない保険 */
        .card form {
            margin: 0;
        }

        /* 期限切れ表示（既存の .expired .due を活かしつつ、カード全体も少しだけ差別化したい場合）
   ※強すぎると見づらいので薄く */
        .card.expired {
            border-color: rgba(192, 0, 0, 0.20);
        }

        /* =========================
   サブボタン（完了済みリンク用）
   ========================= */

        .completed-link-wrap {
            max-width: 900px;
            margin: 0 auto 14px;
            text-align: left;
        }

        .btn-sub {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;

            border: 1px solid rgba(0, 0, 0, 0.22);
            background: rgba(255, 255, 255, 0.85);
            color: #333;

            font-size: 13px;
            text-decoration: none;
        }

        /* 押せる感（PC向け） */
        .btn-sub:hover {
            background: #fff;
            border-color: rgba(0, 0, 0, 0.35);
        }

        /* キーボードでも分かる */
        .btn-sub:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.10);
        }
    </style>
</head>

<body>
    <h1>アイゼンハワーマトリクス</h1>
    <div class="wrapper">

        <!-- 登録フォーム アコーディオン化-->
        <div class="task-accordion<?php echo !empty($form_errors) ? ' is-open' : ''; ?>">
            <button type="button" class="task-accordion__header">
                <span>タスク登録</span>
                <span class="task-accordion__icon">＋</span>
            </button>

            <div class="task-accordion__body">
                <form action="create_task.php" method="post" class="task-form">
                    <!-- タスク名 -->
                    <div class="form-row">
                        <label for="title">タスク名<span class="required">*</span></label>
                        <!-- <input type="text" id="title" name="title" maxlength="24" required> -->
                        <input type="text"
                            id="title"
                            name="title"
                            maxlength="24"
                            value="<?php echo htmlspecialchars($form_inputs['title'] ?? '', ENT_QUOTES); ?>"
                            required>

                        <?php if (!empty($form_errors['title'])): ?>
                            <div class="field-error">
                                <?php echo htmlspecialchars($form_errors['title'], ENT_QUOTES); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- メモ -->
                    <div class="form-row">
                        <label for="memo">メモ</label>
                        <textarea id="memo" name="memo" rows="2"><?php echo htmlspecialchars($form_inputs['memo'] ?? '', ENT_QUOTES); ?></textarea>
                    </div>

                    <!-- 重要 -->
                    <div class="form-row">
                        <span class="label">重要度<span class="required">*</span></span>
                        <label><input type="radio" name="is_important" value="1" checked> 重要</label>
                        <label><input type="radio" name="is_important" value="0"> 重要ではない</label>
                    </div>

                    <!-- 緊急 -->
                    <div class="form-row">
                        <span class="label">緊急度<span class="required">*</span></span>
                        <label><input type="radio" name="is_urgent" value="1" checked> 緊急</label>
                        <label><input type="radio" name="is_urgent" value="0"> 緊急ではない</label>
                    </div>

                    <!-- 期日 -->
                    <div class="form-row">
                        <label for="due_date">期日<span class="required">*</span></label>
                        <!-- Chromeでの手入力がYYYYYY/MM/DDになる。SafariではYYYY/MM/DD。1回目はこのまま進めて、JS導入時に修正する2025/12/28 -->
                        <!-- <input type="date" id="due_date" name="due_date" required> -->
                        <input type="date"
                            id="due_date"
                            name="due_date"
                            value="<?php echo htmlspecialchars($form_inputs['due_date'] ?? '', ENT_QUOTES); ?>"
                            required>

                        <?php if (!empty($form_errors['due_date'])): ?>
                            <div class="field-error">
                                <?php echo htmlspecialchars($form_errors['due_date'], ENT_QUOTES); ?>
                            </div>
                        <?php endif; ?>

                    </div>

                    <!-- 登録 -->
                    <div class="form-row-submit">
                        <button type="submit">登録</button>
                    </div>

                </form>
            </div>

            <p class="completed-link-wrap">
                <a href="completed.php" class="btn-sub">完了済みタスクを見る</a>
            </p>

            <div class="matrix">

                <!-- Q1：すぐやる（重要×緊急） -->
                <section class="quadrant q1">
                    <h2>すぐやる（重要 × 緊急）</h2>

                    <?php if (empty($tasks_q1)): ?>
                        <p>タスクはありません</p>
                    <?php else: ?>
                        <?php foreach ($tasks_q1 as $task): ?>
                            <?php
                            $isOverdue = !empty($task['due_date']) && $task['due_date'] < $today;
                            ?>
                            <!-- JSが読み取るためのデータ -->
                            <div class="card<?php echo $isOverdue ? ' expired' : ''; ?>"
                                data-id="<?php echo (int)$task['id']; ?>"
                                data-title="<?php echo htmlspecialchars($task['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-memo="<?php echo htmlspecialchars($task['memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-due="<?php echo htmlspecialchars($task['due_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-imp="<?php echo (int)$task['is_important']; ?>"
                                data-urg="<?php echo (int)$task['is_urgent']; ?>">

                                <!-- ここは「表示専用」 -->
                                <div class="due">
                                    期日:
                                    <?php echo htmlspecialchars($task['due_date'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="title">
                                    <?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <!-- 一覧では1行省略表示 -->
                                <div class="memo">
                                    <?php echo htmlspecialchars($task['memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <!-- 完了ボタンを “form POST” に -->
                                <form action="complete_task.php" method="post">
                                    <input type="hidden" name="id" value="<?php echo (int)$task['id']; ?>">
                                    <button type="submit" class="button btn-done">完了</button>
                                </form>
                                <!-- <button class="button btn-done" data-id="<?php echo (int)$task['id']; ?>">
                            完了
                        </button> -->
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- Q２：計画してやる（重要×緊急でない） -->
                <section class="quadrant q2">
                    <h2>計画してやる（重要×緊急でない）</h2>

                    <?php if (empty($tasks_q2)): ?>
                        <p>タスクはありません</p>
                    <?php else: ?>
                        <?php foreach ($tasks_q2 as $task): ?>
                            <?php
                            $isOverdue = !empty($task['due_date']) && $task['due_date'] < $today;
                            ?>
                            <!-- JSが読み取るためのデータ -->
                            <div class="card<?php echo $isOverdue ? ' expired' : ''; ?>"
                                data-id="<?php echo (int)$task['id']; ?>"
                                data-title="<?php echo htmlspecialchars($task['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-memo="<?php echo htmlspecialchars($task['memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-due="<?php echo htmlspecialchars($task['due_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-imp="<?php echo (int)$task['is_important']; ?>"
                                data-urg="<?php echo (int)$task['is_urgent']; ?>">
                                <!-- ここは「表示専用」 -->
                                <div class="due">
                                    期日:
                                    <?php echo htmlspecialchars($task['due_date'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="title">
                                    <?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="memo">
                                    <?php echo htmlspecialchars($task['memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <form action="complete_task.php" method="post">
                                    <input type="hidden" name="id" value="<?php echo (int)$task['id']; ?>">
                                    <button type="submit" class="button btn-done">完了</button>
                                </form>

                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- Q3：任せる（緊急×重要でない） -->
                <section class="quadrant q3">
                    <h2>任せる（緊急×重要でない）</h2>

                    <?php if (empty($tasks_q3)): ?>
                        <p>タスクはありません</p>
                    <?php else: ?>
                        <?php foreach ($tasks_q3 as $task): ?>
                            <?php
                            $isOverdue = !empty($task['due_date']) && $task['due_date'] < $today;
                            ?>
                            <!-- JSが読み取るためのデータ -->
                            <div class="card<?php echo $isOverdue ? ' expired' : ''; ?>"
                                data-id="<?php echo (int)$task['id']; ?>"
                                data-title="<?php echo htmlspecialchars($task['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-memo="<?php echo htmlspecialchars($task['memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-due="<?php echo htmlspecialchars($task['due_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-imp="<?php echo (int)$task['is_important']; ?>"
                                data-urg="<?php echo (int)$task['is_urgent']; ?>">
                                <!-- ここは「表示専用」 -->
                                <div class="due">
                                    期日:
                                    <?php echo htmlspecialchars($task['due_date'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="title">
                                    <?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="memo">
                                    <?php echo htmlspecialchars($task['memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <form action="complete_task.php" method="post">
                                    <input type="hidden" name="id" value="<?php echo (int)$task['id']; ?>">
                                    <button type="submit" class="button btn-done">完了</button>
                                </form>

                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- Q4：やらない（重要でない×緊急でない） -->
                <section class="quadrant q4">
                    <h2>やらない（重要でない×緊急でない）</h2>

                    <?php if (empty($tasks_q4)): ?>
                        <p>タスクはありません</p>
                    <?php else: ?>
                        <?php foreach ($tasks_q4 as $task): ?>
                            <?php
                            $isOverdue = !empty($task['due_date']) && $task['due_date'] < $today;
                            ?>
                            <!-- JSが読み取るためのデータ -->
                            <div class="card<?php echo $isOverdue ? ' expired' : ''; ?>"
                                data-id="<?php echo (int)$task['id']; ?>"
                                data-title="<?php echo htmlspecialchars($task['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-memo="<?php echo htmlspecialchars($task['memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-due="<?php echo htmlspecialchars($task['due_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-imp="<?php echo (int)$task['is_important']; ?>"
                                data-urg="<?php echo (int)$task['is_urgent']; ?>">
                                <!-- ここは「表示専用」 -->
                                <div class="due">
                                    期日:
                                    <?php echo htmlspecialchars($task['due_date'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="title">
                                    <?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="memo">
                                    <?php echo htmlspecialchars($task['memo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <form action="complete_task.php" method="post">
                                    <input type="hidden" name="id" value="<?php echo (int)$task['id']; ?>">
                                    <button type="submit" class="button btn-done">完了</button>
                                </form>

                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- Q2, Q3, Q4 も同じ構造で、使う配列だけ変える -->
            </div>

            <!-- <div class="quadrant q1">
                <h2>すぐやる（重要×緊急）</h2>
                <div class="card expired">
                    <div class="due">期日: 11/20</div>バグ修正対応<div class="button">完了</div>
                </div>
                <div class="card">
                    <div class="due">期日: 11/25</div>会議資料作成<div class="button">完了</div>
                </div>
            </div> -->
            <!-- <div class="quadrant q2">
            <h2>計画してやる（重要×緊急でない）</h2>
            <div class="card">
                <div class="due">期日: 11/30</div>新機能設計メモ<div class="button">完了</div>
            </div>
        </div>
        <div class="quadrant q3">
            <h2>任せる（緊急×重要でない）</h2>
            <div class="card">
                <div class="due">期日: 11/28</div>データ入力依頼<div class="button">完了</div>
            </div>
        </div>
        <div class="quadrant q4">
            <h2>やらない（重要でない×緊急でない）</h2>
            <div class="card">
                <div class="due">期日: -</div>古いファイル整理<div class="button">完了</div>
            </div>
        </div> -->
        </div>

        <!-- =========================
 編集用モーダル（最初は非表示）
========================= -->
        <div id="editModal" class="modal-overlay" aria-hidden="true">

            <div class="modal">

                <!-- タイトル＋閉じる -->
                <div class="modal-header">
                    <h3>タスクを編集</h3>
                    <button type="button" id="modalClose">×</button>
                </div>

                <!-- 更新用フォーム -->
                <form action="update_task.php" method="post">

                    <!-- どのタスクかを識別するID -->
                    <input type="hidden" name="id" id="edit_id">

                    <label>タスク名</label>
                    <input type="text" name="title" id="edit_title">

                    <label>メモ（全文）</label>
                    <textarea name="memo" id="edit_memo"></textarea>

                    <label>重要度</label>
                    <input type="radio" name="is_important" value="1" id="edit_imp_1">重要
                    <input type="radio" name="is_important" value="0" id="edit_imp_0">重要でない

                    <label>緊急度</label>
                    <input type="radio" name="is_urgent" value="1" id="edit_urg_1">緊急
                    <input type="radio" name="is_urgent" value="0" id="edit_urg_0">緊急でない

                    <label>期日</label>
                    <input type="date" name="due_date" id="edit_due">

                    <button type="submit">更新</button>
                </form>
            </div>
        </div>

        <script>
            // ==============================
            // モーダルとフォーム部品を取得
            // ==============================
            const modal = document.getElementById('editModal');
            const closeBtn = document.getElementById('modalClose');

            const editId = document.getElementById('edit_id');
            const editTitle = document.getElementById('edit_title');
            const editMemo = document.getElementById('edit_memo');
            const editDue = document.getElementById('edit_due');

            const imp1 = document.getElementById('edit_imp_1');
            const imp0 = document.getElementById('edit_imp_0');
            const urg1 = document.getElementById('edit_urg_1');
            const urg0 = document.getElementById('edit_urg_0');

            // ==============================
            // モーダルを開く／閉じる
            // ==============================
            function openModal() {
                modal.classList.add('is-open');
            }

            function closeModal() {
                modal.classList.remove('is-open');
            }

            closeBtn.addEventListener('click', closeModal);

            // ==============================
            // 各タスクカードにクリックイベントを付ける
            // ==============================
            document.querySelectorAll('.card').forEach(card => {

                card.addEventListener('click', (e) => {

                    // 完了ボタンを押した時は編集しない
                    if (e.target.closest('form')) return;

                    // --------------------------
                    // card の data-* を取得
                    // --------------------------
                    editId.value = card.dataset.id;
                    editTitle.value = card.dataset.title;
                    editMemo.value = card.dataset.memo;
                    editDue.value = card.dataset.due;

                    // ラジオボタンの切り替え
                    (card.dataset.imp === '1' ? imp1 : imp0).checked = true;
                    (card.dataset.urg === '1' ? urg1 : urg0).checked = true;

                    // モーダルを開く
                    openModal();
                });
            });
        </script>

        <script>
            // ==============================
            // タスク登録アコーディオン（スマホ用）
            // ==============================

            // 外枠（is-open を付ける場所）
            const acc = document.querySelector('.task-accordion');

            // ヘッダー（押すボタン）
            const accBtn = document.querySelector('.task-accordion__header');

            // 記号（＋/−）
            const accIcon = document.querySelector('.task-accordion__icon');

            // 要素が存在する時だけ動かす（安全策）
            if (acc && accBtn && accIcon) {

                // 初期状態：閉じている想定（＋）
                // ※ エラー時にPHPで is-open を付けたら、ここで記号も合わせる
                if (acc.classList.contains('is-open')) {
                    accIcon.textContent = '−';
                } else {
                    accIcon.textContent = '＋';
                }

                // クリックで開閉
                accBtn.addEventListener('click', () => {

                    // is-open を反転
                    acc.classList.toggle('is-open');

                    // 記号も状態に合わせて切り替え
                    if (acc.classList.contains('is-open')) {
                        accIcon.textContent = '−';
                    } else {
                        accIcon.textContent = '＋';
                    }
                });
            }
        </script>

</body>

</html>