<?php

$isHttps = isset($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== ''
    && $_SERVER['HTTPS'] !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

if (isset($_SESSION['admin_id'])) {
    session_write_close();
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../farm-himitu-demo/config.php';

$message = '';
$loginId = '';

if (empty($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $loginId = $_POST['login_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    $sessionToken = $_SESSION['login_csrf_token'] ?? '';

    $csrfMatches = is_string($csrfToken)
        && is_string($sessionToken)
        && $sessionToken !== ''
        && hash_equals($sessionToken, $csrfToken);

    if (
        !is_string($loginId) ||
        !is_string($password) ||
        !is_string($csrfToken) ||
        !is_string($sessionToken) ||
        $sessionToken === '' ||
        !$csrfMatches
    ) {
        error_log(
            'Admin login: CSRF validation failed. ' .
            'session_cookie=' .
            (isset($_COOKIE[session_name()]) ? 'present' : 'missing') .
            ', session_token=' .
            ($sessionToken !== '' ? 'present' : 'missing')
        );

        $message = '入力内容を確認して、もう一度お試しください。';

    } elseif (
        trim($loginId) === '' ||
        strlen($loginId) > 100 ||
        $password === '' ||
        strlen($password) > 1024
    ) {
        $message = 'ログインIDまたはパスワードが正しくありません。';

    } else {
        $loginId = trim($loginId);

        try {
            $stmt = $pdo->prepare(
                'SELECT id, login_id, password_hash, role, store_id, status
                 FROM admin_users
                 WHERE login_id = :login_id
                 LIMIT 1'
            );

            $stmt->execute([
                'login_id' => $loginId,
            ]);

            $admin = $stmt->fetch();

            $passwordCorrect = $admin
                && password_verify($password, $admin['password_hash']);

            if (
                $admin &&
                $admin['status'] === 'active' &&
                $passwordCorrect
            ) {
                session_regenerate_id(true);

                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['login_id'] = $admin['login_id'];
                $_SESSION['role'] = $admin['role'];
                $_SESSION['store_id'] = $admin['store_id'] === null
                    ? null
                    : (int) $admin['store_id'];

                $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));

                header('Location: index.php');
                exit;
            }

            $message = 'ログインIDまたはパスワードが正しくありません。';

        } catch (Throwable $e) {
            error_log('Admin login failed: ' . $e->getMessage());
            http_response_code(500);
            $message = '現在ログインできません。時間をおいて再度お試しください。';
        }
    }
}

?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>ログイン | Farm Shop 管理システム</title>

    <link rel="stylesheet" href="./common.css">
</head>
<body class="login-page">

    <header class="login-header">
        <a href="./login.php" class="common-brand">
            <span class="common-brand-mark" aria-hidden="true">F</span>
            <span>
                <span class="common-brand-name">Farm Shop</span>
                <span class="common-brand-caption">管理システム</span>
            </span>
        </a>
    </header>

    <main class="login-container">
        <section class="login-card" aria-labelledby="login-title">

            <div class="login-heading">
                <p class="login-eyebrow">ADMIN PORTAL</p>
                <h1 class="login-title" id="login-title">ログイン</h1>
                <p class="login-description">
                    アカウント情報を入力してください。
                </p>
            </div>

            <?php if ($message !== ''): ?>
                <div class="common-alert" role="alert" aria-live="polite">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form
                class="login-form"
                method="post"
                action="login.php"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['login_csrf_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <div class="common-form-group">
                    <label for="login-id">ログインID</label>
                    <input
                        class="common-input"
                        id="login-id"
                        type="text"
                        name="login_id"
                        maxlength="100"
                        autocomplete="username"
                        placeholder="ログインIDを入力"
                        required
                        autofocus
                        value="<?= htmlspecialchars(
                            $loginId,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                </div>

                <div class="common-form-group">
                    <label for="login-password">パスワード</label>
                    <input
                        class="common-input"
                        id="login-password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        placeholder="パスワードを入力"
                        required
                    >
                </div>

                <button
                    class="common-button common-button-primary login-submit"
                    type="submit"
                >
                    ログイン
                </button>
            </form>

            <p class="login-notice">
                許可されたユーザー専用の管理画面です。
            </p>

        </section>
    </main>

    <footer class="login-footer">
        &copy; Farm Shop
    </footer>

</body>
</html>