<?php
header('Content-Type: application/json; charset=UTF-8');

function respond($statusCode, $data)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    respond(405, ['success' => false, 'message' => '許可されていないリクエストです。']);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

try {
    require_once __DIR__ . '/../../farm-himitu-demo/config.php';

    if (empty($_SESSION['admin_id'])) {
        respond(401, ['success' => false, 'message' => 'ログインしてください。']);
    }

    $productId = filter_var($_GET['product_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$productId || $productId < 1) {
        respond(400, ['success' => false, 'message' => '商品IDが正しくありません。']);
    }

    $stmt = $pdo->prepare(
        'SELECT id, role, store_id, status FROM admin_users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => (int) $_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || $admin['status'] !== 'active' || $admin['role'] !== 'store_admin' || $admin['store_id'] === null) {
        respond(403, ['success' => false, 'message' => '画像を管理する権限がありません。']);
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM products WHERE id = :id AND store_id = :store_id LIMIT 1'
    );
    $stmt->execute([':id' => $productId, ':store_id' => (int) $admin['store_id']]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        respond(404, ['success' => false, 'message' => '商品が見つからないか、アクセス権限がありません。']);
    }

    $stmt = $pdo->prepare(
        'SELECT pi.id, pi.display_order, ma.id AS media_asset_id,
                ma.storage_key, ma.original_filename, ma.mime_type, ma.alt_text
         FROM product_images pi
         INNER JOIN media_assets ma ON ma.id = pi.media_asset_id
         WHERE pi.product_id = :product_id
         ORDER BY pi.display_order ASC, pi.id ASC'
    );
    $stmt->execute([':product_id' => $productId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $images = [];
    foreach ($rows as $row) {
        $key = ltrim((string) $row['storage_key'], '/');
        // storage_key は管理側で生成した相対パスのみを想定
        if (str_contains($key, '..') || str_starts_with($key, 'http:') || str_starts_with($key, 'https:')) {
            continue;
        }
        $row['id'] = (int) $row['id'];
        $row['display_order'] = (int) $row['display_order'];
        $row['media_asset_id'] = (int) $row['media_asset_id'];
        $row['url'] = './' . $key;
        $images[] = $row;
    }

    respond(200, ['success' => true, 'images' => $images]);
} catch (Throwable $e) {
    error_log('product_images.php failed: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => '商品画像を取得できませんでした。']);
}
