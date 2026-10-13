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

$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
if (!$productId || $productId < 1) {
    respond(400, ['success' => false, 'message' => '商品IDが正しくありません。']);
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    respond(400, ['success' => false, 'message' => 'アップロードする画像を選択してください。']);
}
$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respond(400, ['success' => false, 'message' => '画像のアップロードに失敗しました。ファイル容量も確認してください。']);
}
if (!is_uploaded_file($file['tmp_name'])) {
    respond(400, ['success' => false, 'message' => 'アップロードされたファイルを確認できません。']);
}
if ((int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
    respond(400, ['success' => false, 'message' => '画像は1ファイル5MB以下にしてください。']);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
if (!isset($allowedTypes[$mimeType])) {
    respond(400, ['success' => false, 'message' => '画像はJPEGまたはPNG形式にしてください。']);
}
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false || $imageInfo[0] < 1 || $imageInfo[1] < 1 ||
    $imageInfo[0] > 8000 || $imageInfo[1] > 8000) {
    respond(400, ['success' => false, 'message' => '画像のサイズが不正、または大きすぎます（最大8000×8000px）。']);
}

$relativeDirectory = 'uploads/product-images';
$uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'product-images';
if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
    respond(500, ['success' => false, 'message' => '画像保存フォルダを作成できません。']);
}
if (!is_writable($uploadDirectory)) {
    respond(500, ['success' => false, 'message' => '画像保存フォルダに書き込み権限がありません。']);
}

$generatedName = bin2hex(random_bytes(20)) . '.' . $allowedTypes[$mimeType];
$absolutePath = $uploadDirectory . DIRECTORY_SEPARATOR . $generatedName;
$storageKey = $relativeDirectory . '/' . $generatedName;
if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
    respond(500, ['success' => false, 'message' => '画像ファイルを保存できませんでした。']);
}
$debugStage = '処理開始';
try {
    require_once __DIR__ . '/../../farm-himitu-demo/config.php';
$debugStage = '管理者確認';
    if (empty($_SESSION['admin_id'])) {
        @unlink($absolutePath);
        respond(401, ['success' => false, 'message' => 'ログインしてください。']);
    }

    $stmt = $pdo->prepare(
        'SELECT id, role, store_id, status FROM admin_users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => (int) $_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || $admin['status'] !== 'active' || $admin['role'] !== 'store_admin' || $admin['store_id'] === null) {
        @unlink($absolutePath);
        respond(403, ['success' => false, 'message' => '画像を管理する権限がありません。']);
    }
$debugStage = '店舗確認';
    $storeId = (int) $admin['store_id'];
    $pdo->beginTransaction();
$debugStage = '商品確認';
    // 商品行をロックし、同時アップロードによる7枚超過や表示順重複を防ぐ
    $stmt = $pdo->prepare(
        'SELECT id FROM products WHERE id = :id AND store_id = :store_id FOR UPDATE'
    );
    $stmt->execute([':id' => $productId, ':store_id' => $storeId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        @unlink($absolutePath);
        respond(404, ['success' => false, 'message' => '商品が見つからないか、アクセス権限がありません。']);
    }
$debugStage = '画像枚数確認';
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = :product_id');
    $stmt->execute([':product_id' => $productId]);
    $imageCount = (int) $stmt->fetchColumn();
    if ($imageCount >= 7) {
        $pdo->rollBack();
        @unlink($absolutePath);
        respond(400, ['success' => false, 'message' => '商品画像は最大7枚までです。']);
    }

    $displayOrder = $imageCount + 1;
    $originalName = basename(str_replace('\\', '/', (string) ($file['name'] ?? '')));
    $originalName = mb_substr($originalName, 0, 255, 'UTF-8');

    $stmt = $pdo->prepare(
        'INSERT INTO media_assets
            (store_id, storage_key, original_filename, mime_type, file_size_bytes, alt_text, uploaded_by_user_id)
         VALUES
            (:store_id, :storage_key, :original_filename, :mime_type, :file_size_bytes, :alt_text, :uploaded_by_user_id)'
    );

    $stmt->execute([
        ':store_id' => $storeId,
        ':storage_key' => $storageKey,
        ':original_filename' => $originalName !== '' ? $originalName : null,
        ':mime_type' => $mimeType,
        ':file_size_bytes' => (int) $file['size'],
        ':alt_text' => null,
        ':uploaded_by_user_id' => null,
    ]);

    $mediaAssetId = (int) $pdo->lastInsertId();
$debugStage = '商品画像関連付け';
    $stmt = $pdo->prepare(
        'INSERT INTO product_images (product_id, media_asset_id, display_order)
         VALUES (:product_id, :media_asset_id, :display_order)'
    );
    $stmt->execute([
        ':product_id' => $productId,
        ':media_asset_id' => $mediaAssetId,
        ':display_order' => $displayOrder,
    ]);
$debugStage = 'DB確定';
    $pdo->commit();
    respond(201, ['success' => true, 'message' => '画像を登録しました。']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    @unlink($absolutePath);
    error_log('product_image_upload.php failed: ' . $e->getMessage());
   // respond(500, ['success' => false, 'message' => '画像を登録できませんでした。']);
   $driverCode = '';

if ($e instanceof PDOException && isset($e->errorInfo[1])) {
    $driverCode = (string) $e->errorInfo[1];
}

respond(500, [
    'success' => false,
    'message' => '画像を登録できませんでした。',
    'debug_stage' => $debugStage ?? '不明',
    'debug_error_type' => get_class($e),
    'debug_error_code' => (string) $e->getCode(),
    'debug_driver_code' => $driverCode,
]);
}
