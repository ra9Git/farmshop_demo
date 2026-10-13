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
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>店舗管理 | Farm Shop</title>

    <link rel="stylesheet" href="./common.css">
    <script type="module" crossorigin src="./store-app/assets/stores-DrbSbld0.js"></script>
    <link rel="stylesheet" crossorigin href="./store-app/assets/stores-CGObJrZe.css">
</head>
<body>
    <div id="stores-root"></div>

    <?php if (($_GET['created'] ?? '') === '1'): ?>
        <noscript>店舗を登録しました。</noscript>
    <?php endif; ?>
</body>
</html>