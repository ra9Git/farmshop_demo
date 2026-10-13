<?php

session_start();

// 未ログインならログイン画面へ戻す
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// 店舗管理者以外はアクセス拒否
$sessionRole = (string) ($_SESSION['role'] ?? '');

if ($sessionRole !== 'store_admin') {
    http_response_code(403);
    exit('このページを利用する権限がありません。');
}

// データベース接続設定
require_once __DIR__ . '/../farm-himitu-demo/config.php';

// ログイン中の管理者情報をDBから再確認する
$stmt = $pdo->prepare(
    'SELECT id, login_id, role, store_id, status
     FROM admin_users
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute([
    'id' => (int) $_SESSION['admin_id'],
]);

$admin = $stmt->fetch();

// アカウントが存在しない、または無効なら拒否する
if (
    !$admin ||
    $admin['status'] !== 'active' ||
    $admin['role'] !== 'store_admin' ||
    $admin['store_id'] === null
) {
    http_response_code(403);
    exit('このアカウントは店舗管理画面を利用できません。');
}

// DBに登録されている所属店舗IDを使う
$storeId = (int) $admin['store_id'];

// 所属店舗の情報を取得する
$stmt = $pdo->prepare(
    "SELECT id, store_code, name, status
     FROM stores
     WHERE id = :id
     LIMIT 1"
);

$stmt->execute(['id' => $storeId]);
$store = $stmt->fetch();

// 店舗が存在しない場合
if (!$store) {
    http_response_code(404);
    exit('所属店舗が見つかりません。');
}

// 無効な店舗は利用させない
if ($store['status'] !== 'active') {
    http_response_code(403);
    exit('この店舗は現在利用できません。システム管理者にお問い合わせください。');
}

?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>自店舗の管理</title>
</head>
<body>
    <h1>自店舗の管理</h1>

    <p>
        ようこそ、
        <?= htmlspecialchars($admin['login_id'], ENT_QUOTES, 'UTF-8') ?>
        さん
    </p>

    <h2>所属店舗情報</h2>

    <table border="1" cellpadding="6" cellspacing="0">
        <tbody>
            <tr>
                <th>店舗ID</th>
                <td>
                    <?= htmlspecialchars(
                        (string) $store['id'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>
            </tr>
            <tr>
                <th>店舗コード</th>
                <td>
                    <?= htmlspecialchars(
                        $store['store_code'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>
            </tr>
            <tr>
                <th>店舗名</th>
                <td>
                    <?= htmlspecialchars(
                        $store['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>
            </tr>
            <tr>
                <th>店舗状態</th>
                <td>
                    <?= $store['status'] === 'active' ? '有効' : '無効' ?>
                </td>
            </tr>
        </tbody>
    </table>

    <p>
        この画面には、現在ログインしているアカウントの所属店舗情報だけが表示されます。
    </p>

    <p>商品管理や予約・台帳機能は、今後この画面に追加していきます。</p>

    <hr>
    <p><a href="store_products.php">商品管理</a></p>
    <p><a href="index.php">管理画面トップへ戻る</a></p>
    <p><a href="logout.php">ログアウト</a></p>
</body>
</html>