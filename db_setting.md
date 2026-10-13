# 複数店舗対応 通販・商品管理システム DB設計書（案）

-   文書バージョン：0.1（レビュー用）
-   対象技術：React.js / PHP API / MySQL
-   配置：管理画面・PHP
    API・メインDBは運営者サーバー、販売サイトは店舗ごとのFTPサーバー
-   方針：初期開発は1店舗で進めるが、開発環境では複数の仮想店舗を作り、店舗分離を検証する

> この文書は、現時点で共有された要件をもとにしたレビュー用の設計案です。決定が必要な事項は「未決定事項」に記載しています。SQLはMySQL
> 8.0系を想定した初期案です。実際のレンタルサーバーのMySQLバージョン、照合順序、利用可能な機能を確認してから実行してください。

------------------------------------------------------------------------

## 1. システム構成と設計方針

### 1.1 構成

1.  管理画面：React.js
2.  API：PHP（HTTPS、JSON）
3.  メインDB：MySQL
4.  販売サイト：店舗ごとのサーバーにReactのビルド済みファイルをFTP配置
5.  画像：当初は管理サーバーのWeb公開可能な画像領域に保存し、DBには画像の保存キー（相対パス）を記録する。将来は画像専用ストレージへ移行できるよう、サーバー内の絶対パスをDBに保存しない。

販売サイトはDBに直接接続しない。公開商品やサイト設定はPHP
APIから取得する。管理操作はログイン認証と店舗単位の認可を必須とする。CORSはブラウザ制御の一要素であり、認証・認可の代わりにはならない。

### 1.2 設計原則

-   店舗ID（`store_id`）を店舗固有データに持たせ、APIでアクセス対象店舗を必ず検証する。
-   商品そのものの情報と、店舗ごとの販売条件を分離する。
-   初期運用では商品を登録した店舗だけに販売設定を作る。将来、同一商品情報を別店舗でも利用する場合は、その店舗用の販売設定を別に作成する。
-   在庫は共有しない。数量・在庫表示・購入可否は店舗別販売設定で管理する。
-   公開状態、購入可能状態、在庫表示を別の概念として扱う。
-   推し商品は店舗ごとに設定する。推し商品を先頭にし、その後に指定されたソート条件を適用する。
-   商品一覧や在庫管理画面のソート条件（価格順、在庫順、人気順など）はAPIの検索条件とし、全てを商品レコードに保存しない。手動表示順だけを保存する。
-   自由なHTML/JavaScriptをDBに保存して実行せず、定義済みのサイト表示パーツとデータを保存する。
-   注文機能は既存のPHPメールフォームを初期段階で継続利用できるようにする。ただし人気順・注文履歴・自動在庫減算には注文データ保存の仕組みが必要。

------------------------------------------------------------------------

## 2. テーブル一覧

  ------------------------------------------------------------------------------------------------------------------------------
  区分              テーブル                     役割                                       導入時期
  ----------------- ---------------------------- ------------------------------------------ ------------------------------------
  店舗・権限        `stores`                     店舗情報                                   初期

  店舗・権限        `users`                      管理画面利用者                             初期

  店舗・権限        `store_users`                利用者と店舗・権限の紐付け                 初期

  商品              `products`                   商品の共通情報                             初期

  商品              `store_products`             店舗ごとの価格・在庫・公開・並び順         初期

  商品              `media_assets`               アップロード画像の共通管理                 初期

  商品              `product_images`             商品画像と表示順の紐付け                   初期

  サイト設定        `site_templates`             選択可能な標準テンプレートの定義           初期

  サイト設定        `store_sites`                店舗の販売サイト・デザイン方式の設定       初期

  サイト設定        `store_site_sections`        メインビジュアルや紹介文などの表示パーツ   初期

  商品分類          `categories`                 カテゴリの定義                             初期

  商品分類          `store_product_categories`   店舗商品とカテゴリの紐付け                 初期

  注文・販売        `orders`                     注文・入金・注文状態                       将来拡張。人気順を実装するなら必要

  注文・販売        `order_items`                注文時点の商品名・価格・数量               将来拡張。人気順を実装するなら必要

  在庫履歴          `inventory_logs`             在庫変更の履歴                             自動在庫管理導入時を推奨
  ------------------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------

## 3. テーブル定義

共通ルール： - 主キーは `BIGINT UNSIGNED AUTO_INCREMENT`
を基本とする。 - 日時は原則
`DATETIME`（サーバー側で扱うタイムゾーンを統一）。 -
金額は浮動小数点ではなく `INT UNSIGNED`（日本円の整数）を使用する。 -
真偽値は `TINYINT(1)` を使用する。 -
外部キー制約を利用できる環境では設定する。API側でも整合性を検証する。 -
以下の `VARCHAR`
長やインデックスは初期案であり、実データ・サーバー環境に合わせて調整する。

### 3.1 `stores` --- 店舗

  -----------------------------------------------------------------------------------
  カラム         型             NULL           初期値              説明
  -------------- -------------- -------------- ------------------- ------------------
  `id`           BIGINT         NO             自動採番            主キー
                 UNSIGNED                                          

  `store_code`   VARCHAR(50)    NO             なし                店舗コード。一意

  `name`         VARCHAR(150)   NO             なし                店舗名

  `status`       VARCHAR(20)    NO             `active`            `active` /
                                                                   `suspended` /
                                                                   `closed`

  `created_at`   DATETIME       NO             CURRENT_TIMESTAMP   登録日時

  `updated_at`   DATETIME       NO             CURRENT_TIMESTAMP   更新日時
  -----------------------------------------------------------------------------------

