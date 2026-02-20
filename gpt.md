# 薬袋印刷WEBシステム 全体解析レポート

## 1. システム概要
このシステムは、**薬袋（A5）を作成・プレビュー・印刷するためのPHP製Webアプリ**です。  
主な目的は、患者名を都度入力しつつ、医薬品マスタから用法・用量などを呼び出して薬袋を素早く作成することです。

- 技術スタック: **PHP + JavaScript + SQLite**
- 実行形態: PHP組み込みサーバー (`php -S 0.0.0.0:8000 -t public`)
- 主要画面:
  - 薬袋作成 (`public/index.php`)
  - 医薬品管理 (`public/admin.php`)
  - 薬局情報設定 (`public/pharmacy.php`)
- APIエンドポイント: `public/api.php`

---

## 2. ディレクトリ構成と責務

- `public/`
  - 画面エントリポイント（`index.php`, `admin.php`, `pharmacy.php`）
  - API公開口（`api.php`）
  - スタイル（`assets/css/app.css`）
- `app/`
  - インフラ層（DB接続: `Database.php`）
  - リポジトリ層（`MedicineRepository.php`, `PharmacyRepository.php`）
  - 共通初期化・入力バリデーション（`bootstrap.php`）
- `database/`
  - スキーマ（`schema.sql`）
  - 実行時にSQLiteファイル (`app.sqlite`) が生成される
- `docs/`
  - 実装計画書（`実装プラン.md`）

現在の実装は、厳密なMVCというより、**軽量なPHPページ + リポジトリ + API**構成です。

---

## 3. 起動～初期化フロー

1. `public/*.php` から `app/bootstrap.php` を読み込む。
2. `Database::connection()` 実行時に SQLite 接続を確立。
3. `database/` ディレクトリがなければ作成。
4. `database/schema.sql` を毎回 `exec`（`CREATE TABLE IF NOT EXISTS` なので冪等）。
5. `pharmacies` が0件の場合、サンプル薬局データを1件投入。

これにより、初回起動時にDB初期化が自動完了する設計です。

---

## 4. データモデル（SQLite）

### 4.1 medicines テーブル
医薬品マスタを保持。

- `id` (PK)
- `medicine_name`
- `medicine_type`（`external` / `internal` / `kampo` / `as_needed`）
- `dosage_usage`（用法）
- `dosage_amount`（用量）
- `daily_frequency`（1日回数）
- `description`（説明）
- `created_at` / `updated_at`

インデックス:
- `idx_medicines_name`
- `idx_medicines_type`

### 4.2 pharmacies テーブル
薬局情報を保持（実質1レコード運用）。

- `id` (PK)
- `pharmacy_name`
- `address`
- `phone`
- `fax`
- `created_at` / `updated_at`

---

## 5. アプリケーション層の仕組み

### 5.1 共通関数 (`app/bootstrap.php`)
- `medicineTypes()` で医薬品種別の表示名マップを返却。
- `validateMedicineInput()` で医薬品入力の必須チェックと種別妥当性チェック。
- `validatePharmacyInput()` で薬局情報の必須チェック。

### 5.2 DBアクセス (`app/Database.php`)
- PDOシングルトン接続（静的プロパティで再利用）。
- 例外モード + FETCH_ASSOC を指定。
- スキーマ初期化とサンプル薬局投入を内包。

### 5.3 医薬品リポジトリ (`app/MedicineRepository.php`)
- `searchByName()`：キーワード検索（`LIKE`）または全件取得。
- `find()`：ID単体取得。
- `create()`：新規登録。
- `update()`：更新。
- `delete()`：削除。

### 5.4 薬局リポジトリ (`app/PharmacyRepository.php`)
- `get()`：先頭1件を取得。
- `update()`：
  - レコードなし時は INSERT
  - 既存あり時は UPDATE

---

## 6. 画面仕様とフロントエンド動作

## 6.1 薬袋作成画面 (`public/index.php`)
- 入力: 患者名、医薬品種別、検索キーワード、用法/用量/回数/名称/説明
- 操作:
  - 医薬品検索 (`api.php?path=medicines&keyword=...`)
  - 検索結果選択でフォーム自動反映
  - 印刷 (`window.print()`)
