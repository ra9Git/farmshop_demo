<?php
header('Content-Type: application/json; charset=UTF-8');

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// DB接続
require_once __DIR__ . '/../../farm-himitu-demo/config.php';

// JSON形式でレスポンスを返す
function respond($statusCode, $data)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// POST以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, [
        'success' => false,
        'message' => '許可されていないリクエストです。',
    ]);
}

// ログイン確認
if (empty($_SESSION['admin_id'])) {
    respond(401, [
        'success' => false,
        'message' => 'ログインしてください。',
    ]);
}

// CSRFトークン確認
$sessionToken = $_SESSION['product_edit_csrf_token'] ?? '';
$requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !is_string($requestToken) ||
    $requestToken === '' ||
    !hash_equals($sessionToken, $requestToken)
) {
    respond(403, [
        'success' => false,
        'message' => 'セキュリティ確認に失敗しました。画面を再読み込みしてください。',
    ]);
}

// JSONデータを取得
$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);

if (!is_array($input)) {
    respond(400, [
        'success' => false,
        'message' => '送信データの形式が正しくありません。',
    ]);
}

// 入力値の確認
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
$name = trim((string) ($input['name'] ?? ''));
$price = filter_var($input['price'] ?? null, FILTER_VALIDATE_INT);
$stock = filter_var(
    $input['stock_quantity'] ?? null,
    FILTER_VALIDATE_INT
);
$published = $input['is_published'] ?? null;

if (!$id || $id < 1) {
    respond(400, [
        'success' => false,
        'message' => '商品IDが正しくありません。',
    ]);
}

if (
    $name === '' ||
    mb_strlen($name, 'UTF-8') > 200
) {
    respond(400, [
        'success' => false,
        'message' => '商品名は1〜200文字で入力してください。',
    ]);
}

if ($price === false || $price < 0) {
    respond(400, [
        'success' => false,
        'message' => '価格は0以上の整数で入力してください。',
    ]);
}

if ($stock === false || $stock < 0) {
    respond(400, [
        'success' => false,
        'message' => '在庫数は0以上の整数で入力してください。',
    ]);
}

if (!is_bool($published)) {
    respond(400, [
        'success' => false,
        'message' => '公開状態が正しくありません。',
    ]);
}

try {
    // ログイン中の管理者をDBで確認
    $stmt = $pdo->prepare(
        'SELECT id, role, store_id, status
         FROM admin_users
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => (int) $_SESSION['admin_id'],
    ]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$admin ||
        $admin['status'] !== 'active' ||
        $admin['role'] !== 'store_admin' ||
        empty($admin['store_id'])
    ) {
        respond(403, [
            'success' => false,
            'message' => '商品を編集する権限がありません。',
        ]);
    }

    $storeId = (int) $admin['store_id'];

    // 商品がログイン中の管理者の店舗に属しているか確認
    $stmt = $pdo->prepare(
        'SELECT id
         FROM products
         WHERE id = :id
           AND store_id = :store_id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $id,
        ':store_id' => $storeId,
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        respond(404, [
            'success' => false,
            'message' => '商品が見つからないか、編集する権限がありません。',
        ]);
    }

    // 自店舗の商品だけを更新
    $stmt = $pdo->prepare(
        'UPDATE products
         SET name = :name,
             price = :price,
             stock_quantity = :stock,
             is_published = :published,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id
           AND store_id = :store_id'
    );

    $stmt->execute([
        ':name' => $name,
        ':price' => $price,
        ':stock' => $stock,
        ':published' => $published ? 1 : 0,
        ':id' => $id,
        ':store_id' => $storeId,
    ]);

    respond(200, [
        'success' => true,
        'message' => '商品を更新しました。',
    ]);

} catch (PDOException $e) {
    error_log('product_update.php DB error: ' . $e->getMessage());

    respond(500, [
        'success' => false,
        'message' => 'DBの更新に失敗しました。',
    ]);
}