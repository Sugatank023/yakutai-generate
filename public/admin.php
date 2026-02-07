<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
$pdo = Database::connection();
$repository = new MedicineRepository($pdo);
$types = medicineTypes();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? 'create';
        if ($action === 'delete') {
            $repository->delete((int)$_POST['id']);
            $message = '削除しました。';
        } else {
            $data = validateMedicineInput($_POST);
            if ($action === 'update') {
                $repository->update((int)$_POST['id'], $data);
                $message = '更新しました。';
            } else {
                $repository->create($data);
                $message = '登録しました。';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$keyword = trim((string)($_GET['keyword'] ?? ''));
$items = $repository->searchByName($keyword);
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editing = $editId > 0 ? $repository->find($editId) : null;
?><!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>医薬品管理</title>
  <style>
    body { font-family: sans-serif; margin: 16px; }
    table { border-collapse: collapse; width: 100%; margin-top: 12px; }
    th, td { border: 1px solid #ccc; padding: 8px; }
    input, select, textarea, button { padding: 8px; margin: 4px 0; }
    .row { display: flex; gap: 8px; flex-wrap: wrap; }
    .row > * { flex: 1; min-width: 180px; }
    .msg { color: green; }
    .err { color: red; }
  </style>
</head>
<body>
  <h1>医薬品管理</h1>
  <p><a href="index.php">← 薬袋作成へ</a></p>
  <?php if ($message !== ''): ?><p class="msg"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

  <form method="get">
    <input type="text" name="keyword" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" placeholder="医薬品名で検索">
    <button type="submit">検索</button>
  </form>

  <h2><?= $editing ? '医薬品編集' : '新規登録' ?></h2>
  <form method="post">
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
    <div class="row">
      <div><label>医薬品名<br><input required name="medicine_name" value="<?= htmlspecialchars((string)($editing['medicine_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label></div>
      <div><label>医薬品種別<br>
        <select name="medicine_type" required>
          <?php foreach ($types as $key => $label): ?>
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
    <div><label>説明<br><textarea name="description" rows="3" style="width:100%;"><?= htmlspecialchars((string)($editing['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></label></div>
    <button type="submit"><?= $editing ? '更新する' : '登録する' ?></button>
  </form>

  <h2>一覧</h2>
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
            <a href="admin.php?edit=<?= (int)$item['id'] ?>">編集</a>
            <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <button type="submit">削除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
