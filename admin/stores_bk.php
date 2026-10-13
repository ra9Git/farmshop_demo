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

// データベース接続設定を読み込む
require_once __DIR__ . '/../farm-himitu-demo/config.php';

$stores = [];
$errorMessage = '';

try {
    // 店舗一覧を取得する
    $stmt = $pdo->prepare(
        'SELECT id, store_code, name, status, created_at
         FROM stores
         ORDER BY id DESC'
    );

    $stmt->execute();
    $stores = $stmt->fetchAll();

} catch (PDOException $e) {
    // 詳細なエラーはサーバー側に記録する
    error_log('Store list retrieval failed: ' . $e->getMessage());

    http_response_code(500);
    $errorMessage = '店舗一覧を取得できませんでした。時間をおいて再度お試しください。';
}

?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>店舗管理</title>
</head>
<body>
    <h1>店舗管理</h1>

    <p><a href="index.php">管理画面トップへ戻る</a></p>

    <h2>店舗一覧</h2>
    <?php if (($_GET['created'] ?? '') === '1'): ?>
        <p>店舗を登録しました。</p>
    <?php endif; ?>
    <?php if (($_GET['updated'] ?? '') === '1'): ?>
        <p>店舗情報を更新しました。</p>
    <?php endif; ?>
    <p><a href="store_create.php">新しい店舗を登録</a></p>
    <?php if ($errorMessage !== ''): ?>

        <p role="alert">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </p>

    <?php else: ?>

        <p>登録店舗数：<?= count($stores) ?> 件</p>

        <?php if (count($stores) === 0): ?>

            <p>店舗はまだ登録されていません。</p>

        <?php else: ?>

            <table border="1" cellpadding="8" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>店舗コード</th>
                        <th>店舗名</th>
                        <th>状態</th>
                        <th>登録日時</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stores as $store): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars(
                                    (string) $store['id'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    (string) $store['store_code'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    (string) $store['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td>
                                <?php
                                $statusLabels = [
                                    'active' => '有効',
                                    'inactive' => '無効',
                                ];

                                $status = (string) $store['status'];
                                $statusLabel = $statusLabels[$status] ?? $status;
                                ?>
                                <?= htmlspecialchars(
                                    $statusLabel,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    (string) $store['created_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td>
                                <a href="store_edit.php?id=<?= (int) $store['id'] ?>">
                                    編集
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    <?php endif; ?>

    <p><a href="index.php">管理画面トップへ戻る</a></p>
    <p><a href="logout.php">ログアウト</a></p>
</body>
</html>