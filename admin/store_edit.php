<?php

session_start();

// 未ログインならログイン画面へ戻す
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// システム管理者以外はアクセス拒否
$role = (string) ($_SESSION['role'] ?? '');

if ($role !== 'system_admin') {
    http_response_code(403);
    exit('このページを利用する権限がありません。');
}

// URLの店舗IDを確認する
$storeId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($storeId === false || $storeId === null) {
    http_response_code(400);
    exit('店舗IDが正しくありません。');
}

// CSRFトークンを用意する
if (empty($_SESSION['store_edit_csrf_token'])) {
    $_SESSION['store_edit_csrf_token'] = bin2hex(random_bytes(32));
}

// データベース接続設定
require_once __DIR__ . '/../farm-himitu-demo/config.php';

$errorMessage = '';
$storeCode = '';
$storeName = '';
$storeStatus = 'active';

// 編集対象の店舗を取得する
$stmt = $pdo->prepare(
    'SELECT id, store_code, name, status
     FROM stores
     WHERE id = :id'
);

$stmt->execute(['id' => $storeId]);
$store = $stmt->fetch();

if (!$store) {
    http_response_code(404);
    exit('指定された店舗が見つかりません。');
}

// 現在の値をフォームの初期値にする
$storeCode = $store['store_code'];
$storeName = $store['name'];
$storeStatus = $store['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedStoreCode = $_POST['store_code'] ?? null;
    $postedStoreName = $_POST['name'] ?? null;
    $postedStoreStatus = $_POST['status'] ?? null;
    $csrfToken = $_POST['csrf_token'] ?? null;

    $sessionToken = $_SESSION['store_edit_csrf_token'] ?? '';

    // 入力値が文字列か確認する
    if (
        !is_string($postedStoreCode) ||
        !is_string($postedStoreName) ||
        !is_string($postedStoreStatus) ||
        !is_string($csrfToken)
    ) {
        $errorMessage = '入力内容を確認してください。';

    } elseif (
        $sessionToken === '' ||
        !hash_equals($sessionToken, $csrfToken)
    ) {
        $errorMessage = '画面の有効期限が切れました。ページを再読み込みしてください。';

    } else {
        $storeCode = trim($postedStoreCode);
        $storeName = trim($postedStoreName);
        $storeStatus = $postedStoreStatus;

        // 店舗コードの入力チェック
        if (
            $storeCode === '' ||
            !preg_match('/\A[A-Za-z0-9_-]{1,50}\z/', $storeCode)
        ) {
            $errorMessage = '店舗コードは半角英数字、ハイフン、アンダースコアを使い、50文字以内で入力してください。';

        // 店舗名の入力チェック
        } elseif ($storeName === '') {
            $errorMessage = '店舗名を入力してください。';

        } elseif (
            preg_match_all('/./us', $storeName, $matches) === false ||
            count($matches[0]) > 150
        ) {
            $errorMessage = '店舗名は150文字以内で入力してください。';

        // 状態の入力チェック
        } elseif (!in_array($storeStatus, ['active', 'inactive'], true)) {
            $errorMessage = '店舗の状態が正しくありません。';

        } else {
            try {
                $stmt = $pdo->prepare(
                    'UPDATE stores
                     SET store_code = :store_code,
                         name = :name,
                         status = :status
                     WHERE id = :id'
                );

                $stmt->execute([
                    'store_code' => $storeCode,
                    'name' => $storeName,
                    'status' => $storeStatus,
                    'id' => $storeId,
                ]);

                // 更新成功後は二重送信を避けるため一覧へ移動
                unset($_SESSION['store_edit_csrf_token']);

                header('Location: stores.php?updated=1');
                exit;

            } catch (PDOException $e) {
                // 店舗コードが重複している場合
                if (
                    isset($e->errorInfo[1]) &&
                    (int) $e->errorInfo[1] === 1062
                ) {
                    $errorMessage = 'この店舗コードは既に使用されています。別のコードを入力してください。';
                } else {
                    error_log('Store update failed: ' . $e->getMessage());
                    http_response_code(500);
                    $errorMessage = '店舗を更新できませんでした。時間をおいて再度お試しください。';
                }
            }
        }
    }
}

?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>店舗編集</title>
</head>
<body>
    <h1>店舗編集</h1>

    <p>
        店舗ID：
        <?= htmlspecialchars((string) $storeId, ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p><a href="stores.php">店舗一覧へ戻る</a></p>

    <?php if ($errorMessage !== ''): ?>
        <p role="alert">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="post" action="store_edit.php?id=<?= (int) $storeId ?>">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $_SESSION['store_edit_csrf_token'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <p>
            <label>
                店舗コード（半角英数字・ハイフン・アンダースコア）<br>
                <input
                    type="text"
                    name="store_code"
                    maxlength="50"
                    required
                    value="<?= htmlspecialchars(
                        $storeCode,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
            </label>
        </p>

        <p>
            <label>
                店舗名<br>
                <input
                    type="text"
                    name="name"
                    maxlength="150"
                    required
                    value="<?= htmlspecialchars(
                        $storeName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
            </label>
        </p>

        <p>
            <label>
                店舗の状態<br>
                <select name="status" required>
                    <option
                        value="active"
                        <?= $storeStatus === 'active' ? 'selected' : '' ?>
                    >
                        有効
                    </option>
                    <option
                        value="inactive"
                        <?= $storeStatus === 'inactive' ? 'selected' : '' ?>
                    >
                        無効
                    </option>
                </select>
            </label>
        </p>

        <button type="submit">変更を保存する</button>
    </form>
</body>
</html>