### 3.2 `users` --- 管理画面利用者

  -------------------------------------------------------------------------------------------------------------
  カラム            型             NULL           初期値              説明
  ----------------- -------------- -------------- ------------------- -----------------------------------------
  `id`              BIGINT         NO             自動採番            主キー
                    UNSIGNED                                          

  `login_id`        VARCHAR(190)   NO             なし                ログインID。一意

  `password_hash`   VARCHAR(255)   NO             なし                PHPのパスワードハッシュ関数で生成した値

  `display_name`    VARCHAR(150)   NO             なし                表示名

  `role`            VARCHAR(30)    NO             `store_staff`       `system_admin` / `store_staff`

  `status`          VARCHAR(20)    NO             `active`            `active` / `disabled`

  `last_login_at`   DATETIME       YES            NULL                最終ログイン日時

  `created_at`      DATETIME       NO             CURRENT_TIMESTAMP   登録日時

  `updated_at`      DATETIME       NO             CURRENT_TIMESTAMP   更新日時
  -------------------------------------------------------------------------------------------------------------

`system_admin` は全店舗を管理できる。店舗担当者の対象店舗は
`store_users` で判定する。パスワードそのものは保存しない。

### 3.3 `store_users` --- 利用者と店舗の紐付け

  -------------------------------------------------------------------------------
  カラム         型             NULL           初期値              説明
  -------------- -------------- -------------- ------------------- --------------
  `store_id`     BIGINT         NO             なし                `stores.id`
                 UNSIGNED                                          

  `user_id`      BIGINT         NO             なし                `users.id`
                 UNSIGNED                                          

  `store_role`   VARCHAR(30)    NO             `manager`           `manager` /
                                                                   `staff` /
                                                                   `viewer`

  `created_at`   DATETIME       NO             CURRENT_TIMESTAMP   登録日時
  -------------------------------------------------------------------------------

複合主キー：`(store_id, user_id)`。1人が複数店舗を担当できる。APIは画面から送信された
`store_id` をそのまま信用せず、ログイン利用者の権限を検証する。

### 3.4 `products` --- 商品の共通情報

  -----------------------------------------------------------------------------------------------------------
  カラム                 型             NULL           初期値              説明
  ---------------------- -------------- -------------- ------------------- ----------------------------------
  `id`                   BIGINT         NO             自動採番            主キー
                         UNSIGNED                                          

  `name`                 VARCHAR(200)   NO             なし                商品名

  `subtitle`             VARCHAR(200)   YES            NULL                品種名など。例：ドルチェドリーム

  `description`          TEXT           YES            NULL                基本の商品説明。改行を保持できる

  `created_by_user_id`   BIGINT         YES            NULL                登録利用者
                         UNSIGNED                                          

  `created_at`           DATETIME       NO             CURRENT_TIMESTAMP   登録日時

  `updated_at`           DATETIME       NO             CURRENT_TIMESTAMP   更新日時
  -----------------------------------------------------------------------------------------------------------

商品登録時は、その店舗用の `store_products`
レコードも同時に作る。基本的には店舗固有商品として運用するが、将来、共通の商品情報を別店舗に割り当てる場合も、店舗ごとの販売設定と在庫は独立させる。

### 3.5 `store_products` --- 店舗ごとの販売設定（中心テーブル）

  --------------------------------------------------------------------------------------------------------------------------------------
  カラム                    型             NULL           初期値              説明
  ------------------------- -------------- -------------- ------------------- ----------------------------------------------------------
  `id`                      BIGINT         NO             自動採番            主キー
                            UNSIGNED                                          

  `store_id`                BIGINT         NO             なし                販売店舗
                            UNSIGNED                                          

  `product_id`              BIGINT         NO             なし                `products.id`
                            UNSIGNED                                          

  `product_code`            VARCHAR(80)    YES            NULL                店舗内の商品管理コード

  `display_name`            VARCHAR(200)   YES            NULL                店舗で商品名を変える場合の上書き。NULLなら共通名

  `display_subtitle`        VARCHAR(200)   YES            NULL                店舗で補足名を変える場合の上書き

  `display_description`     TEXT           YES            NULL                店舗独自説明。NULLなら共通説明

  `price`                   INT UNSIGNED   NO             0                   販売価格。税込価格を保存する方針案

  `inventory_mode`          VARCHAR(20)    NO             `quantity`          `quantity`（数量）/ `status`（テキスト）

  `stock_quantity`          INT UNSIGNED   YES            0                   数量管理時の在庫数。数量管理なしではNULL

  `inventory_unit`          VARCHAR(30)    YES            NULL                個、瓶、袋、箱、セットなど

  `low_stock_threshold`     INT UNSIGNED   NO             3                   これ以下なら「残り僅か」と判定する基準。数量管理時に使用

  `stock_status`            VARCHAR(20)    NO             `available`         `available` / `low` / `sold_out`。主にテキスト管理で使用

  `is_published`            TINYINT(1)     NO             0                   1=公開、0=非公開

  `is_purchasable`          TINYINT(1)     NO             1                   1=購入受付、0=購入停止

  `is_featured`             TINYINT(1)     NO             0                   推し商品か

  `display_order`           INT            NO             0                   手動表示順。小さい数を先に表示

  `sale_start_at`           DATETIME       YES            NULL                販売開始日時。NULLなら指定なし

  `sale_end_at`             DATETIME       YES            NULL                販売終了日時。NULLなら指定なし

  `shipping_note`           VARCHAR(500)   YES            NULL                送料の説明文。複雑な送料計算機能ではない

  `min_purchase_quantity`   INT UNSIGNED   NO             1                   最低購入数

  `purchase_step`           INT UNSIGNED   NO             1                   購入数量の刻み。通常は1

  `status`                  VARCHAR(20)    NO             `active`            `active` / `archived`

  `created_at`              DATETIME       NO             CURRENT_TIMESTAMP   登録日時

  `updated_at`              DATETIME       NO             CURRENT_TIMESTAMP   更新日時
  --------------------------------------------------------------------------------------------------------------------------------------

