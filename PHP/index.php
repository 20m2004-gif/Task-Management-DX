<?php
// ▼▼▼ 設定エリア ▼▼▼

// エラーを画面に表示する設定（開発中はONにしておくとミスに気づきやすいです）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// データベースファイルの場所（同じフォルダにある task.db を使います）
$db_path = 'task.db'; 

try {
    // 1. データベースに接続（PDO(PHP Data Objects）という仕組みを使います）
    $pdo = new PDO('sqlite:' . $db_path);
    // エラーが起きたら静かに無視せず、ちゃんと警告を出す設定
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. データを入れる「箱（テーブル）」を用意
    // task_log テーブルが無ければ新しく作ります（IF NOT EXISTS）
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_log (
        log_date TEXT,      -- 日付
        employee TEXT,      -- 担当者名
        department TEXT,    -- 部署
        task_category TEXT, -- タスクカテゴリ
        task_name TEXT,     -- タスク詳細
        minutes INTEGER,    -- 作業時間(分)
        channel TEXT,       -- 経路
        priority TEXT,      -- 優先度
        status TEXT,        -- ステータス（状態）
        note TEXT           -- 備考
    )");

    // 3. 入力フォームの「プルダウン」を作るための準備
    
    // (A) 社員リストをデータベースから取得
    // employeesテーブルから名前だけを抜き出します
    $stmt_emp = $pdo->query("SELECT employee FROM employees");
    // FETCH_COLUMN: 「列」として単純なリスト形式で受け取る
    $employees_list = $stmt_emp->fetchAll(PDO::FETCH_COLUMN);

    // (B) カテゴリリストをデータベースから取得
    // task_masterテーブルから「ID」と「グループ名」の両方を取得します
    // ORDER BY: グループごとに並び替えて見やすくしています
    $stmt_cat = $pdo->query("SELECT task_category, task_category_group FROM task_master ORDER BY task_category_group, task_category");
    // FETCH_ASSOC: 「項目名」と「値」のセット（連想配列）として受け取る
    $categories_list = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // 接続に失敗した場合の処理
    echo "接続エラー: " . $e->getMessage();
    // よくあるエラー（テーブルが見つからない）へのヒントを表示
    if (strpos($e->getMessage(), 'no such table') !== false) {
        echo "<br><strong>ヒント:</strong> DBeaverでCSVが正しくインポートされているか確認してください。";
    }
    exit(); // ここで処理を強制終了
}

// ▼▼▼ 登録処理エリア（送信ボタンが押された時だけ動く） ▼▼▼

$message = ""; // 画面に出すメッセージ（「登録しました」など）

// 「POST」という方法でデータが送られてきたかチェック
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // フォームに入力された値を変数に入れます
    $date = $_POST['log_date'];
    $emp = $_POST['employee'];
    $cat = $_POST['task_category'];
    $task = $_POST['task_name'];
    $min = $_POST['minutes'];
    $status = $_POST['status'];

    // 4. データベースに保存（SQLインジェクション対策）
    // prepare: データの「ひな形」を作ります（?の部分があとで埋まります）
    // departmentは'Admin', priorityは'Med'で今回は固定しています
    $stmt = $pdo->prepare("INSERT INTO task_log (log_date, employee, task_category, task_name, minutes, status, department, priority) VALUES (?, ?, ?, ?, ?, ?, 'Admin', 'Med')");
    
    // execute: ? の部分に実際の値を入れて実行！
    if ($stmt->execute([$date, $emp, $cat, $task, $min, $status])) {
        $message = "✅ 登録しました！";
    } else {
        $message = "❌ エラーが発生しました";
    }
}

// ▼▼▼ 表示エリア（履歴データの取得） ▼▼▼

// 5. 最新の履歴を5件だけ取得
// ORDER BY log_date DESC: 新しい日付順に並べる
// LIMIT 5: 5件だけにする
$stmt = $pdo->query("SELECT * FROM task_log ORDER BY log_date DESC LIMIT 5");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!-- ▼▼▼ ここからHTML（画面の見た目） ▼▼▼ -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>業務日報入力</title>
    <style>
        /* 簡単なデザイン設定（CSS） */
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

    <!-- メッセージがある時だけ表示する -->
    <?php if ($message): ?>
        <!-- htmlspecialchars: セキュリティ対策（変な記号を文字に変換） -->
        <p class="msg"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <!-- 入力フォーム開始 -->
    <form method="post">
        <div class="form-group">
            <label>日付</label>
            <!-- value: 今日の日付を初期値としてセット -->
            <input type="date" name="log_date" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="form-group">
            <label>担当者名 <span style="color:red; font-size:12px;">(必須)</span></label>
            <select name="employee" required>
                <!-- 初期値は見出しにして、選択できないようにする -->
                <option value="" hidden>担当者を選択してください</option>
                
                <!-- データベースから取ってきた社員リストをループして表示 -->
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
                
                <!-- カテゴリリストをループして表示 -->
                <?php if (!empty($categories_list)): ?>
                    <?php foreach ($categories_list as $row): ?>
                        <!-- 画面には「BackOffice : DataEntry」のように見やすく表示 -->
                        <option value="<?= htmlspecialchars($row['task_category']) ?>">
                            <?= htmlspecialchars($row['task_category_group']) ?> : <?= htmlspecialchars($row['task_category']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="">(カテゴリデータがありません)</option>
                <?php endif; ?>
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
        <!-- 履歴データをループして表にする -->
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