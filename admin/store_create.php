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

// CSRFトークンを用意する
if (empty($_SESSION['store_create_csrf_token'])) {
    $_SESSION['store_create_csrf_token'] = bin2hex(random_bytes(32));
}

// データベース接続設定
require_once __DIR__ . '/../farm-himitu-demo/config.php';

$storeCode = '';
$storeName = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeCode = $_POST['store_code'] ?? '';
    $storeName = $_POST['name'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    $sessionToken = $_SESSION['store_create_csrf_token'] ?? '';

    // 入力値が文字列か確認する
    if (
        !is_string($storeCode) ||
        !is_string($storeName) ||
        !is_string($csrfToken)
    ) {
        $errorMessage = '入力内容を確認してください。';

    } elseif (
        $sessionToken === '' ||
        !hash_equals($sessionToken, $csrfToken)
    ) {
        $errorMessage = '画面の有効期限が切れました。ページを再読み込みしてください。';

    } else {
        $storeCode = trim($storeCode);
        $storeName = trim($storeName);

        // 店舗コードは半角英数字・ハイフン・アンダースコアのみ
        if (
            $storeCode === '' ||
            !preg_match('/\A[A-Za-z0-9_-]{1,50}\z/', $storeCode)
        ) {
            $errorMessage = '店舗コードは半角英数字、ハイフン、アンダースコアを使い、50文字以内で入力してください。';

        } elseif ($storeName === '') {
            $errorMessage = '店舗名を入力してください。';
        } elseif (
            preg_match_all('/./us', $storeName, $matches) === false ||
            count($matches[0]) > 150
            ) {
            // VARCHAR(150) に日本語を最大150文字程度保存できるよう、
            // UTF-8の最大4バイト/文字を考慮して検証する
            $errorMessage = '店舗名を入力してください（150文字以内）。';

        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO stores (store_code, name)
                     VALUES (:store_code, :name)'
                );

                $stmt->execute([
                    'store_code' => $storeCode,
                    'name' => $storeName,
                ]);

                // 二重送信を避けるため、登録後は一覧へ移動
                unset($_SESSION['store_create_csrf_token']);

                header('Location: stores.php?created=1');
                exit;

            } catch (PDOException $e) {
                // 店舗コードの重複
                if (
                    isset($e->errorInfo[1]) &&
                    (int) $e->errorInfo[1] === 1062
                ) {
                    $errorMessage = 'この店舗コードは既に使用されています。別のコードを入力してください。';
                } else {
                    error_log('Store creation failed: ' . $e->getMessage());
                    http_response_code(500);
                    $errorMessage = '店舗を登録できませんでした。時間をおいて再度お試しください。';
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
    <title>店舗登録</title>
</head>
<body>
    <h1>店舗登録</h1>

    <p><a href="stores.php">店舗一覧へ戻る</a></p>

    <?php if ($errorMessage !== ''): ?>
        <p role="alert">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="post" action="store_create.php">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $_SESSION['store_create_csrf_token'],
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
                        is_string($storeCode) ? $storeCode : '',
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
                        is_string($storeName) ? $storeName : '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
            </label>
        </p>

        <button type="submit">店舗を登録する</button>
    </form>
</body>
</html>