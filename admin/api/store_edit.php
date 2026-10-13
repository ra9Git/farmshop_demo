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

// GET：編集対象の店舗情報とCSRFトークンを返す
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $storeId = filter_var(
        $_GET['id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($storeId === false || $storeId === null) {
        respondJson(400, [
            'success' => false,
            'message' => '店舗IDが正しくありません。',
        ]);
    }

    if (empty($_SESSION['store_edit_csrf_token'])) {
        $_SESSION['store_edit_csrf_token'] = bin2hex(random_bytes(32));
    }

    require_once __DIR__ . '/../../farm-himitu-demo/config.php';

    try {
        $stmt = $pdo->prepare(
            'SELECT id, store_code, name, status
             FROM stores
             WHERE id = :id'
        );

        $stmt->execute(['id' => $storeId]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$store) {
            respondJson(404, [
                'success' => false,
                'message' => '指定された店舗が見つかりません。',
            ]);
        }
        
        respondJson(200, [
            'success' => true,
            'store' => $store,
            'csrf_token' => $_SESSION['store_edit_csrf_token'],
        ]);

    } catch (PDOException $e) {
        error_log('Store retrieval for editing failed: ' . $e->getMessage());

        respondJson(500, [
            'success' => false,
            'message' => '店舗情報を取得できませんでした。',
        ]);
    }
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

// 店舗IDの確認
$storeId = filter_var(
    $data['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($storeId === false || $storeId === null) {
    respondJson(400, [
        'success' => false,
        'message' => '店舗IDが正しくありません。',
    ]);
}

$storeCode = $data['store_code'] ?? null;
$storeName = $data['name'] ?? null;
$storeStatus = $data['status'] ?? null;
$csrfToken = $data['csrf_token'] ?? null;

// 入力値が文字列か確認する
if (
    !is_string($storeCode) ||
    !is_string($storeName) ||
    !is_string($storeStatus) ||
    !is_string($csrfToken)
) {
    respondJson(400, [
        'success' => false,
        'message' => '入力内容を確認してください。',
    ]);
}

// CSRFトークンの検証
$sessionToken = $_SESSION['store_edit_csrf_token'] ?? '';

if (
    $sessionToken === '' ||
    !hash_equals($sessionToken, $csrfToken)
) {
    respondJson(403, [
        'success' => false,
        'message' => '画面の有効期限が切れました。編集フォームを開き直してください。',
    ]);
}

$storeCode = trim($storeCode);
$storeName = trim($storeName);

// 店舗コードの入力チェック
if (
    $storeCode === '' ||
    !preg_match('/\A[A-Za-z0-9_-]{1,50}\z/', $storeCode)
) {
    respondJson(422, [
        'success' => false,
        'message' => '店舗コードは半角英数字、ハイフン、アンダースコアを使い、50文字以内で入力してください。',
    ]);
}

// 店舗名の入力チェック
if ($storeName === '') {
    respondJson(422, [
        'success' => false,
        'message' => '店舗名を入力してください。',
    ]);
}

if (
    preg_match_all('/./us', $storeName, $matches) === false ||
    count($matches[0]) > 150
) {
    respondJson(422, [
        'success' => false,
        'message' => '店舗名は150文字以内で入力してください。',
    ]);
}

// 店舗状態の入力チェック
if (!in_array($storeStatus, ['active', 'inactive'], true)) {
    respondJson(422, [
        'success' => false,
        'message' => '店舗の状態が正しくありません。',
    ]);
}

// データベース接続設定
require_once __DIR__ . '/../../farm-himitu/config.php';

try {
    // 対象店舗が存在することを確認する
    $stmt = $pdo->prepare(
        'SELECT id FROM stores WHERE id = :id'
    );

    $stmt->execute(['id' => $storeId]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        respondJson(404, [
            'success' => false,
            'message' => '指定された店舗が見つかりません。',
        ]);
    }

    // 店舗情報を更新する
    $stmt = $pdo->prepare(
        'UPDATE stores
         SET store_code = :store_code,
             name = :name,
             status = :status
         WHERE id = :id'
    );

    $stmt->execute([
        'store_code' => $storeCode,
        'name' => $storeName,
        'status' => $storeStatus,
        'id' => $storeId,
    ]);

    // 更新後の店舗情報を取得する
    $stmt = $pdo->prepare(
        'SELECT id, store_code, name, status, created_at
         FROM stores
         WHERE id = :id'
    );

    $stmt->execute(['id' => $storeId]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    respondJson(200, [
        'success' => true,
        'message' => '店舗情報を更新しました。',
        'store' => $store,
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

    error_log('Store update API failed: ' . $e->getMessage());

    respondJson(500, [
        'success' => false,
        'message' => '店舗を更新できませんでした。時間をおいて再度お試しください。',
    ]);
}