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
    body { font-family: "Hiragino Mincho ProN", "Yu Mincho", "MS PMincho", serif; margin: 16px; background: #f4f1e7; color: #1e1a14; }
    .layout { display: grid; grid-template-columns: 360px 1fr; gap: 16px; align-items: start; }
    .panel { border: 1px solid #ddd; padding: 12px; border-radius: 8px; background: #fff; }
    label { display: block; font-size: 12px; margin-top: 8px; }
    input, select, textarea, button { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
    .print-area {
      width: 148mm;
      min-height: 210mm;
      border: 2px solid var(--theme-color);
      box-shadow: inset 0 0 0 2px var(--theme-soft);
      padding: 5mm 8mm 4mm;
      background: #fffef8;
      --theme-color: #1f57a5;
      --theme-soft: #c7d8ef;
      position: relative;
    }
    .print-area::before,
    .print-area::after {
      content: "";
      position: absolute;
      left: 5mm;
      right: 5mm;
      height: 2px;
      background: var(--theme-color);
    }
    .print-area::before { top: 3.5mm; }
    .print-area::after { bottom: 3.5mm; }
    .title {
      text-align: center;
      font-size: 56px;
      font-weight: 700;
      margin: 5mm 0 2mm;
      letter-spacing: 0.18em;
      color: var(--theme-color);
      text-shadow: 1px 1px 0 #e8dac4;
    }
    .patient {
      text-align: center;
      font-size: 34px;
      margin-bottom: 8px;
      padding-bottom: 8px;
      border-bottom: 2px double var(--theme-color);
      letter-spacing: 0.08em;
    }
    .box {
      border: 2px solid var(--theme-color);
      box-shadow: inset 0 0 0 1px var(--theme-soft);
      padding: 12px;
      min-height: 95mm;
      background: repeating-linear-gradient(
        180deg,
        rgba(157, 31, 35, 0.06) 0,
        rgba(157, 31, 35, 0.06) 1px,
        transparent 1px,
        transparent 12mm
      );
    }
    .usage-label, .usage-main, .usage-freq { font-size: 33px; text-align: center; }
    .usage-label { text-align: left; color: var(--theme-color); font-weight: 700; }
    .usage-main, .usage-freq { margin-top: 4px; }
    .medicine-name {
      margin-top: 6mm;
      font-size: 29px;
      font-weight: 700;
      border-top: 1px solid #8d7d6a;
      padding-top: 3mm;
    }
    .desc { margin-top: 6px; min-height: 40mm; white-space: pre-wrap; }
    .pharmacy {
      margin-top: 12mm;
      font-size: 22px;
      text-align: center;
      border-top: 2px double var(--theme-color);
      padding-top: 4mm;
      line-height: 1.4;
      letter-spacing: 0.04em;
    }

    .print-area.type-internal {
      --theme-color: #1f57a5;
      --theme-soft: #c7d8ef;
    }
    .print-area.type-external {
      --theme-color: #b1282f;
      --theme-soft: #ebc3c6;
    }
    .print-area.type-as-needed {
      --theme-color: #2f8a3a;
      --theme-soft: #c5e2c9;
    }
    .actions { display: flex; gap: 8px; }
    .actions button { flex: 1; }
    @page { size: A5; margin: 8mm; }
    @media print {
      .no-print { display: none !important; }
      body { margin: 0; }
      .layout { display: block; }
      .print-area {
        border: 2px solid var(--theme-color);
        box-shadow: inset 0 0 0 2px var(--theme-soft);
        width: auto;
        min-height: auto;
      }
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
