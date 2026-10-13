import { Fragment, useEffect, useState } from 'react';
import './StoresApp.css';

function StoresApp() {
  const [stores, setStores] = useState([]);
  const [loading, setLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState('');

  // 新規登録フォームの状態
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [createCsrfToken, setCreateCsrfToken] = useState('');
  const [storeCode, setStoreCode] = useState('');
  const [storeName, setStoreName] = useState('');
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState('');
  const [createSuccess, setCreateSuccess] = useState('');

  // 編集フォームの状態
  const [editingStoreId, setEditingStoreId] = useState(null);
  const [editStoreCode, setEditStoreCode] = useState('');
  const [editStoreName, setEditStoreName] = useState('');
  const [editStoreStatus, setEditStoreStatus] = useState('active');
  const [editCsrfToken, setEditCsrfToken] = useState('');
  const [editLoading, setEditLoading] = useState(false);
  const [savingEdit, setSavingEdit] = useState(false);
  const [editError, setEditError] = useState('');
  const [editSuccess, setEditSuccess] = useState('');

  // 店舗一覧を取得する
  async function fetchStores() {
    setLoading(true);
    setErrorMessage('');

    try {
      const response = await fetch('./api/stores.php', {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(
          data.message || '店舗一覧を取得できませんでした。',
        );
      }

      setStores(data.stores);
    } catch (error) {
      console.error('店舗一覧の取得に失敗しました:', error);

      setErrorMessage(
        error.message ||
          '通信に失敗しました。時間をおいて再度お試しください。',
      );
    } finally {
      setLoading(false);
    }
  }

  // 新規登録フォームを開く
  async function openCreateForm() {
    setShowCreateForm(true);
    setCreateError('');
    setCreateSuccess('');
    setStoreCode('');
    setStoreName('');
    setCreateCsrfToken('');

    // 新規登録と編集を同時に開かない
    setEditingStoreId(null);
    setEditError('');
    setEditSuccess('');

    try {
      const response = await fetch('./api/store_create.php', {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(
          data.message || '登録フォームを開けませんでした。',
        );
      }

      setCreateCsrfToken(data.csrf_token);
    } catch (error) {
      console.error('登録フォームの準備に失敗しました:', error);

      setCreateError(
        error.message || '登録フォームの準備に失敗しました。',
      );
    }
  }

  // 新規登録フォームを閉じる
  function closeCreateForm() {
    if (creating) return;

    setShowCreateForm(false);
    setCreateError('');
    setCreateCsrfToken('');
    setStoreCode('');
    setStoreName('');
  }

  // 新しい店舗を登録する
  async function handleCreateStore(event) {
    event.preventDefault();

    if (creating) return;

    setCreating(true);
    setCreateError('');

    try {
      const response = await fetch('./api/store_create.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          store_code: storeCode,
          name: storeName,
          csrf_token: createCsrfToken,
        }),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(
          data.message || '店舗を登録できませんでした。',
        );
      }

      setShowCreateForm(false);
      setStoreCode('');
      setStoreName('');
      setCreateCsrfToken('');
      setCreateError('');
      setCreateSuccess('店舗を登録しました。');

      await fetchStores();
    } catch (error) {
      console.error('店舗登録に失敗しました:', error);

      setCreateError(
        error.message ||
          '通信に失敗しました。時間をおいて再度お試しください。',
      );
    } finally {
      setCreating(false);
    }
  }

  // 編集フォームを閉じる
  function closeEditForm() {
    if (savingEdit) return;

    setEditingStoreId(null);
    setEditStoreCode('');
    setEditStoreName('');
    setEditStoreStatus('active');
    setEditCsrfToken('');
    setEditError('');
    setEditSuccess('');
  }

  // 編集ボタンを押した店舗の情報を取得する
  async function openEditForm(storeId) {
    if (savingEdit) return;

    // すでに開いている店舗の編集ボタンなら閉じる
    if (editingStoreId === storeId) {
      closeEditForm();
      return;
    }

    setShowCreateForm(false);
    setCreateError('');
    setCreateSuccess('');

    setEditingStoreId(storeId);
    setEditStoreCode('');
    setEditStoreName('');
    setEditStoreStatus('active');
    setEditCsrfToken('');
    setEditError('');
    setEditSuccess('');
    setEditLoading(true);

    try {
      const response = await fetch(
        `./api/store_edit.php?id=${encodeURIComponent(storeId)}`,
        {
          method: 'GET',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
          },
        },
      );

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(
          data.message || '店舗情報を取得できませんでした。',
        );
      }

      // 別の店舗へ切り替わっていたら古い結果を反映しない
      setEditStoreCode(data.store.store_code);
      setEditStoreName(data.store.name);
      setEditStoreStatus(data.store.status);
      setEditCsrfToken(data.csrf_token);
    } catch (error) {
      console.error('店舗情報の取得に失敗しました:', error);

      setEditError(
        error.message || '店舗情報を取得できませんでした。',
      );
    } finally {
      setEditLoading(false);
    }
  }

  // 編集内容を保存する
  async function handleSaveEdit(event, storeId) {
    event.preventDefault();

    if (savingEdit) return;

    setSavingEdit(true);
    setEditError('');

    try {
      const response = await fetch('./api/store_edit.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          id: storeId,
          store_code: editStoreCode,
          name: editStoreName,
          status: editStoreStatus,
          csrf_token: editCsrfToken,
        }),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(
          data.message || '店舗情報を更新できませんでした。',
        );
      }

      setEditingStoreId(null);
      setEditStoreCode('');
      setEditStoreName('');
      setEditStoreStatus('active');
      setEditCsrfToken('');
      setEditError('');
      setEditSuccess('店舗情報を更新しました。');

      await fetchStores();
    } catch (error) {
      console.error('店舗情報の更新に失敗しました:', error);

      setEditError(
        error.message ||
          '通信に失敗しました。時間をおいて再度お試しください。',
      );
    } finally {
      setSavingEdit(false);
    }
  }

  // 初回表示時に店舗一覧を取得する
  useEffect(() => {
    fetchStores();
  }, []);

  const statusLabels = {
    active: '有効',
    inactive: '無効',
  };

  return (
    <div className="stores-app">
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
          <a href="./index.php">管理画面トップ</a>
          <a href="./stores.php" className="is-active" aria-current="page">
            店舗管理
          </a>
          <a href="./admin_users.php">管理者管理</a>
          <a href="./logout.php" className="common-logout-link">
            ログアウト
          </a>
        </nav>
      </header>

      <main className="stores-main">
        <nav className="stores-breadcrumb" aria-label="パンくずリスト">
          <a href="./index.php">管理画面トップ</a>
          <span aria-hidden="true">/</span>
          <span>店舗管理</span>
        </nav>

        <section className="stores-panel">
          <div className="stores-panel-heading">
            <div>
              <h2>店舗一覧</h2>
              <p>登録されている店舗の情報を確認できます。</p>
            </div>

            <button
              type="button"
              className="stores-primary-button"
              onClick={openCreateForm}
              disabled={showCreateForm || creating || savingEdit}
            >
              ＋ 新しい店舗を登録
            </button>
          </div>

          {createSuccess && (
            <div className="stores-success" role="status">
              {createSuccess}
            </div>
          )}

          {editSuccess && (
            <div className="stores-success" role="status">
              {editSuccess}
            </div>
          )}

          {/* 新規登録フォーム */}
          {showCreateForm && (
            <section className="stores-create-panel">
              <div className="stores-create-heading">
                <div>
                  <h3>新しい店舗を登録</h3>
                  <p>店舗の基本情報を入力してください。</p>
                </div>

                <button
                  type="button"
                  className="stores-close-button"
                  onClick={closeCreateForm}
                  disabled={creating}
                  aria-label="登録フォームを閉じる"
                >
                  ×
                </button>
              </div>

              {createError && (
                <div className="stores-error" role="alert">
                  {createError}
                </div>
              )}

              <form
                className="stores-create-form"
                onSubmit={handleCreateStore}
              >
                <div className="stores-form-field">
                  <label htmlFor="store-code">
                    店舗コード
                    <span className="stores-required">必須</span>
                  </label>

                  <input
                    id="store-code"
                    type="text"
                    value={storeCode}
                    onChange={(event) =>
                      setStoreCode(event.target.value)
                    }
                    maxLength={50}
                    pattern="[A-Za-z0-9_-]{1,50}"
                    title="半角英数字、ハイフン、アンダースコアを使用できます。"
                    placeholder="例：store004"
                    autoComplete="off"
                    required
                    disabled={creating || !createCsrfToken}
                  />

                  <p>
                    半角英数字・ハイフン・アンダースコア、50文字以内
                  </p>
                </div>

                <div className="stores-form-field">
                  <label htmlFor="store-name">
                    店舗名
                    <span className="stores-required">必須</span>
                  </label>

                  <input
                    id="store-name"
                    type="text"
                    value={storeName}
                    onChange={(event) =>
                      setStoreName(event.target.value)
                    }
                    maxLength={150}
                    placeholder="例：〇〇農園"
                    required
                    disabled={creating || !createCsrfToken}
                  />
                </div>

                <div className="stores-form-actions">
                  <button
                    type="button"
                    className="stores-cancel-button"
                    onClick={closeCreateForm}
                    disabled={creating}
                  >
                    キャンセル
                  </button>

                  <button
                    type="submit"
                    className="stores-primary-button"
                    disabled={creating || !createCsrfToken}
                  >
                    {creating ? '登録中…' : '登録する'}
                  </button>
                </div>

                {!createCsrfToken && !createError && (
                  <p className="stores-form-hint" role="status">
                    登録フォームを準備しています…
                  </p>
                )}
              </form>
            </section>
          )}

          <div className="stores-summary">
            <span>登録店舗数</span>
            <strong>{loading ? '—' : stores.length}</strong>
            <span>件</span>

            <button
              type="button"
              className="stores-refresh-button"
              onClick={fetchStores}
              disabled={loading || creating || savingEdit}
            >
              {loading ? '取得中…' : '再読み込み'}
            </button>
          </div>

          {loading ? (
            <div className="stores-message" role="status">
              店舗一覧を読み込んでいます…
            </div>
          ) : errorMessage ? (
            <div className="stores-error" role="alert">
              <p>{errorMessage}</p>
              <button type="button" onClick={fetchStores}>
                もう一度試す
              </button>
            </div>
          ) : stores.length === 0 ? (
            <div className="stores-empty">
              <p>店舗はまだ登録されていません。</p>
              <button
                type="button"
                className="stores-text-button"
                onClick={openCreateForm}
              >
                最初の店舗を登録する
              </button>
            </div>
          ) : (
            <div className="stores-table-wrapper">
              <table className="stores-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>店舗コード</th>
                    <th>店舗名</th>
                    <th>状態</th>
                    <th>登録日時</th>
                    <th>操作</th>
                  </tr>
                </thead>

                <tbody>
                  {stores.map((store) => (
                    <Fragment key={store.id}>
                      <tr key={store.id}>
                        <td>{store.id}</td>
                        <td>{store.store_code}</td>
                        <td className="stores-name">{store.name}</td>
                        <td>
                          <span
                            className={`stores-status stores-status-${store.status}`}
                          >
                            {statusLabels[store.status] ?? store.status}
                          </span>
                        </td>
                        <td>{store.created_at}</td>
                        <td>
                          <button
                            type="button"
                            className="stores-edit-link"
                            onClick={() => openEditForm(store.id)}
                            disabled={savingEdit || editLoading}
                          >
                            {editingStoreId === store.id
                              ? '編集を閉じる'
                              : '編集'}
                          </button>
                        </td>
                      </tr>

                      {/* 編集フォームを対象店舗の直下に表示 */}
                      {editingStoreId === store.id && (
                        <tr key={`${store.id}-edit`}>
                          <td colSpan={6} className="stores-edit-cell">
                            <section className="stores-edit-panel">
                              <div className="stores-create-heading">
                                <div>
                                  <h3>店舗情報を編集</h3>
                                  <p>
                                    店舗ID：{store.id} ／ {store.name}
                                  </p>
                                </div>

                                <button
                                  type="button"
                                  className="stores-close-button"
                                  onClick={closeEditForm}
                                  disabled={savingEdit}
                                  aria-label="編集フォームを閉じる"
                                >
                                  ×
                                </button>
                              </div>

                              {editLoading ? (
                                <div
                                  className="stores-message"
                                  role="status"
                                >
                                  店舗情報を取得しています…
                                </div>
                              ) : (
                                <>
                                  {editError && (
                                    <div
                                      className="stores-error"
                                      role="alert"
                                    >
                                      {editError}
                                    </div>
                                  )}

                                  <form
                                    className="stores-create-form"
                                    onSubmit={(event) =>
                                      handleSaveEdit(event, store.id)
                                    }
                                  >
                                    <div className="stores-form-field">
                                      <label htmlFor={`edit-code-${store.id}`}>
                                        店舗コード
                                        <span className="stores-required">
                                          必須
                                        </span>
                                      </label>

                                      <input
                                        id={`edit-code-${store.id}`}
                                        type="text"
                                        value={editStoreCode}
                                        onChange={(event) =>
                                          setEditStoreCode(
                                            event.target.value,
                                          )
                                        }
                                        maxLength={50}
                                        pattern="[A-Za-z0-9_-]{1,50}"
                                        title="半角英数字、ハイフン、アンダースコアを使用できます。"
                                        required
                                        disabled={savingEdit || !editCsrfToken}
                                      />
                                      <p>
                                        半角英数字・ハイフン・アンダースコア、50文字以内
                                      </p>
                                    </div>

                                    <div className="stores-form-field">
                                      <label htmlFor={`edit-name-${store.id}`}>
                                        店舗名
                                        <span className="stores-required">
                                          必須
                                        </span>
                                      </label>

                                      <input
                                        id={`edit-name-${store.id}`}
                                        type="text"
                                        value={editStoreName}
                                        onChange={(event) =>
                                          setEditStoreName(
                                            event.target.value,
                                          )
                                        }
                                        maxLength={150}
                                        required
                                        disabled={savingEdit || !editCsrfToken}
                                      />
                                    </div>

                                    <div className="stores-form-field">
                                      <label htmlFor={`edit-status-${store.id}`}>
                                        店舗の状態
                                      </label>

                                      <select
                                        id={`edit-status-${store.id}`}
                                        value={editStoreStatus}
                                        onChange={(event) =>
                                          setEditStoreStatus(
                                            event.target.value,
                                          )
                                        }
                                        required
                                        disabled={savingEdit || !editCsrfToken}
                                      >
                                        <option value="active">有効</option>
                                        <option value="inactive">無効</option>
                                      </select>
                                    </div>

                                    <div className="stores-form-actions">
                                      <button
                                        type="button"
                                        className="stores-cancel-button"
                                        onClick={closeEditForm}
                                        disabled={savingEdit}
                                      >
                                        キャンセル
                                      </button>

                                      <button
                                        type="submit"
                                        className="stores-primary-button"
                                        disabled={
                                          savingEdit || !editCsrfToken
                                        }
                                      >
                                        {savingEdit
                                          ? '保存中…'
                                          : '変更を保存'}
                                      </button>
                                    </div>

                                    {!editCsrfToken && !editError && (
                                      <p
                                        className="stores-form-hint"
                                        role="status"
                                      >
                                        編集フォームを準備しています…
                                      </p>
                                    )}
                                  </form>
                                </>
                              )}
                            </section>
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        <footer className="stores-footer">
          <a href="./index.php">管理画面トップへ戻る</a>
          <a href="./logout.php">ログアウト</a>
        </footer>
      </main>
    </div>
  );
}

export default StoresApp;