制約・インデックス案： -
`UNIQUE (store_id, product_id)`：同じ商品を同じ店舗に重複登録しない。 -
`UNIQUE (store_id, product_code)`：商品コードを店舗内で一意にする（NULLの扱いはMySQLの仕様を確認）。 -
`INDEX (store_id, is_published, display_order)`：店舗別公開商品一覧。 -
`INDEX (store_id, price)`：価格順。 -
`INDEX (store_id, stock_quantity)`：数量在庫順。 -
`INDEX (store_id, is_featured, display_order)`：推し商品と手動順。

在庫表示ルール： - `inventory_mode = 'quantity'` かつ
`stock_quantity = 0` なら、保存された `stock_status`
に関係なく「売り切れ」と表示し、購入不可とする。 -
`inventory_mode = 'quantity'`
かつ在庫数が1以上なら、`stock_quantity <= low_stock_threshold`
のとき「残り僅か」、それ以外は「在庫あり」とする。 -
`inventory_mode = 'status'` の場合は `stock_status` を表示する。 -
`is_published = 0` は販売サイトの公開商品一覧から除外する。 -
`is_purchasable = 0`、販売期間外、または売り切れの場合は注文を受け付けない。 -
DBの値だけでなく、注文受付時にPHP
API側でも在庫・公開状態・価格を再検証する。

ソートルール： - 推し商品を先頭に固定する場合は `is_featured DESC`
を最優先にする。 - その後に、要求された
`price ASC`、`stock_quantity DESC`、`display_order ASC`
などを適用する。 -
人気順は注文データが実装されるまでは正確に算出できない。管理画面と販売サイトの双方で、同じソート規則をAPI側に実装する。

### 3.6 `media_assets` --- アップロード画像

  ------------------------------------------------------------------------------------------------------------------------------
  カラム                  型             NULL           初期値              説明
  ----------------------- -------------- -------------- ------------------- ----------------------------------------------------
  `id`                    BIGINT         NO             自動採番            主キー
                          UNSIGNED                                          

  `store_id`              BIGINT         YES            NULL                店舗専用画像なら店舗ID。共通利用画像はNULL可
                          UNSIGNED                                          

  `storage_key`           VARCHAR(500)   NO             なし                画像保存先の相対キー。サーバー絶対パスは保存しない

  `original_filename`     VARCHAR(255)   YES            NULL                アップロード時の元ファイル名

  `mime_type`             VARCHAR(100)   NO             なし                実際に検証したMIMEタイプ

  `file_size_bytes`       BIGINT         NO             なし                ファイル容量
                          UNSIGNED                                          

  `alt_text`              VARCHAR(300)   YES            NULL                代替テキスト

  `uploaded_by_user_id`   BIGINT         YES            NULL                アップロード利用者
                          UNSIGNED                                          

  `created_at`            DATETIME       NO             CURRENT_TIMESTAMP   登録日時
  ------------------------------------------------------------------------------------------------------------------------------

アップロード時にファイルの実際の形式を検証し、PNG/JPEG以外を拒否する。拡張子だけでは判定しない。容量上限も設定する。`storage_key`
からURLを生成する方式にし、将来の保存先移行を容易にする。

### 3.7 `product_images` --- 商品画像と表示順

  ----------------------------------------------------------------------------------------------------
  カラム             型             NULL           初期値              説明
  ------------------ -------------- -------------- ------------------- -------------------------------
  `id`               BIGINT         NO             自動採番            主キー
                     UNSIGNED                                          

  `product_id`       BIGINT         NO             なし                `products.id`
                     UNSIGNED                                          

  `media_asset_id`   BIGINT         NO             なし                `media_assets.id`
                     UNSIGNED                                          

  `display_order`    TINYINT        NO             1                   1がメイン画像、2～7がサブ画像
                     UNSIGNED                                          

  `created_at`       DATETIME       NO             CURRENT_TIMESTAMP   登録日時
  ----------------------------------------------------------------------------------------------------

制約案： - `UNIQUE (product_id, display_order)` -
`UNIQUE (product_id, media_asset_id)` -
1商品につき最大7枚という上限は、DBの単純なCHECKだけに依存せず、PHP
APIで登録・並べ替え時に検証する。 -
画像を店舗別に差し替える要件が明確になった場合は、店舗商品IDを持つ
`store_product_images` を追加する。初期は共通商品画像を使う。

### 3.8 `categories` --- カテゴリ

  カラム            型                NULL   初期値              説明
  ----------------- ----------------- ------ ------------------- --------------------
  `id`              BIGINT UNSIGNED   NO     自動採番            主キー
  `store_id`        BIGINT UNSIGNED   NO     なし                店舗ごとのカテゴリ
  `name`            VARCHAR(100)      NO     なし                カテゴリ名
  `display_order`   INT               NO     0                   表示順
  `is_active`       TINYINT(1)        NO     1                   使用中か
  `created_at`      DATETIME          NO     CURRENT_TIMESTAMP   登録日時

