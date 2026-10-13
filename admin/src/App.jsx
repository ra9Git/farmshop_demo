import { Fragment, useEffect, useState } from 'react';
import './App.css';
import './App.image-management.css';

const MAX_IMAGES = 7;

function App() {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  const [name, setName] = useState('');
  const [price, setPrice] = useState('');
  const [stock, setStock] = useState('');

  const [editingId, setEditingId] = useState(null);
  const [editName, setEditName] = useState('');
  const [editPrice, setEditPrice] = useState('');
  const [editStock, setEditStock] = useState('');
  const [editPublished, setEditPublished] = useState(false);
  const [editError, setEditError] = useState('');

  const [imagesByProduct, setImagesByProduct] = useState({});
  const [imagesLoading, setImagesLoading] = useState(false);
  const [imageBusy, setImageBusy] = useState(false);
  const [imageError, setImageError] = useState('');
  const [imageMessage, setImageMessage] = useState('');
  const [selectedFiles, setSelectedFiles] = useState([]);
  const [previewUrls, setPreviewUrls] = useState([]);

  const csrfToken =
    document.querySelector('meta[name="csrf-token"]')?.content || '';

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
        throw new Error(data.message || '商品データを取得できませんでした。');
      }
      setProducts(data.products.map((product) => ({
        id: Number(product.id),
        name: product.name,
        price: Number(product.price),
        stock: Number(product.stock_quantity),
        published: product.is_published === true || Number(product.is_published) === 1,
      })));
    } catch (err) {
      console.error('商品一覧の取得に失敗しました:', err);
      setError(err.message || '商品データを取得できませんでした。');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchProducts();
  }, []);

  // 商品登録は別工程で実装予定
  function handleSubmit(event) {
    event.preventDefault();
    alert('商品登録は次の工程で実装します。');
  }

  function togglePublished() {
    alert('公開状態の切替は次の工程で実装します。');
  }

  function deleteProduct() {
    alert('商品削除は次の工程で実装します。');
  }

  async function fetchProductImages(productId) {
    setImagesLoading(true);
    setImageError('');
    try {
      const response = await fetch(
        `./api/product_images.php?product_id=${encodeURIComponent(productId)}`,
        { method: 'GET', credentials: 'same-origin' }
      );
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || '商品画像を取得できませんでした。');
      }
      setImagesByProduct((current) => ({
        ...current,
        [productId]: data.images,
      }));
    } catch (err) {
      console.error('商品画像の取得に失敗しました:', err);
      setImageError(err.message || '商品画像を取得できませんでした。');
    } finally {
      setImagesLoading(false);
    }
  }

  function clearSelectedFiles() {
    previewUrls.forEach((url) => URL.revokeObjectURL(url));
    setSelectedFiles([]);
    setPreviewUrls([]);
  }

  function handleFileChange(event) {
    const files = Array.from(event.target.files || []);
    clearSelectedFiles();
    setImageError('');
    setImageMessage('');

    const currentImages = imagesByProduct[editingId] || [];
    const availableSlots = MAX_IMAGES - currentImages.length;
    if (files.length > availableSlots) {
      setImageError(`この商品に追加できる画像はあと${availableSlots}枚です。最大${MAX_IMAGES}枚まで登録できます。`);
      event.target.value = '';
      return;
    }

    const invalid = files.find((file) =>
      !['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024
    );
    if (invalid) {
      setImageError('画像はJPEGまたはPNG形式、1ファイル5MB以下にしてください。');
      event.target.value = '';
      return;
    }

    setSelectedFiles(files);
    setPreviewUrls(files.map((file) => URL.createObjectURL(file)));
    event.target.value = '';
  }

  async function uploadSelectedImages() {
    if (!editingId || selectedFiles.length === 0) return;
    if (!csrfToken) {
      setImageError('セキュリティトークンを取得できません。画面を再読み込みしてください。');
      return;
    }

    setImageBusy(true);
    setImageError('');
    setImageMessage('');
    let uploadedCount = 0;

    try {
      // 1枚ずつ送信することで、各画像の表示順をサーバー側で安全に決定する
      for (const file of selectedFiles) {
        const formData = new FormData();
        formData.append('product_id', String(editingId));
        formData.append('image', file);

        const response = await fetch('./api/product_image_upload.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': csrfToken },
          body: formData,
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
          throw new Error(data.message || '画像のアップロードに失敗しました。');
        }
        uploadedCount += 1;
      }

      clearSelectedFiles();
      await fetchProductImages(editingId);
      setImageMessage(`${uploadedCount}枚の画像を登録しました。`);
    } catch (err) {
      console.error('商品画像のアップロードに失敗しました:', err);
      if (uploadedCount > 0) {
        await fetchProductImages(editingId);
      }
      setImageError(
        uploadedCount > 0
          ? `${uploadedCount}枚を登録しましたが、残りの画像でエラーが発生しました。${err.message}`
          : (err.message || '画像のアップロードに失敗しました。')
      );
    } finally {
      setImageBusy(false);
    }
  }

  async function deleteProductImage(image) {
    if (!editingId) return;
    if (!window.confirm('この画像を削除しますか？')) return;
    if (!csrfToken) {
      setImageError('セキュリティトークンを取得できません。画面を再読み込みしてください。');
      return;
    }

    setImageBusy(true);
    setImageError('');
    setImageMessage('');
    try {
      const response = await fetch('./api/product_image_delete.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify({
          product_id: editingId,
          product_image_id: image.id,
        }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || '画像の削除に失敗しました。');
      }
      await fetchProductImages(editingId);
      setImageMessage('画像を削除しました。');
    } catch (err) {
      console.error('商品画像の削除に失敗しました:', err);
      setImageError(err.message || '画像の削除に失敗しました。');
    } finally {
      setImageBusy(false);
    }
  }

  function startEditing(product) {
    if (editingId === product.id) {
      setEditingId(null);
      setEditError('');
      setImageError('');
      setImageMessage('');
      clearSelectedFiles();
      return;
    }

    clearSelectedFiles();
    setEditingId(product.id);
    setEditName(product.name);
    setEditPrice(String(product.price));
    setEditStock(String(product.stock));
    setEditPublished(product.published);
    setEditError('');
    setImageError('');
    setImageMessage('');
    fetchProductImages(product.id);
  }

  async function handleEditSubmit(event, productId) {
    event.preventDefault();
    setEditError('');
    if (!csrfToken) {
      setEditError('セキュリティトークンを取得できません。画面を再読み込みしてください。');
      return;
    }

    const numericPrice = Number(editPrice);
    const numericStock = Number(editStock);
    if (!editName.trim()) {
      setEditError('商品名を入力してください。');
      return;
    }
    if (editPrice.trim() === '' || !Number.isInteger(numericPrice) || numericPrice < 0) {
      setEditError('価格は0以上の整数で入力してください。');
      return;
    }
    if (editStock.trim() === '' || !Number.isInteger(numericStock) || numericStock < 0) {
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
        throw new Error(data.message || '商品の更新に失敗しました。');
      }

      setProducts((current) => current.map((product) =>
        product.id === productId
          ? {
              ...product,
              name: editName.trim(),
              price: numericPrice,
              stock: numericStock,
              published: editPublished,
            }
          : product
      ));
      setEditingId(null);
      setEditError('');
      clearSelectedFiles();
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
          <span className="common-brand-mark" aria-hidden="true">F</span>
          <span>
            <span className="common-brand-name">Farm Shop</span>
            <span className="common-brand-caption">管理システム</span>
          </span>
        </a>
        <nav className="common-nav" aria-label="メインメニュー">
          <a href="./index.php">店舗情報に戻る</a>
          <a href="./logout.php" className="common-logout-link">ログアウト</a>
        </nav>
      </header>

      <main className="common-main main-content">
        <div className="page-heading">
          <div>
            <p className="eyebrow">PRODUCT MANAGEMENT</p>
            <h1>商品管理</h1>
            <p className="description">商品の登録・編集・公開状態を管理します。</p>
          </div>
          <span className="product-count">登録商品：{products.length} 件</span>
        </div>

        <section className="panel">
          <h2>新しい商品を登録</h2>
          <form onSubmit={handleSubmit} className="product-form">
            <label>
              商品名
              <input type="text" value={name} onChange={(event) => setName(event.target.value)}
                placeholder="例：季節の野菜セット" required />
            </label>
            <div className="form-row">
              <label>
                価格（円）
                <input type="number" min="0" step="1" value={price}
                  onChange={(event) => setPrice(event.target.value)} placeholder="1800" required />
              </label>
              <label>
                在庫数
                <input type="number" min="0" step="1" value={stock}
                  onChange={(event) => setStock(event.target.value)} placeholder="20" required />
              </label>
            </div>
            <button type="submit" className="primary-button">＋ 商品を登録</button>
          </form>
          <p className="form-note">※ 商品登録は現在準備中です。入力した内容はまだ保存されません。</p>
        </section>

        <section className="panel">
          <div className="section-heading">
            <h2>商品一覧</h2>
            <span>{products.length} 件</span>
          </div>
          {loading && <p role="status">商品データを読み込んでいます...</p>}
          {!loading && error && (
            <div role="alert">
              <p>{error}</p>
              <button type="button" className="secondary-button" onClick={fetchProducts}>再読み込み</button>
            </div>
          )}
          {!loading && !error && (
            <div className="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>商品名</th><th>価格</th><th>在庫</th><th>公開状態</th><th>操作</th>
                  </tr>
                </thead>
                <tbody>
                  {products.map((product) => (
                    <Fragment key={product.id}>
                      <tr>
                        <td className="product-name">{product.name}</td>
                        <td>{product.price.toLocaleString('ja-JP')} 円</td>
                        <td>{product.stock === 0 ? <span className="sold-out">在庫なし</span> : `${product.stock} 個`}</td>
                        <td>
                          <span className={product.published ? 'status published' : 'status unpublished'}>
                            {product.published ? '公開中' : '非公開'}
                          </span>
                        </td>
                        <td>
                          <div className="action-buttons">
                            <button type="button" className="secondary-button" onClick={() => startEditing(product)}>
                              {editingId === product.id ? '編集を閉じる' : '編集'}
                            </button>
                            <button type="button" className="secondary-button" onClick={() => togglePublished(product.id)}>
                              {product.published ? '非公開にする' : '公開する'}
                            </button>
                            <button type="button" className="delete-button" onClick={() => deleteProduct(product.id)}>削除</button>
                          </div>
                        </td>
                      </tr>
                      {editingId === product.id && (
                        <tr className="edit-table-row">
                          <td colSpan="5">
                            <div className="edit-panel">
                              <h3>商品を編集：{product.name}</h3>
                              <form className="product-form" onSubmit={(event) => handleEditSubmit(event, product.id)}>
                                <label>
                                  商品名
                                  <input type="text" value={editName} onChange={(event) => setEditName(event.target.value)}
                                    maxLength="200" required />
                                </label>
                                <div className="form-row">
                                  <label>
                                    価格（円）
                                    <input type="number" min="0" step="1" value={editPrice}
                                      onChange={(event) => setEditPrice(event.target.value)} required />
                                  </label>
                                  <label>
                                    在庫数
                                    <input type="number" min="0" step="1" value={editStock}
                                      onChange={(event) => setEditStock(event.target.value)} required />
                                  </label>
                                </div>
                                <label className="published-edit-option">
                                  <input type="checkbox" checked={editPublished}
                                    onChange={(event) => setEditPublished(event.target.checked)} />
                                  商品を公開する
                                </label>
                                {editError && <p className="edit-error" role="alert">{editError}</p>}
                                <div className="edit-form-actions">
                                  <button type="submit" className="primary-button" disabled={saving}>
                                    {saving ? '保存中...' : '変更を保存'}
                                  </button>
                                  <button type="button" className="secondary-button" disabled={saving}
                                    onClick={() => { setEditingId(null); setEditError(''); clearSelectedFiles(); }}>
                                    キャンセル
                                  </button>
                                </div>
                              </form>

                              <section className="product-image-manager" aria-label="商品画像管理">
                                <div className="image-manager-heading">
                                  <div>
                                    <h4>商品画像</h4>
                                    <p>1枚目がメイン画像です。JPEG / PNG、1枚5MB以下、最大7枚。</p>
                                  </div>
                                  <span className="image-count">
                                    {(imagesByProduct[product.id] || []).length} / {MAX_IMAGES} 枚
                                  </span>
                                </div>

                                {imagesLoading && <p role="status">画像を読み込んでいます...</p>}
                                {!imagesLoading && (
                                  <div className="product-image-grid">
                                    {(imagesByProduct[product.id] || []).map((image) => (
                                      <div className="product-image-card" key={image.id}>
                                        <img src={image.url} alt={image.alt_text || product.name} />
                                        <div className="product-image-card-footer">
                                          <span>{image.display_order === 1 ? 'メイン画像' : `画像 ${image.display_order}`}</span>
                                          <button type="button" className="delete-button image-delete-button"
                                            disabled={imageBusy} onClick={() => deleteProductImage(image)}>
                                            削除
                                          </button>
                                        </div>
                                      </div>
                                    ))}
                                    {(imagesByProduct[product.id] || []).length === 0 && (
                                      <p className="image-empty">登録済みの画像はありません。</p>
                                    )}
                                  </div>
                                )}

                                {(imagesByProduct[product.id] || []).length < MAX_IMAGES && (
                                  <div className="image-upload-area">
                                    <label className="image-file-label">
                                      追加する画像を選択
                                      <input type="file" accept="image/jpeg,image/png" multiple
                                        onChange={handleFileChange}
                                        disabled={imageBusy || imagesLoading} />
                                    </label>
                                    <span className="image-upload-help">
                                      あと{MAX_IMAGES - (imagesByProduct[product.id] || []).length}枚登録できます
                                    </span>
                                  </div>
                                )}

                                {previewUrls.length > 0 && (
                                  <div className="image-preview-area">
                                    <h5>アップロード前のプレビュー</h5>
                                    <div className="product-image-grid">
                                      {previewUrls.map((url, index) => (
                                        <div className="product-image-card" key={`${url}-${index}`}>
                                          <img src={url} alt={`選択画像 ${index + 1}`} />
                                          <div className="product-image-card-footer">
                                            <span>{selectedFiles[index]?.name}</span>
                                          </div>
                                        </div>
                                      ))}
                                    </div>
                                    <div className="edit-form-actions">
                                      <button type="button" className="primary-button"
                                        disabled={imageBusy} onClick={uploadSelectedImages}>
                                        {imageBusy ? 'アップロード中...' : `${selectedFiles.length}枚をアップロード`}
                                      </button>
                                      <button type="button" className="secondary-button" disabled={imageBusy}
                                        onClick={clearSelectedFiles}>選択を取り消す</button>
                                    </div>
                                  </div>
                                )}
                                {imageError && <p className="image-error" role="alert">{imageError}</p>}
                                {imageMessage && <p className="image-success" role="status">{imageMessage}</p>}
                              </section>
                            </div>
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  ))}
                  {products.length === 0 && (
                    <tr><td colSpan="5" className="empty-message">商品が登録されていません。</td></tr>
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
