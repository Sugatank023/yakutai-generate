<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
$types = medicineTypes();
?><!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>薬袋作成</title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <header class="page-header no-print">
    <h1>薬袋作成</h1>
    <p>和の雰囲気を保ちながら、入力しやすいモダンな編集UIに更新しました。</p>
  </header>
  <div class="layout">
    <section class="card panel no-print">
      <div class="actions">
        <button type="button" onclick="window.location.href='admin.php'">医薬品管理</button>
        <button type="button" onclick="window.location.href='pharmacy.php'">薬局設定</button>
      </div>

      <label>患者名
        <input id="patient_name" type="text" maxlength="50" placeholder="患者名を入力">
      </label>

      <label>医薬品種別
        <select id="medicine_type">
          <?php foreach ($types as $key => $label): ?>
            <?php if ($key === 'kampo') continue; ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>医薬品名検索
        <input id="search_keyword" type="text" placeholder="医薬品名で検索">
      </label>
      <button type="button" id="search_btn">検索</button>

      <label>検索結果
        <select id="medicine_list" size="6"></select>
      </label>

      <label>用法
        <input id="dosage_usage" type="text">
      </label>
      <label>用量
        <input id="dosage_amount" type="text">
      </label>
      <label>1日回数
        <input id="daily_frequency" type="text">
      </label>
      <label>医薬品名
        <input id="medicine_name" type="text">
      </label>
      <label>医薬品説明
        <textarea id="description" rows="5"></textarea>
      </label>

      <button type="button" onclick="window.print()">印刷</button>
    </section>

    <section class="print-area" id="print_area">
      <div class="title" id="preview_type">内服薬</div>
      <div class="patient" id="preview_patient"></div>
      <div class="box">
        <div class="usage-label" id="preview_usage">用法</div>
        <div class="usage-main" id="preview_amount"></div>
        <div class="usage-freq" id="preview_freq"></div>
        <div class="medicine-name" id="preview_name"></div>
        <div class="desc" id="preview_desc"></div>
      </div>
      <div class="pharmacy" id="preview_pharmacy">
            <div id="preview-pharmacy-name"></div>
            <div id="preview-pharmacy-address"></div>
            <div id="preview-pharmacy-tel-fax"></div>
      </div>
    </section>
  </div>

  <script>
    const typeLabel = {
      external: '外用薬',
      internal: '内服薬',
      as_needed: '頓服薬'
    };

    async function fetchMedicines(keyword = '') {
      const res = await fetch(`api.php?path=medicines&keyword=${encodeURIComponent(keyword)}`);
      const json = await res.json();
      const list = document.getElementById('medicine_list');
      list.innerHTML = '';
      for (const item of json.data || []) {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = `${item.medicine_name} (${typeLabel[item.medicine_type] ?? item.medicine_type})`;
        opt.dataset.item = JSON.stringify(item);
        list.appendChild(opt);
      }
    }

    async function fetchPharmacy() {
      const res = await fetch('api.php?path=pharmacy');
      const json = await res.json();
      const p = json.data || {};
      document.getElementById('preview-pharmacy-name').textContent =
        `${p.pharmacy_name ?? ''}`;
      document.getElementById('preview-pharmacy-address').textContent =
        `${p.address ?? ''}`;
      document.getElementById('preview-pharmacy-tel-fax').textContent =
        `TEL:${p.phone ?? ''} FAX:${p.fax ?? ''}`;

    }

    function applyTheme(type) {
      const printArea = document.getElementById('print_area');
      printArea.classList.remove('type-internal', 'type-external', 'type-as-needed');
      if (type === 'external') {
        printArea.classList.add('type-external');
      } else if (type === 'as_needed') {
        printArea.classList.add('type-as-needed');
      } else {
        printArea.classList.add('type-internal');
      }
    }

    function syncPreview() {
      const patient = document.getElementById('patient_name').value;
      const type = document.getElementById('medicine_type').value;
      applyTheme(type);
      document.getElementById('preview_type').textContent = typeLabel[type] ?? type;
      document.getElementById('preview_patient').textContent = patient ? `${patient}様` : '';
      document.getElementById('preview_usage').textContent = '用法';
      document.getElementById('preview_amount').textContent = `${document.getElementById('dosage_usage').value} ${document.getElementById('dosage_amount').value}`.trim();
      document.getElementById('preview_freq').textContent = document.getElementById('daily_frequency').value;
      const medicineName = document.getElementById('medicine_name').value;
      document.getElementById('preview_name').textContent = medicineName ? `・${medicineName}` : '';
      document.getElementById('preview_desc').textContent = document.getElementById('description').value;
    }

    document.getElementById('search_btn').addEventListener('click', () => {
      fetchMedicines(document.getElementById('search_keyword').value);
    });

    document.getElementById('medicine_list').addEventListener('change', (e) => {
      const selected = e.target.options[e.target.selectedIndex];
      if (!selected || !selected.dataset.item) return;
      const item = JSON.parse(selected.dataset.item);
      document.getElementById('medicine_type').value = item.medicine_type;
      document.getElementById('dosage_usage').value = item.dosage_usage;
      document.getElementById('dosage_amount').value = item.dosage_amount;
      document.getElementById('daily_frequency').value = item.daily_frequency;
      document.getElementById('medicine_name').value = item.medicine_name;
      document.getElementById('description').value = item.description || '';
      syncPreview();
    });

    for (const id of ['patient_name', 'medicine_type', 'dosage_usage', 'dosage_amount', 'daily_frequency', 'medicine_name', 'description']) {
      document.getElementById(id).addEventListener('input', syncPreview);
      document.getElementById(id).addEventListener('change', syncPreview);
    }

    fetchMedicines();
    fetchPharmacy();
    syncPreview();
  </script>
</body>
</html>