制約案：`UNIQUE (store_id, name)`。将来カテゴリを複数階層にしたくなった場合は
`parent_id` を追加する。

### 3.9 `store_product_categories` --- 店舗商品とカテゴリの紐付け

  ---------------------------------------------------------------------------------------
  カラム               型             NULL           初期値         説明
  -------------------- -------------- -------------- -------------- ---------------------
  `store_product_id`   BIGINT         NO             なし           `store_products.id`
                       UNSIGNED                                     

  `category_id`        BIGINT         NO             なし           `categories.id`
                       UNSIGNED                                     
  ---------------------------------------------------------------------------------------

複合主キー：`(store_product_id, category_id)`。初期画面でカテゴリを1つしか設定しない場合でも、複数カテゴリに拡張しやすい。

### 3.10 `site_templates` --- 標準テンプレート定義

  カラム            型                NULL   初期値              説明
  ----------------- ----------------- ------ ------------------- --------------------------
  `id`              BIGINT UNSIGNED   NO     自動採番            主キー
  `template_code`   VARCHAR(50)       NO     なし                テンプレート識別子。一意
  `name`            VARCHAR(150)      NO     なし                管理画面表示名
  `version`         VARCHAR(30)       NO     `1.0`               テンプレートバージョン
  `is_active`       TINYINT(1)        NO     1                   新規選択可能か
  `created_at`      DATETIME          NO     CURRENT_TIMESTAMP   登録日時

このテーブルはテンプレートのプログラム本体を保存するものではない。実際のReactコードはサイト側に配置する。DBはどのテンプレートを使うか識別する。

### 3.11 `store_sites` --- 店舗の販売サイト設定

  ------------------------------------------------------------------------------------------------------
  カラム                  型             NULL           初期値              説明
  ----------------------- -------------- -------------- ------------------- ----------------------------
  `id`                    BIGINT         NO             自動採番            主キー
                          UNSIGNED                                          

  `store_id`              BIGINT         NO             なし                店舗ID
                          UNSIGNED                                          

  `site_code`             VARCHAR(50)    NO             なし                サイト識別子

  `site_name`             VARCHAR(200)   NO             なし                サイト名

  `site_url`              VARCHAR(500)   YES            NULL                公開URL

  `site_mode`             VARCHAR(20)    NO             `template`          `template` / `custom`

  `template_id`           BIGINT         YES            NULL                標準テンプレート使用時のID
                          UNSIGNED                                          

  `logo_media_asset_id`   BIGINT         YES            NULL                ロゴ画像
                          UNSIGNED                                          

  `primary_color`         CHAR(7)        YES            NULL                例：`#336633`

  `secondary_color`       CHAR(7)        YES            NULL                例：`#F5F0E6`

  `is_active`             TINYINT(1)     NO             1                   サイト設定が有効か

  `created_at`            DATETIME       NO             CURRENT_TIMESTAMP   登録日時

  `updated_at`            DATETIME       NO             CURRENT_TIMESTAMP   更新日時
  ------------------------------------------------------------------------------------------------------

制約案： - `UNIQUE (store_id, site_code)` -
初期は1店舗につき1サイトを想定する。複数ドメインや複数サイトが必要になった場合に備え、店舗テーブルにURLを直接固定するのではなく独立テーブルとする。 -
`site_mode = 'template'`
の場合にのみテンプレート用のコンテンツパーツを表示する。`custom`
は独自実装のフロントエンドが必要なAPIデータを利用する。

FTP接続パスワードなどの機密情報は、通常の設定カラムに平文で保存しない。初期設計ではFTP配備を手動運用とし、DBには公開URLなど必要な情報のみを保存する。

### 3.12 `store_site_sections` --- サイト表示パーツ

  -----------------------------------------------------------------------------------------------------------------------------------------
  カラム             型              NULL           初期値              説明
  ------------------ --------------- -------------- ------------------- -------------------------------------------------------------------
  `id`               BIGINT UNSIGNED NO             自動採番            主キー

  `store_site_id`    BIGINT UNSIGNED NO             なし                対象サイト

  `page_key`         VARCHAR(50)     NO             `home`              `home`、`about` などページ識別子

  `section_type`     VARCHAR(50)     NO             なし                `hero`、`text`、`image_text`、`banner`、`notice`、`shipping_info`
                                                                        など

  `title`            VARCHAR(200)    YES            NULL                見出し

  `body_text`        TEXT            YES            NULL                本文

  `media_asset_id`   BIGINT UNSIGNED YES            NULL                表示画像

  `link_url`         VARCHAR(1000)   YES            NULL                リンク先

  `link_label`       VARCHAR(100)    YES            NULL                リンク文言

  `display_order`    INT             NO             0                   パーツ順

  `is_published`     TINYINT(1)      NO             0                   公開状態

  `created_at`       DATETIME        NO             CURRENT_TIMESTAMP   登録日時

  `updated_at`       DATETIME        NO             CURRENT_TIMESTAMP   更新日時
  -----------------------------------------------------------------------------------------------------------------------------------------

