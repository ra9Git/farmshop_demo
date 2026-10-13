<?php
session_start();

// セッション内のログイン情報を削除
$_SESSION = [];

// セッションCookieを削除
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// サーバー側のセッションを破棄
session_destroy();

// ログイン画面へ戻る
header('Location: login.php');
exit;