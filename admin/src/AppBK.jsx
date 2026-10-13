import { Fragment, useEffect, useState } from 'react';
import './App.css';

function App() {
  // 商品一覧
  const [products, setProducts] = useState([]);

  // 通信状態
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  // 新規登録フォーム（DB連携は今後実装）
  const [name, setName] = useState('');
  const [price, setPrice] = useState('');
  const [stock, setStock] = useState('');

  // 編集フォーム
  const [editingId, setEditingId] = useState(null);
  const [editName, setEditName] = useState('');
  const [editPrice, setEditPrice] = useState('');
  const [editStock, setEditStock] = useState('');
  const [editPublished, setEditPublished] = useState(false);
  const [editError, setEditError] = useState('');

  // PHP側で設定したCSRFトークンを取得
  const csrfToken =
    document.querySelector('meta[name="csrf-token"]')?.content || '';

  // DBから商品一覧を取得
  async function fetchProducts() {
    try {
      setLoading(true);
      setError('');

      const response = await fetch('./api/products.php', {
        method: 'GET',
        credentials: 'same-origin',
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(
          data.message || '商品データを取得できませんでした。'
        );
      }

      const loadedProducts = data.products.map((product) => ({
        id: Number(product.id),
        name: product.name,
        price: Number(product.price),
        stock: Number(product.stock_quantity),
        published:
          product.is_published === true ||
          Number(product.is_published) === 1,
      }));

      setProducts(loadedProducts);
    } catch (err) {
      console.error('商品一覧の取得に失敗しました:', err);
      setError(err.message || '商品データを取得できませんでした。');
    } finally {
      setLoading(false);
    }
  }

  // 初回表示時に商品一覧を読み込む
  useEffect(() => {
    fetchProducts();
  }, []);

  // 新規登録は今後実装
  function handleSubmit(event) {
    event.preventDefault();
    alert('商品登録は次の工程で実装します。');
  }

  // 公開・非公開切替は今後実装
  function togglePublished() {
    alert('公開状態の切替は次の工程で実装します。');
  }

  // 削除は今後実装
  function deleteProduct() {
    alert('商品削除は次の工程で実装します。');
  }

  // 編集フォームを開く
  function startEditing(product) {
    if (editingId === product.id) {
      setEditingId(null);
      setEditError('');
      return;
    }

    setEditingId(product.id);
    setEditName(product.name);
    setEditPrice(String(product.price));
    setEditStock(String(product.stock));
    setEditPublished(product.published);
    setEditError('');
  }

  // 編集内容をDBに保存
  async function handleEditSubmit(event, productId) {
    event.preventDefault();
    setEditError('');

    if (!csrfToken) {
      setEditError(
        'セキュリティトークンを取得できません。画面を再読み込みしてください。'
      );
      return;
    }

    const numericPrice = Number(editPrice);
    const numericStock = Number(editStock);

    if (!editName.trim()) {
      setEditError('商品名を入力してください。');
      return;
    }

    if (
      editPrice.trim() === '' ||
      !Number.isInteger(numericPrice) ||
      numericPrice < 0
    ) {
      setEditError('価格は0以上の整数で入力してください。');
      return;
    }

    if (
      editStock.trim() === '' ||
      !Number.isInteger(numericStock) ||
      numericStock < 0
    ) {
      setEditError('在庫数は0以上の整数で入力してください。');
      return;
    }

    try {
      setSaving(true);

      const response = await fetch('./api/product_update.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify({
          id: productId,
          name: editName.trim(),
          price: numericPrice,
          stock_quantity: numericStock,
          is_published: editPublished,
        }),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(
          data.message || '商品の更新に失敗しました。'
        );
      }

      // 画面上の商品一覧にも保存内容を反映
      setProducts((currentProducts) =>
        currentProducts.map((product) =>
          product.id === productId
            ? {
                ...product,
                name: editName.trim(),
                price: numericPrice,
                stock: numericStock,
                published: editPublished,
              }
            : product
        )
      );

      setEditingId(null);
      setEditError('');
      alert('商品を更新しました。');
    } catch (err) {
      console.error('商品更新に失敗しました:', err);
      setEditError(err.message || '商品の更新に失敗しました。');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="admin-app">
      <header className="common-header">
        <a className="common-brand" href="./index.php">
          <span className="common-brand-mark" aria-hidden="true">
            F
          </span>
          <span>
            <span className="common-brand-name">Farm Shop</span>
            <span className="common-brand-caption">管理システム</span>
          </span>
        </a>

        <nav className="common-nav" aria-label="メインメニュー">
          <a href="./index.php">店舗情報に戻る</a>
          <a href="./logout.php" className="common-logout-link">
            ログアウト
          </a>
        </nav>
      </header>

      <main className="common-main main-content">
        <div className="page-heading">
          <div>
            <p className="eyebrow">PRODUCT MANAGEMENT</p>
            <h1>商品管理</h1>
            <p className="description">
              商品の登録・編集・公開状態を管理します。
            </p>
          </div>
          <span className="product-count">
            登録商品：{products.length} 件
          </span>
        </div>

        <section className="panel">
          <h2>新しい商品を登録</h2>

          <form onSubmit={handleSubmit} className="product-form">
            <label>
              商品名
              <input
                type="text"
                value={name}
                onChange={(event) => setName(event.target.value)}
                placeholder="例：季節の野菜セット"
                required
              />
            </label>

            <div className="form-row">
              <label>
                価格（円）
                <input
                  type="number"
                  min="0"
                  step="1"
                  value={price}
                  onChange={(event) => setPrice(event.target.value)}
                  placeholder="1800"
                  required
                />
              </label>

              <label>
                在庫数
                <input
                  type="number"
                  min="0"
                  step="1"
                  value={stock}
                  onChange={(event) => setStock(event.target.value)}
                  placeholder="20"
                  required
                />
              </label>
            </div>

            <button type="submit" className="primary-button">
              ＋ 商品を登録
            </button>
          </form>

          <p className="form-note">
            ※ 商品登録は現在準備中です。入力した内容はまだ保存されません。
          </p>
        </section>

        <section className="panel">
          <div className="section-heading">
            <h2>商品一覧</h2>
            <span>{products.length} 件</span>
          </div>

          {loading && (
            <p role="status">商品データを読み込んでいます...</p>
          )}

          {!loading && error && (
            <div role="alert">
              <p>{error}</p>
              <button
                type="button"
                className="secondary-button"
                onClick={fetchProducts}
              >
                再読み込み
              </button>
            </div>
          )}

          {!loading && !error && (
            <div className="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>商品名</th>
                    <th>価格</th>
                    <th>在庫</th>
                    <th>公開状態</th>
                    <th>操作</th>
                  </tr>
                </thead>

                <tbody>
                  {products.map((product) => (
                    <Fragment key={product.id}>
                      <tr>
                        <td className="product-name">
                          {product.name}
                        </td>

                        <td>
                          {product.price.toLocaleString('ja-JP')} 円
                        </td>

                        <td>
                          {product.stock === 0 ? (
                            <span className="sold-out">在庫なし</span>
                          ) : (
                            `${product.stock} 個`
                          )}
                        </td>

                        <td>
                          <span
                            className={
                              product.published
                                ? 'status published'
                                : 'status unpublished'
                            }
                          >
                            {product.published ? '公開中' : '非公開'}
                          </span>
                        </td>

                        <td>
                          <div className="action-buttons">
                            <button
                              type="button"
                              className="secondary-button"
                              onClick={() => startEditing(product)}
                            >
                              {editingId === product.id ? '編集を閉じる' : '編集'}
                            </button>

                            <button
                              type="button"
                              className="secondary-button"
                              onClick={() => togglePublished(product.id)}
                            >
                              {product.published
                                ? '非公開にする'
                                : '公開する'}
                            </button>

                            <button
                              type="button"
                              className="delete-button"
                              onClick={() => deleteProduct(product.id)}
                            >
                              削除
                            </button>
                          </div>
                        </td>
                      </tr>

                      {editingId === product.id && (
                        <tr className="edit-table-row">
                          <td colSpan="5">
                            <div className="edit-panel">
                              <h3>商品を編集：{product.name}</h3>

                              <form
                                className="product-form"
                                onSubmit={(event) =>
                                  handleEditSubmit(event, product.id)
                                }
                              >
                                <label>
                                  商品名
                                  <input
                                    type="text"
                                    value={editName}
                                    onChange={(event) =>
                                      setEditName(event.target.value)
                                    }
                                    maxLength="200"
                                    required
                                  />
                                </label>

                                <div className="form-row">
                                  <label>
                                    価格（円）
                                    <input
                                      type="number"
                                      min="0"
                                      step="1"
                                      value={editPrice}
                                      onChange={(event) =>
                                        setEditPrice(event.target.value)
                                      }
                                      required
                                    />
                                  </label>

                                  <label>
                                    在庫数
                                    <input
                                      type="number"
                                      min="0"
                                      step="1"
                                      value={editStock}
                                      onChange={(event) =>
                                        setEditStock(event.target.value)
                                      }
                                      required
                                    />
                                  </label>
                                </div>

                                <label className="published-edit-option">
                                  <input
                                    type="checkbox"
                                    checked={editPublished}
                                    onChange={(event) =>
                                      setEditPublished(event.target.checked)
                                    }
                                  />
                                  商品を公開する
                                </label>

                                {editError && (
                                  <p className="edit-error" role="alert">
                                    {editError}
                                  </p>
                                )}

                                <div className="edit-form-actions">
                                  <button
                                    type="submit"
                                    className="primary-button"
                                    disabled={saving}
                                  >
                                    {saving ? '保存中...' : '変更を保存'}
                                  </button>

                                  <button
                                    type="button"
                                    className="secondary-button"
                                    disabled={saving}
                                    onClick={() => {
                                      setEditingId(null);
                                      setEditError('');
                                    }}
                                  >
                                    キャンセル
                                  </button>
                                </div>
                              </form>
                            </div>
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  ))}

                  {products.length === 0 && (
                    <tr>
                      <td colSpan="5" className="empty-message">
                        商品が登録されていません。
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </main>

      <footer className="common-footer product-footer-links">
        <a href="./index.php">管理画面トップへ戻る</a>
        <a href="./logout.php">ログアウト</a>
      </footer>
    </div>
  );
}

export default App;