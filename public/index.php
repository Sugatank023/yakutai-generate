<?php

// [ADDED] ファイル役割: 薬袋作成画面（入力フォーム + A5プレビュー + window.print() 印刷トリガ）。
// [ADDED] 入出力: PHP側は medicineTypes() を使って初期HTMLを描画。JS側は api.php へfetchし、DOM更新でプレビュー同期を行う。
// [ADDED] セキュリティ注意: フロントの入力値はそのままDOM.textContentへ反映しておりHTMLとしては挿入していない。最終的な保存時検証はサーバー側バリデーションに依存する。

// PHPの厳密型チェックを有効化し、想定外の型変換を防ぐ
declare(strict_types=1);

// 共通初期化（DBクラス・リポジトリ・バリデーション関数）を読み込む
require_once __DIR__ . '/../app/bootstrap.php';
// 医薬品種別の表示用マスタを取得する
$types = medicineTypes();
?><!doctype html>
<html lang="ja">
<head>
  <!-- 文字エンコーディングをUTF-8に固定して日本語の文字化けを防ぐ -->
  <meta charset="UTF-8">
  <!-- モバイル端末でもレイアウト比率を維持するためのviewport指定 -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- ブラウザタブに表示するページ名 -->
  <title>薬袋作成</title>
  <!-- 画面表示・印刷表示で共通利用するスタイルシート -->
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <!-- 画面上部のページ見出し。no-print指定で印刷時は非表示 -->
  <header class="page-header no-print">
    <h1>薬袋作成</h1>
    <p>和の雰囲気を保ちながら、入力しやすいモダンな編集UIに更新しました。</p>
  </header>
  <!-- 左:入力パネル / 右:A5プレビュー の2カラムレイアウト -->
  <div class="layout">
    <!-- 入力UIセクション。印刷対象外のため no-print を付与 -->
    <section class="card panel no-print">
      <!-- 管理画面への遷移ボタン群 -->
      <div class="actions">
        <button type="button" onclick="window.location.href='admin.php'">医薬品管理</button>
        <button type="button" onclick="window.location.href='pharmacy.php'">薬局設定</button>
      </div>

      <!-- 患者名入力欄（薬袋上部の「○○様」表示に反映） -->
      <label>患者名
        <input id="patient_name" type="text" maxlength="50" placeholder="患者名を入力">
      </label>

      <!-- 医薬品種別選択欄（テーマ色と見出し文言を切替） -->
      <label>医薬品種別
        <select id="medicine_type">
          <?php foreach ($types as $key => $label): ?>
            <?php if ($key === 'kampo') continue; ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <!-- 医薬品マスタ検索キーワード入力 -->
      <label>医薬品名検索
        <input id="search_keyword" type="text" placeholder="医薬品名で検索">
      </label>
      <!-- キーワードでAPI検索を実行するボタン -->
      <button type="button" id="search_btn">検索</button>

      <!-- 検索結果候補（選択時に用法・用量などへ自動反映） -->
      <label>検索結果
        <select id="medicine_list" size="6"></select>
      </label>

      <!-- 以下は薬袋本文へ反映される編集フィールド群 -->
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

      <!-- ブラウザ印刷ダイアログを開くボタン -->
      <button type="button" onclick="window.print()">印刷</button>
    </section>

    <!-- A5薬袋のリアルタイムプレビュー領域（印刷対象） -->
    <section class="print-area" id="print_area">
      <!-- 薬種別タイトル（内服薬/外用薬/頓服薬） -->
      <div class="title" id="preview_type">内服薬</div>
      <!-- 患者名表示エリア（入力時に「様」付きで更新） -->
      <div class="patient" id="preview_patient"></div>
      <!-- 用法・用量・回数・薬品名・説明の本文ボックス -->
      <div class="box">
        <div class="usage-label" id="preview_usage">用法</div>
        <div class="usage-main" id="preview_amount"></div>
        <div class="usage-freq" id="preview_freq"></div>
        <div class="medicine-name" id="preview_name"></div>
        <div class="desc" id="preview_desc"></div>
      </div>
      <!-- 枠外下部の薬局情報表示エリア -->
      <div class="pharmacy" id="preview_pharmacy">
            <div id="preview-pharmacy-name"></div>
            <div id="preview-pharmacy-address"></div>
            <div id="preview-pharmacy-tel-fax"></div>
      </div>
    </section>
  </div>

  <script>
    // 医薬品種別キーを画面表示用ラベルへ変換するための定義
    const typeLabel = {
      external: '外用薬',
      internal: '内服薬',
      as_needed: '頓服薬'
    };

    // [ADDED] データフロー概要: fetchMedicines()/fetchPharmacy()でAPI取得 -> medicine_list選択または手入力 -> syncPreview()で印刷領域DOMを更新 -> window.print()で印刷。
    // 医薬品検索APIを呼び出し、候補一覧の<select>を更新する関数
    async function fetchMedicines(keyword = '') {
      // URLパラメータとして検索キーワードを付与してAPIへGET送信
      const res = await fetch(`api.php?path=medicines&keyword=${encodeURIComponent(keyword)}`);
      // レスポンスJSONをJavaScriptオブジェクトへ変換
      const json = await res.json();
      // 候補を表示するセレクトボックス要素を取得
      const list = document.getElementById('medicine_list');
      // 既存候補を一旦クリアして最新検索結果だけを表示する
      list.innerHTML = '';
      // APIから返った候補配列を1件ずつ<option>へ変換
      for (const item of json.data || []) {
        // option要素を動的生成
        const opt = document.createElement('option');
        // valueには医薬品IDを設定（選択識別用）
        opt.value = item.id;
        // 一覧表示テキストは「医薬品名（種別）」形式に整形
        opt.textContent = `${item.medicine_name} (${typeLabel[item.medicine_type] ?? item.medicine_type})`;
        // 後段でフォームへ反映できるよう、レコード全体をdata属性へ保存
        opt.dataset.item = JSON.stringify(item);
        // 生成したoptionをセレクトボックスに追加
        list.appendChild(opt);
      }
    }

    // 薬局情報APIを呼び出し、プレビュー下部の薬局表示を更新する関数
    async function fetchPharmacy() {
      // 薬局情報を取得するAPIへGET送信
      const res = await fetch('api.php?path=pharmacy');
      // JSONレスポンスを展開
      const json = await res.json();
      // data未定義時でも安全に扱えるよう空オブジェクトをデフォルト化
      const p = json.data || {};
      // 薬局名をプレビューへ反映
      document.getElementById('preview-pharmacy-name').textContent =
        `${p.pharmacy_name ?? ''}`;
      // 住所をプレビューへ反映
      document.getElementById('preview-pharmacy-address').textContent =
        `${p.address ?? ''}`;
      // 電話・FAXを1行で整形してプレビューへ反映
      document.getElementById('preview-pharmacy-tel-fax').textContent =
        `TEL:${p.phone ?? ''} FAX:${p.fax ?? ''}`;

    }

    // 医薬品種別に応じた配色クラスを印刷プレビュー領域へ適用する関数
    function applyTheme(type) {
      // 対象となるプレビュー領域を取得
      const printArea = document.getElementById('print_area');
      // 既存の種別クラスをいったん全て除去
      printArea.classList.remove('type-internal', 'type-external', 'type-as-needed');
      // 種別に応じて適切なクラスを1つだけ付与
      if (type === 'external') {
        printArea.classList.add('type-external');
      } else if (type === 'as_needed') {
        printArea.classList.add('type-as-needed');
      } else {
        printArea.classList.add('type-internal');
      }
    }

    // 入力フォームの値を読み取り、薬袋プレビュー表示を同期する関数
    function syncPreview() {
      // [ADDED] この関数は保存を行わず、入力中の値を表示専用DOMへ反映するのみ（副作用は画面更新）。
      // 患者名入力値を取得
      const patient = document.getElementById('patient_name').value;
      // 選択中の医薬品種別を取得
      const type = document.getElementById('medicine_type').value;
      // 種別に応じた配色を反映
      applyTheme(type);
      // 種別見出しを更新
      document.getElementById('preview_type').textContent = typeLabel[type] ?? type;
      // 患者名は「様」を付けて表示（未入力なら空表示）
      document.getElementById('preview_patient').textContent = patient ? `${patient}様` : '';
      // 用法ラベルは固定文言
      document.getElementById('preview_usage').textContent = '用法';
      // 用法・用量を1行に結合して表示
      document.getElementById('preview_amount').textContent = `${document.getElementById('dosage_usage').value} ${document.getElementById('dosage_amount').value}`.trim();
      // 1日回数を表示
      document.getElementById('preview_freq').textContent = document.getElementById('daily_frequency').value;
      // 医薬品名入力値を一時変数へ保持
      const medicineName = document.getElementById('medicine_name').value;
      // 医薬品名は先頭に中黒を付けて表示（未入力なら空表示）
      document.getElementById('preview_name').textContent = medicineName ? `・${medicineName}` : '';
      // 説明文をそのまま表示
      document.getElementById('preview_desc').textContent = document.getElementById('description').value;
    }

    // 検索ボタン押下時、キーワードで医薬品検索を実行
    document.getElementById('search_btn').addEventListener('click', () => {
      fetchMedicines(document.getElementById('search_keyword').value);
    });

    // 検索結果の候補選択時、選択した医薬品情報をフォームへ自動反映
    document.getElementById('medicine_list').addEventListener('change', (e) => {
      // 現在選択されているoption要素を取得
      const selected = e.target.options[e.target.selectedIndex];
      // データ属性がなければ何もしない（安全ガード）
      if (!selected || !selected.dataset.item) return;
      // data属性のJSON文字列をオブジェクトへ復元
      const item = JSON.parse(selected.dataset.item);
      // [ADDED] 注意: dataset.item はJSON文字列で保持されるため、壊れた文字列が入るとJSON.parseで例外になり得る（例外処理は未実装）。
      // フォーム各項目へ選択レコードを反映
      document.getElementById('medicine_type').value = item.medicine_type;
      document.getElementById('dosage_usage').value = item.dosage_usage;
      document.getElementById('dosage_amount').value = item.dosage_amount;
      document.getElementById('daily_frequency').value = item.daily_frequency;
      document.getElementById('medicine_name').value = item.medicine_name;
      document.getElementById('description').value = item.description || '';
      // 反映後の内容でプレビュー表示も同期
      syncPreview();
    });

    // プレビュー連動対象の入力項目ID一覧を定義
    for (const id of ['patient_name', 'medicine_type', 'dosage_usage', 'dosage_amount', 'daily_frequency', 'medicine_name', 'description']) {
      // テキスト入力中のリアルタイム反映
      document.getElementById(id).addEventListener('input', syncPreview);
      // セレクト変更時などchangeイベントでも反映
      document.getElementById(id).addEventListener('change', syncPreview);
    }

    // 初期表示時に医薬品候補を取得
    fetchMedicines();
    // 初期表示時に薬局情報を取得
    fetchPharmacy();
    // 初期表示時に空状態のプレビューを描画
    syncPreview();
  </script>
</body>
</html>
