<?php

// [ADDED] ファイル役割: 薬局情報設定画面（実質1レコード運用の表示・更新）。
// [ADDED] 入出力: GET表示時は PharmacyRepository::get() 結果をフォーム初期値に使用、POST時は validatePharmacyInput() 後に update() でUpsert。
// [ADDED] セキュリティ注意: 更新POSTにCSRFトークン検証は見当たらない（要確認）。

// 厳密型チェックを有効化し、入力データの型ゆらぎを抑止する
declare(strict_types=1);

// 共通初期化（DB接続・リポジトリ・バリデーション関数）を読み込む
require_once __DIR__ . '/../app/bootstrap.php';
// 共有SQLite接続を取得する
$pdo = Database::connection();
// 薬局情報テーブル専用リポジトリを生成する
$repository = new PharmacyRepository($pdo);

// 保存成功時のメッセージ表示用変数
$message = '';
// エラー発生時のメッセージ表示用変数
$error = '';

// POST時（保存ボタン押下時）のみ更新処理を実行する
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [ADDED] 処理ブロック: 入力取得($_POST) -> バリデーション -> Repository更新 -> 画面用メッセージ設定。
    try {
        // 入力値を検証した上で薬局情報を更新する
        $repository->update(validatePharmacyInput($_POST));
        $message = '薬局情報を更新しました。';
    } catch (Throwable $e) {
        // 例外メッセージを画面上に表示する
        $error = $e->getMessage();
    }
}

// 画面表示用に現在の薬局情報を取得する
$data = $repository->get();
?><!doctype html>
<html lang="ja">
<head>
  <!-- 日本語を正しく扱うための文字コード指定 -->
  <meta charset="UTF-8">
  <!-- モバイルでも等倍で見やすい表示にするためのviewport設定 -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- 薬局情報設定ページのタイトル -->
  <title>薬局情報設定</title>
  <!-- 共通UI用スタイルシート -->
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <!-- ページ見出しとメイン画面への戻り導線 -->
  <header class="page-header">
    <h1>薬局情報設定</h1>
    <p>薬袋の印字に利用する薬局情報を、見やすい画面で更新できます。</p>
    <a class="back-link" href="index.php">← 薬袋作成へ</a>
  </header>

  <!-- 保存成功時のメッセージ表示 -->
  <?php if ($message !== ''): ?><p class="msg"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <!-- 保存失敗時のエラーメッセージ表示 -->
  <?php if ($error !== ''): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

  <!-- 薬袋下部に印字される薬局情報の編集フォーム -->
  <section class="card panel" style="max-width:680px;">
    <form method="post">
      <!-- 薬局名称（必須） -->
      <label>薬局名
        <input required name="pharmacy_name" value="<?= htmlspecialchars((string)($data['pharmacy_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </label>
      <!-- 薬局住所（必須） -->
      <label>住所
        <input required name="address" value="<?= htmlspecialchars((string)($data['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </label>
      <!-- 電話/FAXは横並びで入力できるようグリッド化 -->
      <div class="grid-form">
        <label>電話番号
          <input required name="phone" value="<?= htmlspecialchars((string)($data['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>FAX番号
          <input required name="fax" value="<?= htmlspecialchars((string)($data['fax'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </label>
      </div>
      <button type="submit" style="margin-top:12px;">保存</button>
    </form>
  </section>
</body>
</html>
