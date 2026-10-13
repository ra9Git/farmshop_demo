<?php
// このファイルは各店舗向け管理画面から require_once して使用します。
// PHPファイルの先頭に空白やBOMを入れず、UTF-8で保存してください。

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../farm-himitu-demo/config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('データベース接続を確認できません。');
}

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// セッションの値だけで権限を判断せず、毎回DB上の管理者情報を確認する。
$adminStmt = $pdo->prepare(
    'SELECT id, login_id, role, store_id, status
     FROM admin_users
     WHERE id = :id
     LIMIT 1'
);
$adminStmt->execute(['id' => (int) $_SESSION['admin_id']]);
$currentAdmin = $adminStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentAdmin || $currentAdmin['status'] !== 'active') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookieParams = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $cookieParams['path'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($currentAdmin['role'] !== 'store_admin' || empty($currentAdmin['store_id'])) {
    http_response_code(403);
    exit('このページは店舗管理者専用です。');
}

// 管理者レコードに登録された店舗を取得。URLやフォームから店舗IDを受け取らない。
$storeStmt = $pdo->prepare(
    'SELECT id, store_code, name, status
     FROM stores
     WHERE id = :id
     LIMIT 1'
);
$storeStmt->execute(['id' => (int) $currentAdmin['store_id']]);
$currentStore = $storeStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentStore || $currentStore['status'] !== 'active') {
    http_response_code(403);
    exit('所属店舗が存在しないか、停止中のため操作できません。');
}

$currentAdminId = (int) $currentAdmin['id'];
$currentStoreId = (int) $currentStore['id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('verify_csrf_or_die')) {
    function verify_csrf_or_die(): void
    {
        $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
        $savedToken = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $submittedToken === '' || $savedToken === '' || !hash_equals($savedToken, $submittedToken)) {
            http_response_code(400);
            exit('送信内容を確認できませんでした。ページを再読み込みして、もう一度お試しください。');
        }
    }
}

if (!function_exists('parse_unsigned_form_value')) {
    function parse_unsigned_form_value($value, string $label, array &$errors): ?int
    {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/\A[0-9]+\z/', $value)) {
            $errors[] = $label . 'は0以上の整数で入力してください。';
            return null;
        }

        // productsのprice / stock_quantityはMySQL INT UNSIGNED。
        if ((float) $value > 4294967295) {
            $errors[] = $label . 'は4,294,967,295以下で入力してください。';
            return null;
        }

        return (int) $value;
    }
}

if (!function_exists('text_length')) {
    function text_length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        preg_match_all('/./us', $value, $matches);
        return count($matches[0]);
    }
}
