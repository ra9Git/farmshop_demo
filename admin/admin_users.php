<?php

session_start();

// 未ログインならログイン画面へ戻す
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// システム管理者以外はアクセス拒否
$role = (string) ($_SESSION['role'] ?? '');

if ($role !== 'system_admin') {
    http_response_code(403);
    exit('このページを利用する権限がありません。');
}

// CSRFトークンを用意する
if (empty($_SESSION['admin_users_csrf_token'])) {
    $_SESSION['admin_users_csrf_token'] = bin2hex(random_bytes(32));
}

// データベース接続設定
require_once __DIR__ . '/../farm-himitu-demo/config.php';

$errorMessage = '';
$loginId = '';
$storeIdInput = '';

// 登録先に選択できる有効な店舗を取得する
$stmt = $pdo->query(
    "SELECT id, store_code, name
     FROM stores
     WHERE status = 'active'
     ORDER BY id ASC"
);

$stores = $stmt->fetchAll();

// 新規登録処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedLoginId = $_POST['login_id'] ?? null;
    $password = $_POST['password'] ?? null;
    $postedStoreId = $_POST['store_id'] ?? null;
    $csrfToken = $_POST['csrf_token'] ?? null;

    $sessionToken = $_SESSION['admin_users_csrf_token'] ?? '';

    if (
        !is_string($postedLoginId) ||
        !is_string($password) ||
        !is_string($postedStoreId) ||
        !is_string($csrfToken)
    ) {
        $errorMessage = '入力内容を確認してください。';

    } elseif (
        $sessionToken === '' ||
        !hash_equals($sessionToken, $csrfToken)
    ) {
        $errorMessage = '画面の有効期限が切れました。ページを再読み込みしてください。';

    } else {
        $loginId = trim($postedLoginId);
        $storeIdInput = $postedStoreId;

        // ログインIDは半角英数字と一部記号に限定する
        if (
            $loginId === '' ||
            !preg_match('/\A[A-Za-z0-9._-]{1,100}\z/', $loginId)
        ) {
            $errorMessage = 'ログインIDは半角英数字、ピリオド、ハイフン、アンダースコアを使い、100文字以内で入力してください。';

        // パスワードは12文字以上、200文字以内
        } elseif (
            preg_match_all('/./us', $password, $passwordChars) === false ||
            count($passwordChars[0]) < 12 ||
            count($passwordChars[0]) > 200
        ) {
            $errorMessage = 'パスワードは12文字以上、200文字以内で入力してください。';

        // 店舗IDの形式を確認する
        } elseif (
            filter_var(
                $postedStoreId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) === false
        ) {
            $errorMessage = '所属店舗を選択してください。';

        } else {
            $selectedStoreId = (int) $postedStoreId;

            // 選択された店舗が存在し、有効状態か確認する
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM stores
                 WHERE id = :id
                   AND status = 'active'"
            );

            $stmt->execute(['id' => $selectedStoreId]);
            $selectedStore = $stmt->fetch();

            if (!$selectedStore) {
                $errorMessage = '選択された店舗が見つからないか、無効になっています。';

            } else {
                // 同じログインIDがすでに存在しないか確認する
                $stmt = $pdo->prepare(
                    'SELECT id
                     FROM admin_users
                     WHERE login_id = :login_id
                     LIMIT 1'
                );

                $stmt->execute(['login_id' => $loginId]);
                $existingUser = $stmt->fetch();

                if ($existingUser) {
                    $errorMessage = 'このログインIDはすでに使用されています。';

                } else {
                    try {
                        // パスワードをハッシュ化する
                        $passwordHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        // 店舗管理者として登録する
                        $stmt = $pdo->prepare(
                            'INSERT INTO admin_users
                                (login_id, password_hash, role, store_id, status)
                             VALUES
                                (:login_id, :password_hash, :role, :store_id, :status)'
                        );

                        $stmt->execute([
                            'login_id' => $loginId,
                            'password_hash' => $passwordHash,
                            'role' => 'store_admin',
                            'store_id' => $selectedStoreId,
                            'status' => 'active',
                        ]);

                        // 二重送信を避けるため一覧へリダイレクト
                        unset($_SESSION['admin_users_csrf_token']);

                        header('Location: admin_users.php?created=1');
                        exit;

                    } catch (PDOException $e) {
                        // DB側で重複が検出された場合
                        if (
                            isset($e->errorInfo[1]) &&
                            (int) $e->errorInfo[1] === 1062
                        ) {
                            $errorMessage = 'このログインIDはすでに使用されています。';
                        } else {
                            error_log(
                                'Admin user creation failed: ' . $e->getMessage()
                            );

                            http_response_code(500);
                            $errorMessage = '管理者を登録できませんでした。時間をおいて再度お試しください。';
                        }
                    }
                }
            }
        }
    }
}

// 管理者一覧を取得する
$stmt = $pdo->query(
    'SELECT
        au.id,
        au.login_id,
        au.role,
        au.store_id,
        au.status,
        au.created_at,
        s.name AS store_name
     FROM admin_users AS au
     LEFT JOIN stores AS s
        ON au.store_id = s.id
     ORDER BY au.id ASC'
);

$adminUsers = $stmt->fetchAll();

?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>管理者管理 | Farm Shop</title>
    <link rel="stylesheet" href="./common.css">
