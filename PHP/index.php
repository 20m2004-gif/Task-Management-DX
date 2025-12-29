<?php
// エラーを表示する設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. データベース接続設定
$db_path = 'task.db'; 

try {
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // テーブルが無ければ作成（念の為）
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_log (
        log_date TEXT, employee TEXT, department TEXT, task_category TEXT, 
        task_name TEXT, minutes INTEGER, channel TEXT, priority TEXT, status TEXT, note TEXT
    )");

    // 社員マスタを取得
    $stmt_emp = $pdo->query("SELECT employee FROM employees");
    $employees_list = $stmt_emp->fetchAll(PDO::FETCH_COLUMN);

    // ★★★ 修正ポイント1：グループ名も一緒に取得し、行ごと(ASSOC)に取る ★★★
    $stmt_cat = $pdo->query("SELECT task_category, task_category_group FROM task_master ORDER BY task_category_group, task_category");
    $categories_list = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
    // ★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★

} catch (PDOException $e) {
    echo "接続エラー: " . $e->getMessage();
    if (strpos($e->getMessage(), 'no such table') !== false) {
        echo "<br><strong>ヒント:</strong> DBeaverで 'employees' や 'task_master' テーブルが正しくインポートされているか確認してください。";
    }
    exit();
}

// 2. フォームが送信された時の処理（登録処理）
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['log_date'];
    $emp = $_POST['employee'];
    $cat = $_POST['task_category'];
    $task = $_POST['task_name'];
    $min = $_POST['minutes'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("INSERT INTO task_log (log_date, employee, task_category, task_name, minutes, status, department, priority) VALUES (?, ?, ?, ?, ?, ?, 'Admin', 'Med')");
    
    if ($stmt->execute([$date, $emp, $cat, $task, $min, $status])) {
        $message = "✅ 登録しました！";
    } else {
        $message = "❌ エラーが発生しました";
    }
}

// 3. 履歴データの取得
$stmt = $pdo->query("SELECT * FROM task_log ORDER BY log_date DESC LIMIT 5");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>業務日報入力</title>
    <style>
        body { font-family: "Helvetica Neue", Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; background-color: #f9f9f9; }
        h1 { color: #333; font-size: 24px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; background: #fff; padding: 10px; border-radius: 5px; border: 1px solid #ddd; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input, select { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #007bff; color: white; padding: 12px 20px; border: none; cursor: pointer; width: 100%; font-size: 16px; font-weight: bold; border-radius: 4px; }
        button:hover { background-color: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .msg { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>

    <h1>業務日報入力フォーム</h1>

    <?php if ($message): ?>
        <p class="msg"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label>日付</label>
            <input type="date" name="log_date" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="form-group">
            <label>担当者名 <span style="color:red; font-size:12px;">(必須)</span></label>
            <select name="employee" required>
                <option value="" hidden>担当者を選択してください</option>
                <?php if (!empty($employees_list)): ?>
                    <?php foreach ($employees_list as $emp_name): ?>
                        <option value="<?= htmlspecialchars($emp_name) ?>"><?= htmlspecialchars($emp_name) ?></option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="">(社員データがありません)</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label>タスクカテゴリ <span style="color:red; font-size:12px;">(必須)</span></label>
            <select name="task_category" required>
                <option value="" hidden>カテゴリを選択してください</option>
                
                <!-- ★★★ 修正ポイント2：変数名を $row に統一 ★★★ -->
                <?php if (!empty($categories_list)): ?>
                    <?php foreach ($categories_list as $row): ?>
                        <option value="<?= htmlspecialchars($row['task_category']) ?>">
                            <?= htmlspecialchars($row['task_category_group']) ?> : <?= htmlspecialchars($row['task_category']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="">(カテゴリデータがありません)</option>
                <?php endif; ?>
                <!-- ★★★★★★★★★★★★★★★★★★★★★★★ -->
            </select>
        </div>

        <div class="form-group">
            <label>タスク内容 <span style="color:red; font-size:12px;">(必須)</span></label>
            <input type="text" name="task_name" placeholder="例：売上データの入力" required>
        </div>

        <div class="form-group">
            <label>作業時間（分） <span style="color:red; font-size:12px;">(必須)</span></label>
            <input type="number" name="minutes" placeholder="例：60" required>
        </div>

        <div class="form-group">
            <label>ステータス <span style="color:red; font-size:12px;">(必須)</span></label>
            <select name="status" required>
                <option value="" hidden>状態を選択してください</option>
                <option value="Done">完了 (Done)</option>
                <option value="InProgress">進行中 (InProgress)</option>
            </select>
        </div>

        <button type="submit">日報を登録する</button>
    </form>

    <hr>

    <h2>チーム全体の最新アクティビティ</h2>
    <table>
        <tr>
            <th>日付</th>
            <th>担当</th>
            <th>タスク</th>
            <th>時間</th>
            <th>状態</th>
        </tr>
        <?php foreach ($logs as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['log_date']) ?></td>
            <td><?= htmlspecialchars($row['employee']) ?></td>
            <td><?= htmlspecialchars($row['task_name']) ?></td>
            <td><?= htmlspecialchars($row['minutes']) ?>分</td>
            <td><?= htmlspecialchars($row['status']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

</body>
</html>