制約・運用： - `section_type`
ごとに必要なフィールドをフロントエンドで定義する。自由なスクリプトを保存・実行しない。 -
メインビジュアルは `section_type = 'hero'`
として画像・見出し・本文・リンク先を設定できる。 - 画像は
`media_asset_id` を参照する。 -
これらのパーツは標準テンプレート向け。独自デザインサイトでは必要に応じて一部の基本設定や商品APIのみ利用する。

### 3.13 `orders` --- 注文（将来拡張）

  -----------------------------------------------------------------------------------------
  カラム               型             NULL           初期値              説明
  -------------------- -------------- -------------- ------------------- ------------------
  `id`                 BIGINT         NO             自動採番            主キー
                       UNSIGNED                                          

  `store_id`           BIGINT         NO             なし                注文を受けた店舗
                       UNSIGNED                                          

  `order_number`       VARCHAR(50)    NO             なし                店舗内注文番号

  `ordered_at`         DATETIME       NO             CURRENT_TIMESTAMP   注文日時

  `customer_name`      VARCHAR(150)   NO             なし                購入者名

  `customer_email`     VARCHAR(254)   NO             なし                メールアドレス

  `customer_phone`     VARCHAR(50)    YES            NULL                電話番号

  `shipping_address`   TEXT           YES            NULL                配送先住所

  `subtotal_amount`    INT UNSIGNED   NO             0                   商品小計

  `shipping_amount`    INT UNSIGNED   NO             0                   送料

  `total_amount`       INT UNSIGNED   NO             0                   合計

  `order_status`       VARCHAR(30)    NO             `received`          `received` /
                                                                         `confirmed` /
                                                                         `cancelled` /
                                                                         `fulfilled`

  `payment_status`     VARCHAR(30)    NO             `unpaid`            `unpaid` / `paid`
                                                                         / `refunded` /
                                                                         `not_required`

  `payment_method`     VARCHAR(30)    NO             `bank_transfer`     支払方法

  `note`               TEXT           YES            NULL                注文メモ

  `created_at`         DATETIME       NO             CURRENT_TIMESTAMP   登録日時

  `updated_at`         DATETIME       NO             CURRENT_TIMESTAMP   更新日時
  -----------------------------------------------------------------------------------------

制約案：`UNIQUE (store_id, order_number)`、`INDEX (store_id, ordered_at)`、`INDEX (store_id, order_status, payment_status)`。

購入者の個人情報を扱うため、アクセス権限、保持期間、削除・匿名化方針を別途定める。初期リリースで注文をDB保存しない場合、このテーブルは後日導入できる。

### 3.14 `order_items` --- 注文商品（将来拡張）

  -----------------------------------------------------------------------------------------------------------------------------------------
  カラム                    型             NULL           初期値         説明
  ------------------------- -------------- -------------- -------------- ------------------------------------------------------------------
  `id`                      BIGINT         NO             自動採番       主キー
                            UNSIGNED                                     

  `order_id`                BIGINT         NO             なし           `orders.id`
                            UNSIGNED                                     

  `store_product_id`        BIGINT         YES            NULL           現在の商品への参照。商品削除・整理後も履歴を保持できるようNULL可
                            UNSIGNED                                     

  `product_name_snapshot`   VARCHAR(200)   NO             なし           注文時点の商品名

  `unit_price_snapshot`     INT UNSIGNED   NO             0              注文時点の単価

  `quantity`                INT UNSIGNED   NO             1              注文数量

  `line_total`              INT UNSIGNED   NO             0              注文商品の小計
  -----------------------------------------------------------------------------------------------------------------------------------------

注文時の商品名・単価はスナップショットとして保存し、現在の商品情報を変更しても過去の注文が変わらないようにする。人気順は原則として販売確定条件を満たす注文から集計する。

### 3.15 `inventory_logs` --- 在庫変更履歴（将来拡張）

  -------------------------------------------------------------------------------------------------------------
  カラム                 型             NULL           初期値              説明
  ---------------------- -------------- -------------- ------------------- ------------------------------------
  `id`                   BIGINT         NO             自動採番            主キー
                         UNSIGNED                                          

  `store_product_id`     BIGINT         NO             なし                対象の店舗商品
                         UNSIGNED                                          

  `change_type`          VARCHAR(30)    NO             なし                `manual` / `order` / `cancel` /
                                                                           `restock` / `correction`

  `quantity_before`      INT UNSIGNED   NO             0                   変更前

  `quantity_after`       INT UNSIGNED   NO             0                   変更後

  `reason`               VARCHAR(500)   YES            NULL                変更理由

  `changed_by_user_id`   BIGINT         YES            NULL                変更利用者。システム処理ならNULL可
                         UNSIGNED                                          

  `created_at`           DATETIME       NO             CURRENT_TIMESTAMP   変更日時
  -------------------------------------------------------------------------------------------------------------

在庫を自動減算する場合は、注文処理と在庫更新をトランザクションで行い、同時注文による在庫のマイナスや二重減算を防ぐ。

------------------------------------------------------------------------

## 4. リレーション図

