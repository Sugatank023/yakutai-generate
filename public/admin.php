<?php

// [ADDED] ファイル役割: 医薬品マスタCRUD画面（一覧検索・新規登録・編集更新・削除）。
// [ADDED] 入出力: GETは検索/編集対象取得、POSTは action に応じて create/update/delete を実行し、結果を画面メッセージ表示する。
// [ADDED] 依存: validateMedicineInput() -> MedicineRepository -> SQLite。
// [ADDED] セキュリティ注意: 更新系POSTにCSRFトークン検証は実装されていない（要確認）。

// 厳密型チェックを有効化し、意図しない型変換を防ぐ
declare(strict_types=1);

// 共通初期化（DB接続クラス・リポジトリ・バリデーション）を読み込む
require_once __DIR__ . '/../app/bootstrap.php';
// 共有SQLite接続を取得
$pdo = Database::connection();
// 医薬品テーブル操作用リポジトリを生成
$repository = new MedicineRepository($pdo);
// 種別キーと表示名の対応表を取得
$types = medicineTypes();

// 成功メッセージ表示用変数を初期化
$message = '';
// エラーメッセージ表示用変数を初期化
$error = '';

// POST時（登録・更新・削除）にのみ更新処理を実行
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [ADDED] サーバー側処理フロー: action判定 -> （create/update時）入力バリデーション -> Repository呼び出し -> 成功/失敗メッセージ設定。
    try {
        // hidden項目actionで処理種別を判定（未指定時はcreate）
        $action = $_POST['action'] ?? 'create';
        if ($action === 'delete') {
            // 削除処理
            $repository->delete((int)$_POST['id']);
            $message = '削除しました。';
        } else {
            // 登録・更新共通の入力バリデーション
            $data = validateMedicineInput($_POST);
            if ($action === 'update') {
                // 更新処理
                $repository->update((int)$_POST['id'], $data);
                $message = '更新しました。';
            } else {
                // 新規登録処理
                $repository->create($data);
                $message = '登録しました。';
            }
        }
    } catch (Throwable $e) {
        // 例外発生時は画面上部へエラーメッセージ表示
        $error = $e->getMessage();
    }
}

// 一覧検索キーワードを取得（未指定時は空文字）
$keyword = trim((string)($_GET['keyword'] ?? ''));
// キーワードで医薬品一覧を取得
$items = $repository->searchByName($keyword);
// 編集対象ID（?edit=）を取得
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
// 編集対象IDが有効な場合のみ既存データを取得
$editing = $editId > 0 ? $repository->find($editId) : null;
?><!doctype html>
<html lang="ja">
<head>
  <!-- 日本語表示を正しく行うための文字コード指定 -->
  <meta charset="UTF-8">
  <!-- スマートフォンでも拡大縮小を抑えて見やすくする設定 -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- 医薬品マスタ管理ページのタイトル -->
  <title>医薬品管理</title>
  <!-- 共通スタイルシート（カード・テーブル・フォーム） -->
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <!-- ページヘッダーとメイン画面への戻りリンク -->
  <header class="page-header">
    <h1>医薬品管理</h1>
    <p>薬袋作成ページと統一したデザインで、医薬品マスタを管理します。</p>
    <a class="back-link" href="index.php">← 薬袋作成へ</a>
  </header>
  <!-- 正常終了時の通知メッセージ（登録・更新・削除後） -->
  <?php if ($message !== ''): ?><p class="msg"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <!-- 例外発生時のエラーメッセージ -->
  <?php if ($error !== ''): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

  <!-- 医薬品名で一覧を絞り込む検索フォーム（GET送信） -->
  <section class="card panel">
    <form method="get" class="actions">
      <input type="text" name="keyword" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" placeholder="医薬品名で検索">
      <button type="submit">検索</button>
    </form>
  </section>

  <!-- 新規登録・編集を共通化した入力フォーム -->
  <section class="card panel">
    <h2><?= $editing ? '医薬品編集' : '新規登録' ?></h2>
    <form method="post">
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
      <!-- 主要項目はレスポンシブ対応のグリッドで並べる -->
      <div class="grid-form">
        <div><label>医薬品名<br><input required name="medicine_name" value="<?= htmlspecialchars((string)($editing['medicine_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label></div>
        <div><label>医薬品種別<br>
          <select name="medicine_type" required>
            <?php foreach ($types as $key => $label): ?>
              <?php if ($key === 'kampo') continue; ?>
              <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= (($editing['medicine_type'] ?? '') === $key) ? 'selected' : '' ?>>
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label></div>
        <div><label>用法<br><input required name="dosage_usage" value="<?= htmlspecialchars((string)($editing['dosage_usage'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label></div>
        <div><label>用量<br><input required name="dosage_amount" value="<?= htmlspecialchars((string)($editing['dosage_amount'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label></div>
        <div><label>1日回数<br><input required name="daily_frequency" value="<?= htmlspecialchars((string)($editing['daily_frequency'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label></div>
      </div>
      <!-- 任意入力の説明欄（薬袋下部説明として表示される想定） -->
      <div><label>説明<br><textarea name="description" rows="3"><?= htmlspecialchars((string)($editing['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></label></div>
      <button type="submit"><?= $editing ? '更新する' : '登録する' ?></button>
    </form>
  </section>

  <!-- 医薬品マスタ一覧テーブル（検索結果を表示） -->
  <section class="card panel table-card">
    <h2>一覧</h2>
    <div class="table-wrapper">
      <!-- テーブルはID・種別・用法などを1行で確認できるよう構成 -->
      <table>
        <thead>
          <tr><th>ID</th><th>医薬品名</th><th>種別</th><th>用法</th><th>用量</th><th>1日回数</th><th>操作</th></tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= (int)$item['id'] ?></td>
              <td><?= htmlspecialchars($item['medicine_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($types[$item['medicine_type']] ?? $item['medicine_type'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($item['dosage_usage'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($item['dosage_amount'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($item['daily_frequency'], ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <a class="back-link" href="admin.php?edit=<?= (int)$item['id'] ?>">編集</a>
                <form method="post" class="inline-form" onsubmit="return confirm('削除しますか？')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <button type="submit">削除</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</body>
</html>
