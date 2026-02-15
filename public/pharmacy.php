<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
$pdo = Database::connection();
$repository = new PharmacyRepository($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $repository->update(validatePharmacyInput($_POST));
        $message = '薬局情報を更新しました。';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$data = $repository->get();
?><!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>薬局情報設定</title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <header class="page-header">
    <h1>薬局情報設定</h1>
    <p>薬袋の印字に利用する薬局情報を、見やすい画面で更新できます。</p>
    <a class="back-link" href="index.php">← 薬袋作成へ</a>
  </header>

  <?php if ($message !== ''): ?><p class="msg"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

  <section class="card panel" style="max-width:680px;">
    <form method="post">
      <label>薬局名
        <input required name="pharmacy_name" value="<?= htmlspecialchars((string)($data['pharmacy_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </label>
      <label>住所
        <input required name="address" value="<?= htmlspecialchars((string)($data['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </label>
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
