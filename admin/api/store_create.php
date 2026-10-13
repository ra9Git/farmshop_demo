<?php

session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

// JSON形式でレスポンスを返す
function respondJson(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ログイン確認
if (!isset($_SESSION['admin_id'])) {
    respondJson(401, [
        'success' => false,
        'message' => 'ログインが必要です。',
    ]);
}

// システム管理者のみ許可
$role = (string) ($_SESSION['role'] ?? '');

if ($role !== 'system_admin') {
    respondJson(403, [
        'success' => false,
        'message' => 'この操作を行う権限がありません。',
    ]);
}

// 登録フォーム用のCSRFトークンを用意する
if (empty($_SESSION['store_create_csrf_token'])) {
    $_SESSION['store_create_csrf_token'] = bin2hex(random_bytes(32));
}

// GET：フォームで使うCSRFトークンを返す
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respondJson(200, [
        'success' => true,
        'csrf_token' => $_SESSION['store_create_csrf_token'],
    ]);
}

// POST以外は受け付けない
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');

    respondJson(405, [
        'success' => false,
        'message' => '許可されていないリクエストです。',
    ]);
}

// Reactから送られたJSONを読み込む
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!is_array($data)) {
    respondJson(400, [
        'success' => false,
        'message' => '送信データを読み取れませんでした。',
    ]);
}

$storeCode = $data['store_code'] ?? null;
$storeName = $data['name'] ?? null;
$csrfToken = $data['csrf_token'] ?? null;

// 入力値が文字列か確認する
if (
    !is_string($storeCode) ||
    !is_string($storeName) ||
    !is_string($csrfToken)
) {
    respondJson(400, [
        'success' => false,
        'message' => '入力内容を確認してください。',
    ]);
}

// CSRFトークンを検証する
$sessionToken = $_SESSION['store_create_csrf_token'] ?? '';

if (
    $sessionToken === '' ||
    !hash_equals($sessionToken, $csrfToken)
) {
    respondJson(403, [
        'success' => false,
        'message' => '画面の有効期限が切れました。フォームを開き直してください。',
    ]);
}

$storeCode = trim($storeCode);
$storeName = trim($storeName);

// 店舗コード：半角英数字、ハイフン、アンダースコア
if (
    $storeCode === '' ||
    !preg_match('/\A[A-Za-z0-9_-]{1,50}\z/', $storeCode)
) {
    respondJson(422, [
        'success' => false,
        'message' => '店舗コードは半角英数字、ハイフン、アンダースコアを使い、50文字以内で入力してください。',
    ]);
}

// 店舗名：必須
if ($storeName === '') {
    respondJson(422, [
        'success' => false,
        'message' => '店舗名を入力してください。',
    ]);
}

// 店舗名：最大150文字
if (
    preg_match_all('/./us', $storeName, $matches) === false ||
    count($matches[0]) > 150
) {
    respondJson(422, [
        'success' => false,
        'message' => '店舗名は150文字以内で入力してください。',
    ]);
}

// データベース接続設定
require_once __DIR__ . '/../../farm-himitu-demo/config.php';

try {
    $stmt = $pdo->prepare(
        'INSERT INTO stores (store_code, name)
         VALUES (:store_code, :name)'
    );

    $stmt->execute([
        'store_code' => $storeCode,
        'name' => $storeName,
    ]);

    // 使用済みトークンを破棄する
    // 次にフォームを開くと新しいトークンが作られる
    unset($_SESSION['store_create_csrf_token']);

    respondJson(201, [
        'success' => true,
        'message' => '店舗を登録しました。',
        'store_id' => $pdo->lastInsertId(),
    ]);

} catch (PDOException $e) {
    // 店舗コードの重複
    if (
        isset($e->errorInfo[1]) &&
        (int) $e->errorInfo[1] === 1062
    ) {
        respondJson(409, [
            'success' => false,
            'message' => 'この店舗コードは既に使用されています。別のコードを入力してください。',
        ]);
    }

    error_log('Store creation API failed: ' . $e->getMessage());

    respondJson(500, [
        'success' => false,
        'message' => '店舗を登録できませんでした。時間をおいて再度お試しください。',
    ]);
}