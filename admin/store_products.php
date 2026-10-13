<?php
require_once __DIR__ . '/store_admin_auth.php';

// 商品編集用のCSRFトークンを作成
if (empty($_SESSION['product_edit_csrf_token'])) {
    $_SESSION['product_edit_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['product_edit_csrf_token'];
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta
        name="csrf-token"
        content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
    >

    <title>商品管理 | Farm Shop</title>
    <link rel="stylesheet" href="./common.css">
    <link
        rel="stylesheet"
        crossorigin
        href="./product-app/assets/index-BSFKF4Nh.css"
    >
</head>
<body>
    <div id="root"></div>

    <script
        type="module"
        crossorigin
        src="./product-app/assets/index-D1BnlGuF.js"
    ></script>
</body>
</html>