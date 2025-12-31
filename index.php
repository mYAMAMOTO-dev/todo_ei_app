<!-- 4象限に表示（Read） -->

<?php
session_start();

$form_errors = $_SESSION['form_errors'] ?? [];
$form_inputs = $_SESSION['form_inputs'] ?? [];

unset($_SESSION['form_errors'], $_SESSION['form_inputs']);


// DB接続
$dsn  = 'mysql:host=127.0.0.1;port=8889;dbname=eisenhower;charset=utf8mb4';
$user = 'root';
$pass = 'root';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);


// 今日の日付（期限切れ判定用）
$today = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');


/**
 * 象限ごとにタスクを取得する共通関数
 */
function fetchTasksByQuadrant(PDO $pdo, string $today, int $isImportant, int $isUrgent): array
{
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

        ＾
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
    </style>
</head>

<body>
    <h1>アイゼンハワーマトリクス　レスポンシブル</h1>
    <div class="wrapper">

        <!-- 登録フォーム -->
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

        <div class="matrix">

            <!-- Q1：すぐやる（重要×緊急） -->
            <section class="quadrant q1">
                <h2>すぐやる（重要 × 緊急）</h2>

                <?php if (empty($tasks_q1)): ?>ƒ
                <p>タスクはありません</p>
            <?php else: ?>
                <?php foreach ($tasks_q1 as $task): ?>
                    <?php
                        $isOverdue = !empty($task['due_date']) && $task['due_date'] < $today;
                    ?>
                    <div class="card<?php echo $isOverdue ? ' expired' : ''; ?>">
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
                        <div class="card<?php echo $isOverdue ? ' expired' : ''; ?>">
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
                        <div class="card<?php echo $isOverdue ? ' expired' : ''; ?>">
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
                        <div class="card<?php echo $isOverdue ? ' expired' : ''; ?>">
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
</body>

</html>