</head>
<body>
    <header class="common-header">
        <a class="common-brand" href="./index.php">
            <span class="common-brand-mark" aria-hidden="true">F</span>
            <span>
                <span class="common-brand-name">Farm Shop</span>
                <span class="common-brand-caption">管理システム</span>
            </span>
        </a>

        <nav class="common-nav" aria-label="メインメニュー">
            <a href="./index.php">管理画面トップ</a>
            <a href="./stores.php">店舗管理</a>
            <a href="./admin_users.php" class="is-active" aria-current="page">
                管理者管理
            </a>
            <a href="./logout.php" class="common-logout-link">ログアウト</a>
        </nav>
    </header>

    <main class="common-main">
        <div class="common-page-heading">
            <p class="common-eyebrow">ADMINISTRATION</p>
            <h1 class="common-page-title">管理者管理</h1>
            <p class="common-description">
                管理者アカウントの一覧確認と、店舗管理者の新規登録を行います。
            </p>
        </div>

        <?php if (($_GET['created'] ?? '') === '1'): ?>
            <div class="common-alert common-alert-success" role="status">
                店舗管理者を登録しました。
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="common-alert" role="alert">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="common-card admin-users-list-card">
            <div class="admin-users-section-heading">
                <div>
                    <h2>管理者一覧</h2>
                    <p>登録されている管理者アカウントを確認できます。</p>
                </div>
                <span class="admin-users-count">
                    <?= count($adminUsers) ?> 件
                </span>
            </div>

            <?php if (count($adminUsers) === 0): ?>
                <div class="admin-users-empty">
                    管理者アカウントは登録されていません。
                </div>
            <?php else: ?>
                <div class="common-table-wrapper">
                    <table class="common-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ログインID</th>
                                <th>権限</th>
                                <th>所属店舗</th>
                                <th>状態</th>
                                <th>登録日時</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adminUsers as $user): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars(
                                            (string) $user['id'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>
                                    <td class="admin-users-login-id">
                                        <?= htmlspecialchars(
                                            $user['login_id'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>
                                    <td>
                                        <?php if ($user['role'] === 'system_admin'): ?>
                                            <span class="admin-users-role admin-users-role-system">
                                                システム管理者
                                            </span>
                                        <?php elseif ($user['role'] === 'store_admin'): ?>
                                            <span class="admin-users-role">
                                                店舗管理者
                                            </span>
                                        <?php else: ?>
                                            <?= htmlspecialchars(
                                                $user['role'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['store_id'] === null): ?>
                                            <span class="admin-users-no-store">所属なし</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars(
                                                $user['store_name'] ?? '店舗が見つかりません',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <span class="common-status">
                                                <span class="common-status-dot"></span>
                                                有効
                                            </span>
                                        <?php else: ?>
                                            <span class="admin-users-inactive">
                                                <?= htmlspecialchars(
                                                    $user['status'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) === 'inactive' ? '無効' : htmlspecialchars(
                                                    $user['status'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="admin-users-date">
                                        <?= htmlspecialchars(
                                            $user['created_at'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="common-card admin-users-create-card">
            <div class="admin-users-section-heading">
                <div>
                    <h2>店舗管理者を新規登録</h2>
                    <p>所属店舗ごとの管理画面にログインするアカウントを作成します。</p>
                </div>
            </div>

            <?php if (count($stores) === 0): ?>
                <div class="common-alert">
                    登録先に選べる有効な店舗がありません。先に店舗を登録してください。
                    <p class="admin-users-alert-link">
                        <a href="./stores.php">店舗管理へ移動</a>
                    </p>
                </div>
            <?php else: ?>
                <form method="post" action="admin_users.php" class="common-form admin-users-form">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $_SESSION['admin_users_csrf_token'],
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
                            pattern="[A-Za-z0-9._-]{1,100}"
                            title="半角英数字、ピリオド、ハイフン、アンダースコアを使用できます。"
                            required
                            autocomplete="off"
                            value="<?= htmlspecialchars(
                                $loginId,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                        <p class="common-help">
                            半角英数字・ピリオド・ハイフン・アンダースコアを使用できます。
                        </p>
                    </div>

                    <div class="common-form-group">
                        <label for="admin-password">パスワード（12文字以上）</label>
                        <input
                            class="common-input"
                            id="admin-password"
                            type="password"
                            name="password"
                            minlength="12"
                            maxlength="200"
                            required
                            autocomplete="new-password"
                        >
                        <p class="common-help">
                            12文字以上、200文字以内で設定してください。
                        </p>
                    </div>

                    <div class="common-form-group">
                        <label for="store-id">所属店舗</label>
                        <select
                            class="common-select"
                            id="store-id"
                            name="store_id"
                            required
                        >
                            <option value="">店舗を選択してください</option>
                            <?php foreach ($stores as $store): ?>
                                <option
                                    value="<?= (int) $store['id'] ?>"
                                    <?= (string) $store['id'] === $storeIdInput
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $store['name'] . '（' . $store['store_code'] . '）',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="common-form-group">
                        <span class="common-label">付与する権限</span>
                        <div class="admin-users-permission">
                            <span class="admin-users-role">店舗管理者</span>
                            <p class="common-help">
                                登録後、このアカウントは選択した店舗の管理画面を利用します。
                            </p>
                        </div>
                    </div>

                    <div class="admin-users-form-actions">
                        <button
                            type="submit"
                            class="common-button common-button-primary"
                        >
                            店舗管理者を登録する
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <footer class="common-footer admin-users-footer">
            <a href="./index.php">管理画面トップへ戻る</a>
            <a href="./logout.php">ログアウト</a>
        </footer>
    </main>
</body>
</html>