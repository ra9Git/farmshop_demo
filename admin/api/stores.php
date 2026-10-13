<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

// ログイン確認
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'ログインが必要です。',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// システム管理者のみ許可
$role = (string) ($_SESSION['role'] ?? '');

if ($role !== 'system_admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'この操作を行う権限がありません。',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// DB接続設定
require_once __DIR__ . '/../../farm-himitu-demo/config.php';

try {
    $stmt = $pdo->prepare(
        'SELECT id, store_code, name, status, created_at
         FROM stores
         ORDER BY id DESC'
    );

    $stmt->execute();
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stores' => $stores,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('Store API retrieval failed: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => '店舗一覧を取得できませんでした。',
    ], JSON_UNESCAPED_UNICODE);
}