``` mermaid
erDiagram
    STORES ||--o{ STORE_USERS : has
    USERS ||--o{ STORE_USERS : assigned
    USERS ||--o{ PRODUCTS : creates
    PRODUCTS ||--o{ PRODUCT_IMAGES : has
    MEDIA_ASSETS ||--o{ PRODUCT_IMAGES : used_by
    STORES ||--o{ STORE_PRODUCTS : sells
    PRODUCTS ||--o{ STORE_PRODUCTS : listed_as
    STORE_PRODUCTS ||--o{ STORE_PRODUCT_CATEGORIES : categorized
    CATEGORIES ||--o{ STORE_PRODUCT_CATEGORIES : includes
    STORES ||--o{ CATEGORIES : owns
    STORES ||--o{ STORE_SITES : has
    SITE_TEMPLATES ||--o{ STORE_SITES : selected
    MEDIA_ASSETS ||--o{ STORE_SITES : logo
    STORE_SITES ||--o{ STORE_SITE_SECTIONS : contains
    MEDIA_ASSETS ||--o{ STORE_SITE_SECTIONS : displayed
    STORES ||--o{ ORDERS : receives
    ORDERS ||--|{ ORDER_ITEMS : contains
    STORE_PRODUCTS ||--o{ ORDER_ITEMS : ordered
    STORE_PRODUCTS ||--o{ INVENTORY_LOGS : tracks
```

------------------------------------------------------------------------

## 5. 主要な処理ルール

### 5.1 公開商品取得

販売サイトは、サイトを識別する情報をAPIに送る。APIはそのサイトに対応する店舗をサーバー側で解決し、次の条件を満たす商品だけを返す。

-   対象店舗の `store_products`
-   `is_published = 1`
-   `status = 'active'`
-   販売開始日時が設定されていれば開始済み
-   販売終了日時が設定されていれば終了前

公開商品一覧APIでは、管理用の内部情報や他店舗のデータを返さない。

### 5.2 購入受付

注文を受けるPHP側で、商品ID、店舗ID、数量、現在の価格、公開状態、購入可能状態、販売期間、在庫を再検証する。ブラウザから送られた合計金額を信用せず、サーバー側で再計算する。

数量管理モードで在庫数が0の場合は注文不可。自動在庫減算を導入する際は、注文確定の基準（注文受付時か入金確認時か）を決め、二重減算を防ぐ。

### 5.3 推し商品とソート

販売サイト・管理画面ともに、APIにソート条件を指定できるようにする。

-   手動順：`is_featured DESC, display_order ASC, id ASC`
-   価格順：`is_featured DESC, price ASC, id ASC`
-   在庫順：`is_featured DESC, stock_quantity DESC, id ASC`
-   人気順：販売確定済み注文の期間内販売数量を集計して降順。未実装期間は選択肢を無効にするか、未集計である旨を表示する。

`id`
を最後の並び替え条件に入れ、同値の商品の順番が不安定にならないようにする。テキスト在庫モードの商品は数値在庫順の対象外にするか、別グループとして扱う。

### 5.4 画像

-   商品画像は1商品最大7枚。`display_order = 1` がメイン画像。
-   PNG/JPEG以外を拒否し、実ファイル形式、容量、画像サイズをサーバー側で検証する。
-   画像ファイル名をそのまま保存パスに使用せず、推測されにくいファイル名またはIDを利用する。
-   `media_assets.storage_key`
    に保存先キーを記録し、公開URLはAPI側で生成する。
-   商品画像は初期案では商品共通。店舗別に画像を変える必要が生じたら、店舗商品に紐付く画像テーブルを追加する。

------------------------------------------------------------------------

## 6. MySQLテーブル作成SQL（初期版）

以下は、初期開発で使う中心テーブルのSQL案です。注文・在庫履歴は将来拡張用として別途追加します。レンタルサーバーのMySQLバージョンを確認してから実行してください。

