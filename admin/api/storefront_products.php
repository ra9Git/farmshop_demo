<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

try {
    // 既存のデータベース接続設定を読み込む
    require_once __DIR__ . '/../../farm-himitu-demo/config.php';

    // 公開対象の商品と、各商品の先頭画像を取得する
    $sql = "
        SELECT
            p.id AS id,

            COALESCE(
                NULLIF(sp.display_name, ''),
                p.name
            ) AS name,

            COALESCE(
                NULLIF(sp.display_subtitle, ''),
                p.subtitle
            ) AS subtitle,

            COALESCE(
                NULLIF(sp.display_description, ''),
                p.description
            ) AS description,

            sp.price AS price,

            COALESCE(
                NULLIF(sp.inventory_unit, ''),
                '個'
            ) AS unit,

            (
                SELECT CONCAT(
                    '/farm-admin/',
                    ma.storage_key
                )
                FROM product_images pi
                INNER JOIN media_assets ma
                    ON ma.id = pi.media_asset_id
                WHERE pi.product_id = p.id
                ORDER BY
                    pi.display_order ASC,
                    pi.id ASC
                LIMIT 1
            ) AS image,

            (
                SELECT COALESCE(ma.alt_text, p.name)
                FROM product_images pi
                INNER JOIN media_assets ma
                    ON ma.id = pi.media_asset_id
                WHERE pi.product_id = p.id
                ORDER BY
                    pi.display_order ASC,
                    pi.id ASC
                LIMIT 1
            ) AS imageAlt,

            CASE
                WHEN sp.is_featured = 1 THEN 'おすすめ'
                ELSE ''
            END AS label,

            '野菜' AS category

        FROM store_products sp

        INNER JOIN products p
            ON p.id = sp.product_id

        INNER JOIN stores s
            ON s.id = sp.store_id

        WHERE
            sp.store_id = 1
            AND s.status = 'active'
            AND sp.is_published = 1
            AND sp.is_purchasable = 1
            AND sp.status = 'active'
            AND p.is_published = 1

            AND EXISTS (
                SELECT 1
                FROM store_sites ss
                WHERE
                    ss.store_id = sp.store_id
                    AND ss.site_code = 'karino-store'
                    AND ss.is_active = 1
            )

            AND (
                sp.sale_start_at IS NULL
                OR sp.sale_start_at <= NOW()
            )

            AND (
                sp.sale_end_at IS NULL
                OR sp.sale_end_at >= NOW()
            )

        ORDER BY
            sp.display_order ASC,
            sp.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // React側で数値として扱えるようにする
    foreach ($products as &$product) {
        $product['id'] = (int) $product['id'];
        $product['price'] = (int) $product['price'];

        $product['label'] = $product['label'] ?? '';
        $product['image'] = $product['image'] ?? '';
        $product['imageAlt'] = $product['imageAlt'] ?? $product['name'];
        $product['subtitle'] = $product['subtitle'] ?? '';
    }
    unset($product);

    echo json_encode(
        [
            'success' => true,
            'products' => $products,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

} catch (Throwable $e) {
    // 詳細なエラーはサーバーのログに記録し、公開レスポンスには含めない
    error_log(
        'storefront_products.php failed: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode(
        [
            'success' => false,
            'message' => '商品情報を取得できませんでした。',
        ],
        JSON_UNESCAPED_UNICODE
    );
}