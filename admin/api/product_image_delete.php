<?php
header('Content-Type: application/json; charset=UTF-8');

function respond($statusCode, $data)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
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

$sessionToken = $_SESSION['product_edit_csrf_token'] ?? '';
$requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($sessionToken) || $sessionToken === '' ||
    !is_string($requestToken) || $requestToken === '' ||
    !hash_equals($sessionToken, $requestToken)) {
    respond(403, ['success' => false, 'message' => 'セキュリティ確認に失敗しました。画面を再読み込みしてください。']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, ['success' => false, 'message' => '送信データの形式が正しくありません。']);
}
$productId = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT);
$productImageId = filter_var($input['product_image_id'] ?? null, FILTER_VALIDATE_INT);
if (!$productId || $productId < 1 || !$productImageId || $productImageId < 1) {
    respond(400, ['success' => false, 'message' => '商品IDまたは画像IDが正しくありません。']);
}

try {
    require_once __DIR__ . '/../../farm-himitu-demo/config.php';
    if (empty($_SESSION['admin_id'])) {
        respond(401, ['success' => false, 'message' => 'ログインしてください。']);
    }

    $stmt = $pdo->prepare(
        'SELECT id, role, store_id, status FROM admin_users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => (int) $_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin || $admin['status'] !== 'active' || $admin['role'] !== 'store_admin' || $admin['store_id'] === null) {
        respond(403, ['success' => false, 'message' => '画像を管理する権限がありません。']);
    }

    $storeId = (int) $admin['store_id'];
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT id FROM products WHERE id = :id AND store_id = :store_id FOR UPDATE'
    );
    $stmt->execute([':id' => $productId, ':store_id' => $storeId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        respond(404, ['success' => false, 'message' => '商品が見つからないか、アクセス権限がありません。']);
    }

    $stmt = $pdo->prepare(
        'SELECT pi.id, pi.media_asset_id, ma.storage_key, ma.store_id AS asset_store_id
         FROM product_images pi
         INNER JOIN media_assets ma ON ma.id = pi.media_asset_id
         WHERE pi.id = :image_id AND pi.product_id = :product_id
         LIMIT 1'
    );
    $stmt->execute([':image_id' => $productImageId, ':product_id' => $productId]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$image || ($image['asset_store_id'] !== null && (int) $image['asset_store_id'] !== $storeId)) {
        $pdo->rollBack();
        respond(404, ['success' => false, 'message' => '画像が見つからないか、削除する権限がありません。']);
    }

    $stmt = $pdo->prepare('DELETE FROM product_images WHERE id = :id AND product_id = :product_id');
    $stmt->execute([':id' => $productImageId, ':product_id' => $productId]);

    // 既存順を一度ずらし、ユニーク制約に抵触せず1から詰め直す
    $stmt = $pdo->prepare('UPDATE product_images SET display_order = display_order + 20 WHERE product_id = :product_id');
    $stmt->execute([':product_id' => $productId]);

    $stmt = $pdo->prepare('SELECT id FROM product_images WHERE product_id = :product_id ORDER BY display_order ASC, id ASC');
    $stmt->execute([':product_id' => $productId]);
    $remainingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $updateOrder = $pdo->prepare('UPDATE product_images SET display_order = :display_order WHERE id = :id');
    foreach ($remainingIds as $index => $remainingId) {
        $updateOrder->execute([
            ':display_order' => $index + 1,
            ':id' => (int) $remainingId,
        ]);
    }

    // 別の商品でも参照されていない場合だけ、画像情報を削除する
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE media_asset_id = :media_asset_id');
    $stmt->execute([':media_asset_id' => (int) $image['media_asset_id']]);
    $stillUsed = (int) $stmt->fetchColumn() > 0;
    if (!$stillUsed) {
        $stmt = $pdo->prepare('DELETE FROM media_assets WHERE id = :id');
        $stmt->execute([':id' => (int) $image['media_asset_id']]);
    }

    $pdo->commit();

    if (!$stillUsed) {
        $key = ltrim((string) $image['storage_key'], '/');
        if (!str_contains($key, '..') && str_starts_with($key, 'uploads/product-images/')) {
            $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    respond(200, ['success' => true, 'message' => '画像を削除しました。']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('product_image_delete.php failed: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => '画像を削除できませんでした。']);
}
