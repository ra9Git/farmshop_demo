
<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../farm-himitu/config.php';

    $stmt = $pdo->query('SELECT DATABASE() AS db_name');
    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'データベース接続成功',
        'database' => $result['db_name']
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('DB test failed: ' . $e->getMessage());
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => '接続テストに失敗しました。サーバーのエラーログを確認してください。'
    ], JSON_UNESCAPED_UNICODE);
}