``` sql
CREATE TABLE stores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  store_code VARCHAR(50) NOT NULL,
  name VARCHAR(150) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stores_store_code (store_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  login_id VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(150) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'store_staff',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_login_id (login_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE store_users (
  store_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  store_role VARCHAR(30) NOT NULL DEFAULT 'manager',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (store_id, user_id),
  CONSTRAINT fk_store_users_store FOREIGN KEY (store_id) REFERENCES stores(id),
  CONSTRAINT fk_store_users_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL,
  subtitle VARCHAR(200) NULL,
  description TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_products_name (name),
  CONSTRAINT fk_products_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE store_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  store_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(80) NULL,
  display_name VARCHAR(200) NULL,
  display_subtitle VARCHAR(200) NULL,
  display_description TEXT NULL,
  price INT UNSIGNED NOT NULL DEFAULT 0,
  inventory_mode VARCHAR(20) NOT NULL DEFAULT 'quantity',
  stock_quantity INT UNSIGNED NULL DEFAULT 0,
  inventory_unit VARCHAR(30) NULL,
  low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 3,
  stock_status VARCHAR(20) NOT NULL DEFAULT 'available',
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  is_purchasable TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  display_order INT NOT NULL DEFAULT 0,
  sale_start_at DATETIME NULL,
  sale_end_at DATETIME NULL,
  shipping_note VARCHAR(500) NULL,
  min_purchase_quantity INT UNSIGNED NOT NULL DEFAULT 1,
  purchase_step INT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_products_store_product (store_id, product_id),
  UNIQUE KEY uq_store_products_code (store_id, product_code),
  KEY idx_store_products_public_order (store_id, is_published, display_order),
  KEY idx_store_products_price (store_id, price),
  KEY idx_store_products_stock (store_id, stock_quantity),
  KEY idx_store_products_featured (store_id, is_featured, display_order),
  CONSTRAINT fk_store_products_store FOREIGN KEY (store_id) REFERENCES stores(id),
  CONSTRAINT fk_store_products_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT chk_store_products_inventory_mode CHECK (inventory_mode IN ('quantity', 'status')),
  CONSTRAINT chk_store_products_stock_status CHECK (stock_status IN ('available', 'low', 'sold_out')),
  CONSTRAINT chk_store_products_price CHECK (price >= 0),
  CONSTRAINT chk_store_products_min_qty CHECK (min_purchase_quantity >= 1),
  CONSTRAINT chk_store_products_step CHECK (purchase_step >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE media_assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  store_id BIGINT UNSIGNED NULL,
  storage_key VARCHAR(500) NOT NULL,
  original_filename VARCHAR(255) NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size_bytes BIGINT UNSIGNED NOT NULL,
  alt_text VARCHAR(300) NULL,
  uploaded_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_media_assets_store (store_id),
  CONSTRAINT fk_media_assets_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
  CONSTRAINT fk_media_assets_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  media_asset_id BIGINT UNSIGNED NOT NULL,
  display_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_images_order (product_id, display_order),
  UNIQUE KEY uq_product_images_asset (product_id, media_asset_id),
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_images_asset FOREIGN KEY (media_asset_id) REFERENCES media_assets(id),
  CONSTRAINT chk_product_images_order CHECK (display_order BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  store_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_store_name (store_id, name),
  CONSTRAINT fk_categories_store FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE store_product_categories (
  store_product_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (store_product_id, category_id),
  CONSTRAINT fk_spc_store_product FOREIGN KEY (store_product_id) REFERENCES store_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_spc_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE site_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_code VARCHAR(50) NOT NULL,
  name VARCHAR(150) NOT NULL,
  version VARCHAR(30) NOT NULL DEFAULT '1.0',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_site_templates_code (template_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE store_sites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  store_id BIGINT UNSIGNED NOT NULL,
  site_code VARCHAR(50) NOT NULL,
  site_name VARCHAR(200) NOT NULL,
  site_url VARCHAR(500) NULL,
  site_mode VARCHAR(20) NOT NULL DEFAULT 'template',
  template_id BIGINT UNSIGNED NULL,
  logo_media_asset_id BIGINT UNSIGNED NULL,
  primary_color CHAR(7) NULL,
  secondary_color CHAR(7) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_sites_code (store_id, site_code),
  KEY idx_store_sites_store (store_id),
  CONSTRAINT fk_store_sites_store FOREIGN KEY (store_id) REFERENCES stores(id),
  CONSTRAINT fk_store_sites_template FOREIGN KEY (template_id) REFERENCES site_templates(id) ON DELETE SET NULL,
  CONSTRAINT fk_store_sites_logo FOREIGN KEY (logo_media_asset_id) REFERENCES media_assets(id) ON DELETE SET NULL,
  CONSTRAINT chk_store_sites_mode CHECK (site_mode IN ('template', 'custom'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE store_site_sections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  store_site_id BIGINT UNSIGNED NOT NULL,
  page_key VARCHAR(50) NOT NULL DEFAULT 'home',
  section_type VARCHAR(50) NOT NULL,
  title VARCHAR(200) NULL,
  body_text TEXT NULL,
  media_asset_id BIGINT UNSIGNED NULL,
  link_url VARCHAR(1000) NULL,
  link_label VARCHAR(100) NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_site_sections_order (store_site_id, page_key, is_published, display_order),
  CONSTRAINT fk_site_sections_site FOREIGN KEY (store_site_id) REFERENCES store_sites(id) ON DELETE CASCADE,
  CONSTRAINT fk_site_sections_asset FOREIGN KEY (media_asset_id) REFERENCES media_assets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> 注：このSQLは初期コアテーブルを作るための案です。注文テーブル・注文商品テーブル・在庫履歴は、注文保存と在庫管理をどの段階で導入するか確定してから追加します。CHECK制約の実効性はMySQLのバージョンに依存するため、API側でも必ず値を検証します。

------------------------------------------------------------------------

## 7. サンプルデータと代表的な取得例

### 7.1 仮想店舗の登録

``` sql
INSERT INTO stores (store_code, name) VALUES
('farm_a', '仮想店舗A'),
('farm_b', '仮想店舗B');
```

開発時は複数店舗を作り、Aの利用者がBの商品を編集・閲覧できないこと、Aの商品がBの公開APIに表示されないことをテストする。

### 7.2 商品と店舗販売設定の登録

``` sql
INSERT INTO products (name, subtitle, description)
VALUES ('とうもろこし', 'ドルチェドリーム', '黄色と白のコントラストが特徴です。');

SET @product_id = LAST_INSERT_ID();

INSERT INTO store_products
(store_id, product_id, product_code, price, inventory_mode, stock_quantity,
 inventory_unit, low_stock_threshold, is_published, is_purchasable, display_order)
VALUES
(1, @product_id, 'CORN-001', 210, 'quantity', 30, '本', 5, 1, 1, 10);
```

実際の運用では、`store_id = 1`
のような固定値をプログラムに埋め込まず、選択中の店舗IDと権限から決定する。

### 7.3 公開商品一覧（手動順・推し商品優先）

``` sql
SELECT
  sp.id AS store_product_id,
  p.id AS product_id,
  COALESCE(sp.display_name, p.name) AS name,
  COALESCE(sp.display_subtitle, p.subtitle) AS subtitle,
  COALESCE(sp.display_description, p.description) AS description,
  sp.price,
  sp.inventory_mode,
  sp.stock_quantity,
  sp.inventory_unit,
  sp.low_stock_threshold,
  sp.stock_status,
  sp.is_featured,
  sp.display_order
