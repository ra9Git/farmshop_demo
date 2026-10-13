<?php

header('Content-Type: application/json; charset=utf-8');

// GET以外のリクエストは受け付けない
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'この操作には対応していません。'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// ログイン時と同じセッション設定でセッションを開始する
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

try {
    // MySQL接続設定を読み込む
    require_once __DIR__ . '/../../farm-himitu-demo/config.php';

    // ログインしていなければ拒否する
    if (empty($_SESSION['admin_id'])) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'ログインしてください。'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // ログイン中の管理者情報をDBから再確認する
    $adminSql = '
        SELECT
            id,
            role,
            store_id,
            status
        FROM admin_users
        WHERE id = :admin_id
        LIMIT 1
    ';

    $adminStmt = $pdo->prepare($adminSql);
    $adminStmt->execute([
        'admin_id' => (int) $_SESSION['admin_id']
    ]);

    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

    // 管理者が存在しない、または無効になっている場合
    if (!$admin || $admin['status'] !== 'active') {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'ログイン状態を確認できません。再ログインしてください。'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // 店舗管理者以外は、この商品一覧APIを利用できない
    if (
        $admin['role'] !== 'store_admin'
        || $admin['store_id'] === null
    ) {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'この操作を行う権限がありません。'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // ログイン中の管理者に紐づく店舗IDを取得する
    $storeId = (int) $admin['store_id'];

    // 自分の店舗の商品だけを取得する
    $sql = '
        SELECT
            id,
            store_id,
            name,
            subtitle,
            description,
            price,
            stock_quantity,
            is_published,
            created_at,
            updated_at
        FROM products
        WHERE store_id = :store_id
        ORDER BY id ASC
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'store_id' => $storeId
    ]);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // JSONで扱いやすい型に変換する
    foreach ($products as &$product) {
        $product['id'] = (int) $product['id'];
        $product['store_id'] = (int) $product['store_id'];
        $product['price'] = (int) $product['price'];
        $product['stock_quantity'] = (int) $product['stock_quantity'];
        $product['is_published'] = (bool) $product['is_published'];
    }

    unset($product);

    echo json_encode([
        'success' => true,
        'products' => $products
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // 詳細なエラーはサーバーのログにだけ記録する
    error_log('Products API failed: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => '商品データを取得できませんでした。'
    ], JSON_UNESCAPED_UNICODE);
}