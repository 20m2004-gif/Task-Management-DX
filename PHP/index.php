<?php
// ▼▼▼ 設定エリア ▼▼▼

// エラーを画面に表示する設定（開発中はONにしておくとミスに気づきやすいです）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// データベースファイルの場所（同じフォルダにある task.db を使います）
$db_path = 'task.db'; 

try {
    // 1. データベースに接続（PDOという仕組みを使います）
    $pdo = new PDO('sqlite:' . $db_path);
    // エラーが起きたら静かに無視せず、ちゃんと警告を出す設定
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. データを入れる「箱（テーブル）」を用意
    // task_log テーブルが無ければ新しく作ります
    // ※全ての項目（部署、経路、優先度、備考）を含めて定義しています
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_log (
        log_date TEXT,      -- 日付
        employee TEXT,      -- 担当者名
        department TEXT,    -- 部署
        task_category TEXT, -- タスクカテゴリ
        task_name TEXT,     -- タスク詳細
        minutes INTEGER,    -- 作業時間(分)
        channel TEXT,       -- 経路
        priority TEXT,      -- 優先度
        status TEXT,        -- 状態
        note TEXT           -- 備考
    )");

    // 3. 入力フォームの「プルダウン」を作るための準備
    
    // (A) 社員リストをデータベースから取得
    $stmt_emp = $pdo->query("SELECT employee FROM employees");
    $employees_list = $stmt_emp->fetchAll(PDO::FETCH_COLUMN);

    // (B) カテゴリリストをデータベースから取得
    // グループ名とカテゴリ名の両方を取得します
    $stmt_cat = $pdo->query("SELECT task_category, task_category_group FROM task_master ORDER BY task_category_group, task_category");
    $categories_list = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // 接続に失敗した場合の処理
    echo "接続エラー: " . $e->getMessage();
    exit();
}

// ▼▼▼ 登録処理エリア（送信ボタンが押された時だけ動く） ▼▼▼

$message = ""; // 画面に出すメッセージ

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // フォームに入力された「全て」の値を変数に入れます
    $date = $_POST['log_date'];
    $emp = $_POST['employee'];
    $dept = $_POST['department'];   // 追加：部署
    $cat = $_POST['task_category'];
    $task = $_POST['task_name'];
    $min = $_POST['minutes'];
    $channel = $_POST['channel'];   // 追加：経路
    $priority = $_POST['priority']; // 追加：優先度
    $status = $_POST['status'];
    $note = $_POST['note'];         // 追加：備考

    // 4. データベースに保存
    // 以前のコードでは固定値（Adminなど）を入れていましたが、今回は入力値をそのまま使います
    // 「?」が10個あります（項目の数と同じ）
    $sql = "INSERT INTO task_log (
                log_date, employee, department, task_category, task_name, 
                minutes, channel, priority, status, note
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    // execute: ? の順番通りに変数を入れて実行！
    if ($stmt->execute([$date, $emp, $dept, $cat, $task, $min, $channel, $priority, $status, $note])) {
        $message = "✅ 登録しました！";
    } else {
        $message = "❌ エラーが発生しました";
    }
}

// ▼▼▼ 表示エリア（履歴データの取得） ▼▼▼