FROM store_products sp
JOIN products p ON p.id = sp.product_id
WHERE sp.store_id = :store_id
  AND sp.is_published = 1
  AND sp.status = 'active'
ORDER BY sp.is_featured DESC, sp.display_order ASC, sp.id ASC;
```

`:store_id`
はPHPのPDOプリペアドステートメントでバインドする。販売開始・終了日時の条件もAPI側で加える。

### 7.4 画像一覧

``` sql
SELECT
  pi.display_order,
  ma.storage_key,
  ma.mime_type,
  ma.alt_text
FROM product_images pi
JOIN media_assets ma ON ma.id = pi.media_asset_id
WHERE pi.product_id = :product_id
ORDER BY pi.display_order ASC;
```

公開APIでは `storage_key`
をそのまま公開するか、API側で公開URLに変換するかを統一する。非公開ファイルのパスが外部に漏れないようにする。

------------------------------------------------------------------------

## 8. 初期リリースと将来拡張の切り分け

### 初期リリースで実装する

-   `stores`、`users`、`store_users`
-   `products`、`store_products`
-   `media_assets`、`product_images`
-   `categories`、`store_product_categories`
-   `site_templates`、`store_sites`、`store_site_sections`
-   商品登録・編集・非公開化
-   画像アップロード（最大7枚、PNG/JPEG検証）
-   店舗ごとの公開商品API
-   推し商品、手動順、価格順、在庫順
-   店舗単位の認証・認可
-   共通テンプレートサイトのサイト設定編集

### 将来拡張。ただし人気順を初期から使うなら前倒しする

-   `orders`、`order_items`
-   入金状態と注文状態の管理
-   人気順の販売実績集計
-   `inventory_logs`
-   在庫の自動減算、注文取消時の在庫戻し
-   店舗別の商品画像上書き
-   販売期間の自動制御、複雑な送料計算
-   テンプレートの追加・バージョン管理機能

------------------------------------------------------------------------

## 9. 未決定事項と推奨案

  --------------------------------------------------------------------------------------------------------------------------------------------------------------
  項目                    未決定事項                             推奨案
  ----------------------- -------------------------------------- -----------------------------------------------------------------------------------------------
  税                      価格は税込か税抜か                     現在の表示に合わせ、税込価格を整数で保存する。税率や軽減税率を商品ごとにどう扱うかは別途確認

  商品の共有              同じ商品情報を複数店舗で共有するか     初期は店舗ごとに商品登録。将来共有する場合も販売設定・在庫は店舗別

  画像                    同じ商品を店舗ごとに違う画像にするか   初期は商品画像共通。必要なら店舗商品画像テーブルを追加

  注文DB                  初期から注文をDB保存するか             人気順を初期実装するなら注文DBを前倒し。そうでなければ既存メールフォームを維持し、後日追加

  人気順                  注文数か、入金済み販売数か             銀行振込の未入金注文を除外できるよう、販売確定の基準を決める

  在庫減算                注文受付時か、入金確認時か             生鮮品で過剰受注を防ぐ必要があるなら、仮押さえ・期限切れ・入金確認を含む運用設計が必要

  送料                    表示文のみか、送料計算も必要か         初期は商品・店舗の送料説明文。複雑な送料計算は別要件として設計

  サイト数                1店舗につき販売サイトは1つか           `store_sites` を分けて将来複数サイトに対応できる形とする

  API識別                 公開サイトがどの店舗か判定する方法     サイトURL・ドメインと店舗の紐付けをサーバー側で管理。ドメイン情報だけを秘密鍵の代わりにしない

  MySQL環境               利用可能なバージョン                   本番サーバーでバージョン・外部キー・CHECK制約・文字コードを確認してからSQLを確定

  商品コード              店舗内で必須か任意か                   初期は任意。設定する場合は店舗内で一意にする

  公開と購入              非公開・購入停止時の見せ方             非公開は一覧から除外、購入停止は必要に応じて商品ページを表示したまま購入ボタンを無効化
  --------------------------------------------------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------

## 10. 設計レビュー時の重点確認

1.  店舗担当者が他店舗の商品の価格・在庫・注文を変更できないか。
2.  店舗Aの公開APIから店舗Bの商品や非公開商品が返らないか。
3.  数量管理商品で在庫数が0なら、常に売り切れ表示・購入不可になるか。
4.  推し商品が先頭に固定され、その後のソート条件が正しく適用されるか。
5.  画像7枚制限が登録時・並び替え時の双方で守られるか。
6.  注文時にクライアントの価格を信用せず、サーバー側で金額を再計算できるか。
7.  商品価格や名称を変更しても、注文履歴のスナップショットが変化しないか。
8.  非公開化した商品が販売サイトの公開APIから除外されるか。
9.  サイト設定を変更しても、独自デザインサイトの表示が意図せず変わらないか。
10. 画像保存先やテンプレートの変更が将来の移行を妨げないか。

------------------------------------------------------------------------

## 11. 次の作業

1.  未決定事項を確認し、初期リリース範囲を確定する。
2.  本番サーバーのMySQLバージョンと利用条件を確認する。
3.  テーブル定義とSQLをレビューして確定する。
4.  開発DBに仮想店舗を複数作成し、店舗分離をテストする。
5.  PHP APIの実装を開始する。
6.  商品1件の登録・取得・編集を通し、React管理画面からDBの変更を確認する。
7.  その後、商品画像、サイト設定、通販サイトとの連携へ進む。