- プレビュー:
  - A5相当の `print-area` にリアルタイム反映
  - 薬局情報をAPIから取得して下部表示
  - 種別に応じてテーマクラス切替

> 補足: `medicineTypes()` には `kampo` が存在しますが、`index.php` と `admin.php` のプルダウンでは `kampo` を `continue` で除外しており、UI上は選択できない実装です（既存データ表示時はラベル変換自体は可能）。

### 6.2 医薬品管理画面 (`public/admin.php`)
- 検索、一覧表示、登録、編集、削除を1ページで実施。
- POSTの `action` に応じて `create/update/delete` を分岐。
- 例外をキャッチしメッセージ表示。

### 6.3 薬局情報設定画面 (`public/pharmacy.php`)
- 薬局名・住所・電話・FAXを編集保存。
- 保存時は `PharmacyRepository::update()` を実行。

### 6.4 API (`public/api.php`)
実装済みルート:
- `GET ?path=medicines&keyword=`：医薬品検索
- `POST ?path=medicines`：医薬品登録
- `PUT ?path=medicines/{id}`：医薬品更新
- `DELETE ?path=medicines/{id}`：医薬品削除
- `GET ?path=pharmacy`：薬局情報取得
- `PUT ?path=pharmacy`：薬局情報更新

エラーハンドリング:
- 入力エラー: 422 (`InvalidArgumentException`)
- その他: 500
- 未定義ルート: 404

---

## 7. スタイリング・印刷の仕組み

`public/assets/css/app.css` は以下を実装しています。

- 和モダンのUIテーマ（色変数、カード、グラデーション背景）
- 管理画面向けのフォーム／テーブルスタイル
- 薬袋プレビューのレイアウト
- `@media print` と `@page` による印刷最適化（UI非表示・A5固定）
- 種別クラス（内服/外用/頓服）による色調切替

これにより、通常画面では編集しやすく、印刷時は薬袋本体だけを安定出力する方針です。

---

## 8. 処理シーケンス（代表例）

### 8.1 薬袋を作って印刷
1. `index.php` 表示
2. 初期ロード時に医薬品一覧と薬局情報をAPI取得
3. 検索キーワード入力 → 検索ボタンで候補更新
4. 候補選択でフォーム項目に値を展開
5. 入力変更のたび `syncPreview()` でプレビュー更新
6. 印刷ボタンでブラウザ印刷

### 8.2 医薬品を登録
1. `admin.php` で入力して送信
2. サーバー側で `validateMedicineInput()`
3. `MedicineRepository::create()` でINSERT
4. 完了メッセージ表示

### 8.3 薬局情報を更新
1. `pharmacy.php` で入力して送信
2. `validatePharmacyInput()` で必須チェック
3. `PharmacyRepository::update()` でINSERTまたはUPDATE
4. 完了メッセージ表示

---

## 9. 現状実装の特徴と留意点

- **導入が簡単**: 初回アクセスでDB自動生成・初期データ投入。
- **シンプル構成**: フレームワーク非依存で理解しやすい。
- **APIと画面が同居**: 小規模運用向けに実装コストを抑制。
- **拡張余地**:
  - `kampo` のUI公開可否を要件に応じて見直し
  - 電話/FAXの形式チェック強化
  - CSRF対策、認証・認可、監査ログ等の運用機能追加

---

## 10. 設計意図と実装プランとの整合

`docs/実装プラン.md` の要件（A5印刷、医薬品検索・CRUD、薬局情報管理、段階的開発）は、概ね現在の実装に反映されています。  
特に以下は一致度が高いです。

- A5薬袋プレビュー/印刷
- 医薬品検索と管理
- 薬局情報の別テーブル管理
- サーバー側入力検証

一方で、運用品質の面では、将来拡張候補（履歴保存、CSV取込等）やセキュリティ強化項目を追加実装する余地があります。

---

## 11. まとめ
本システムは、**薬袋作成業務に必要な最小機能を、PHP + SQLite で実用的にまとめた軽量Webアプリ**です。  
「患者名は毎回入力」「医薬品マスタ再利用」「A5印刷最適化」という主要要件を満たし、管理画面・API・プレビュー表示が一体化された構成になっています。
