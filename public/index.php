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
  <style>
    body { font-family: sans-serif; margin: 16px; }
    .layout { display: grid; grid-template-columns: 360px 1fr; gap: 16px; align-items: start; }
    .panel { border: 1px solid #ddd; padding: 12px; border-radius: 8px; }
    label { display: block; font-size: 12px; margin-top: 8px; }
    input, select, textarea, button { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
    .print-area { width: 148mm; min-height: 210mm; border: 1px solid #000; padding: 3mm 8mm 3mm; background: #fff; }
    .title { text-align: center; font-size: 60px; font-weight: bold; margin-bottom: 8px; }
    .patient { text-align: center; font-size: 36px; margin-bottom: 8px; padding-bottom: 4px; }
    .box { border: 1px solid #000; padding: 8px; min-height: 95mm; }
    .usage-label, .usage-main, .usage-freq { font-size: 33px; text-align: center; }
    .usage-label { text-align: left; }
    .usage-main, .usage-freq { margin-top: 4px; }
    .medicine-name { margin-top: 4mm; font-size: 27px; font-weight: bold; }
    .desc { margin-top: 6px; min-height: 40mm; white-space: pre-wrap; }
    .pharmacy { margin-top: 12mm; font-size: 24px; text-align: center; }
    .actions { display: flex; gap: 8px; }
    .actions button { flex: 1; }
    @page { size: A5; margin: 8mm; }
    @media print {
      .no-print { display: none !important; }
      body { margin: 0; }
      .layout { display: block; }
      .print-area { border: none; width: auto; min-height: auto; }
    }
  </style>
</head>
<body>
  <h1 class="no-print">薬袋作成</h1>
  <div class="layout">
    <section class="panel no-print">
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
      kampo: '漢方薬',
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

    function syncPreview() {
      const patient = document.getElementById('patient_name').value;
      const type = document.getElementById('medicine_type').value;
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
