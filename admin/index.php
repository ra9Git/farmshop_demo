<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$loginId = $_SESSION['login_id'] ?? '';
$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['system_admin', 'store_admin'], true)) {
    session_destroy();
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../farm-himitu-demo/config.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$store = null;

// 店舗管理者の場合だけ店舗情報を取得
if ($role === 'store_admin') {
    try {
        $stmt = $pdo->prepare(
            'SELECT id, login_id, role, store_id, status
             FROM admin_users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$admin ||
            $admin['status'] !== 'active' ||
            $admin['role'] !== 'store_admin' ||
            empty($admin['store_id'])
        ) {
            session_destroy();
            header('Location: login.php');
            exit;
        }

        $stmt = $pdo->prepare(
            'SELECT id, store_code, name, status
             FROM stores
             WHERE id = :store_id
             LIMIT 1'
        );
        $stmt->execute([
            ':store_id' => (int) $admin['store_id']
        ]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$store || $store['status'] !== 'active') {
            http_response_code(403);
            exit('利用できる店舗が見つからないか、店舗が停止されています。');
        }

        $loginId = $admin['login_id'];

    } catch (PDOException $e) {
        error_log('index.php DB error: ' . $e->getMessage());
        http_response_code(500);
        exit('店舗情報を取得できませんでした。');
    }
}

$isSystemAdmin = $role === 'system_admin';
$roleLabel = $isSystemAdmin ? 'システム管理者' : '店舗管理者';
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>管理メニュー | Farm Shop</title>

    <link rel="stylesheet" href="./common.css">
</head>
<body>

    <header class="common-header">
        <a href="./index.php" class="common-brand">
            <span class="common-brand-mark" aria-hidden="true">F</span>
            <span>
                <span class="common-brand-name">Farm Shop</span>
                <span class="common-brand-caption">管理システム</span>
            </span>
        </a>

        <nav class="common-nav" aria-label="ユーザーメニュー">
            <span class="common-user-info">
                <?= h($loginId) ?>
                <span class="common-role-label"><?= h($roleLabel) ?></span>
            </span>
            <a href="./logout.php" class="common-logout-link">ログアウト</a>
        </nav>
    </header>

    <main class="common-main">

        <section class="common-page-heading">
            <p class="common-eyebrow">DASHBOARD</p>

            <h1 class="common-page-title">
                <?= $isSystemAdmin ? '管理メニュー' : '店舗ダッシュボード' ?>
            </h1>

            <p class="common-description">
                <?php if ($isSystemAdmin): ?>
                    管理する項目を選択してください。
                <?php else: ?>
                    店舗情報の確認や、商品の管理を行えます。
                <?php endif; ?>
            </p>
        </section>

        <?php if ($isSystemAdmin): ?>

            <section class="dashboard-grid" aria-label="管理メニュー">

                <a href="./stores.php" class="dashboard-card">
                    <span class="dashboard-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M3 10L5 4H19L21 10" />
                            <path d="M4 10V20H20V10" />
                            <path d="M9 20V14H15V20" />
                            <path d="M3 10C3 12 6 12 6 10C6 12 9 12 9 10C9 12 12 12 12 10C12 12 15 12 15 10C15 12 18 12 18 10C18 12 21 12 21 10" />
                        </svg>
                    </span>

                    <span class="dashboard-card-title">店舗管理</span>

                    <span class="dashboard-card-description">
                        店舗情報の確認・登録・編集を行います。
                    </span>

                    <span class="dashboard-card-link">
                        管理画面へ <span aria-hidden="true">→</span>
                    </span>
                </a>

                <a href="./admin_users.php" class="dashboard-card">
                    <span class="dashboard-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="9" cy="8" r="4" />
                            <path d="M2 20C2 16.5 5 14 9 14C13 14 16 16.5 16 20" />
                            <path d="M16 5.5C18.2 5.8 20 7.5 20 9.5C20 11.5 18.5 13 16.5 13.5" />
                            <path d="M18 16C20.5 16.8 22 18.2 22 20" />
                        </svg>
                    </span>

                    <span class="dashboard-card-title">管理者管理</span>

                    <span class="dashboard-card-description">
                        管理者アカウントの確認・管理を行います。
                    </span>

                    <span class="dashboard-card-link">
                        管理画面へ <span aria-hidden="true">→</span>
                    </span>
                </a>

            </section>

        <?php else: ?>

            <section class="common-card store-summary">
                <div class="store-summary-heading">
                    <div>
                        <p class="common-eyebrow">YOUR STORE</p>
                        <h2 class="store-summary-title">
                            <?= h($store['name']) ?>
                        </h2>
                    </div>

                    <span class="common-status">
                        <span class="common-status-dot" aria-hidden="true"></span>
                        運営中
                    </span>
                </div>

                <dl class="store-details">
                    <div>
                        <dt>店舗ID</dt>
                        <dd><?= h($store['id']) ?></dd>
                    </div>

                    <div>
                        <dt>店舗コード</dt>
                        <dd><?= h($store['store_code']) ?></dd>
                    </div>

                    <div>
                        <dt>店舗ステータス</dt>
                        <dd><?= h($store['status']) ?></dd>
                    </div>
                </dl>
            </section>

            <section class="dashboard-grid store-dashboard-grid"
                     aria-label="店舗管理メニュー">

                <a href="./store_products.php" class="dashboard-card">
                    <span class="dashboard-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M4 8H20L19 21H5L4 8Z" />
                            <path d="M8 8V6C8 3.8 9.8 2 12 2C14.2 2 16 3.8 16 6V8" />
                            <path d="M9 12V13" />
                            <path d="M15 12V13" />
                        </svg>
                    </span>

                    <span class="dashboard-card-title">商品管理</span>

                    <span class="dashboard-card-description">
                        商品の価格・在庫数・公開状態を管理します。
                    </span>

                    <span class="dashboard-card-link">
                        商品管理へ <span aria-hidden="true">→</span>
                    </span>
                </a>

            </section>

        <?php endif; ?>

        <footer class="common-footer">
            &copy; Farm Shop
        </footer>

    </main>

</body>
</html>