// 5. 最新の履歴を5件だけ取得
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
        body { font-family: "Helvetica Neue", Arial, sans-serif; max-width: 700px; margin: 20px auto; padding: 20px; background-color: #f9f9f9; }
        h1 { color: #333; font-size: 24px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #ddd; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input, select { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        
        /* 2列に並べるための設定（横並び） */
        .row { display: flex; gap: 15px; }
        .col { flex: 1; }

        button { background-color: #007bff; color: white; padding: 12px 20px; border: none; cursor: pointer; width: 100%; font-size: 16px; font-weight: bold; border-radius: 4px; margin-top: 10px; }
        button:hover { background-color: #0056b3; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; font-size: 14px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .msg { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>

    <h1>業務日報入力フォーム</h1>

    <!-- メッセージがある時だけ表示 -->
    <?php if ($message): ?>
        <p class="msg"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <!-- 入力フォーム開始 -->
    <form method="post">
        
        <!-- 日付 -->
        <div class="form-group">
            <label>日付</label>
            <input type="date" name="log_date" value="<?= date('Y-m-d') ?>" required>
        </div>

        <!-- 担当者と部署（横並び） -->
        <div class="form-group row">
            <div class="col">
                <label>担当者 <span style="color:red; font-size:12px;">(必須)</span></label>
                <select name="employee" required>
                    <option value="" hidden>選択してください</option>
                    <!-- DBから取得した社員リスト -->
                    <?php if (!empty($employees_list)): ?>
                        <?php foreach ($employees_list as $emp_name): ?>
                            <option value="<?= htmlspecialchars($emp_name) ?>"><?= htmlspecialchars($emp_name) ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">(データなし)</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col">
                <label>部署 <span style="color:red; font-size:12px;">(必須)</span></label>
                <select name="department" required>
                    <option value="" hidden>選択してください</option>
                    <option value="Admin">Admin (管理部)</option>
                    <option value="IT-Support">IT-Support</option>
                </select>
            </div>
        </div>

        <!-- カテゴリとチャネル（横並び） -->
        <div class="form-group row">
            <div class="col">
                <label>タスクカテゴリ <span style="color:red; font-size:12px;">(必須)</span></label>
                <select name="task_category" required>
                    <option value="" hidden>選択してください</option>
                    <!-- DBから取得したカテゴリリスト -->
                    <?php if (!empty($categories_list)): ?>
                        <?php foreach ($categories_list as $row): ?>
                            <option value="<?= htmlspecialchars($row['task_category']) ?>">
                                <?= htmlspecialchars($row['task_category_group']) ?> : <?= htmlspecialchars($row['task_category']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">(データなし)</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col">
                <label>経路 (Channel) <span style="color:red; font-size:12px;">(必須)</span></label>
                <select name="channel" required>
                    <option value="" hidden>選択してください</option>
                    <option value="Email">Email</option>
                    <option value="Chat">Chat</option>
                    <option value="Onsite">Onsite (対面)</option>
                    <option value="Online">Online (Web会議)</option>
                </select>
            </div>
        </div>

        <!-- タスク内容 -->
        <div class="form-group">
            <label>タスク内容 <span style="color:red; font-size:12px;">(必須)</span></label>
            <input type="text" name="task_name" placeholder="例：売上データの入力" required>
        </div>

        <!-- 作業時間と優先度（横並び） -->
        <div class="form-group row">
            <div class="col">
                <label>作業時間(分) <span style="color:red; font-size:12px;">(必須)</span></label>
                <input type="number" name="minutes" placeholder="例: 60" required>
            </div>
            <div class="col">
                <label>優先度 (Priority) <span style="color:red; font-size:12px;">(必須)</span></label>
                <select name="priority" required>
                    <option value="" hidden>選択してください</option>
                    <option value="High">High (高)</option>
                    <option value="Med">Med (中)</option>
                    <option value="Low">Low (低)</option>
                </select>
            </div>
        </div>

        <!-- ステータス -->
        <div class="form-group">
            <label>ステータス <span style="color:red; font-size:12px;">(必須)</span></label>
            <select name="status" required>
                <option value="" hidden>選択してください</option>
                <option value="Done">完了 (Done)</option>
                <option value="InProgress">進行中 (InProgress)</option>
            </select>
        </div>

        <!-- 備考（任意入力） -->
        <div class="form-group">
            <label>備考 (Note)</label>
            <input type="text" name="note" placeholder="補足事項があれば入力">
        </div>

        <button type="submit">日報を登録する</button>
    </form>

    <hr>

    <h2>チーム全体の最新アクティビティ</h2>
    <!-- 全ての項目を表にして表示 -->
    <table>
        <tr>
            <th>日付</th>
            <th>担当</th>
            <th>部署</th>
            <th>カテゴリ</th>
            <th>タスク</th>
            <th>時間</th>
            <th>状態</th>
        </tr>
        <?php foreach ($logs as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['log_date']) ?></td>
            <td><?= htmlspecialchars($row['employee']) ?></td>
            <td><?= htmlspecialchars($row['department'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['task_category']) ?></td>
            <td><?= htmlspecialchars($row['task_name']) ?></td>
            <td><?= htmlspecialchars($row['minutes']) ?>分</td>
            <td><?= htmlspecialchars($row['status']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

</body>
</html>