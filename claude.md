# 薬袋印刷WEBシステム — システム解析ドキュメント

> 文書生成日: 2026-02-26  
> 対象リポジトリ: `yakutai-generate`

---

## 1. システム概要

本システムは、**薬局で使用する薬袋（A5サイズ）をWebブラウザ上で作成・プレビュー・印刷するためのPHP製Webアプリケーション**です。

### 主な目的

- 患者名を入力し、登録済みの医薬品マスタから用法・用量・回数等を呼び出して薬袋を素早く作成する
- A5用紙に最適化された薬袋レイアウトをブラウザから直接印刷する
- 医薬品マスタと薬局情報を簡単に管理する

### 技術スタック

| 項目 | 技術 |
|------|------|
| サーバーサイド言語 | **PHP 8.x**（`strict_types` 宣言使用） |
| データベース | **SQLite**（`database/app.sqlite`） |
| フロントエンド | **Vanilla JavaScript**（フレームワーク不使用） |
| スタイリング | **Vanilla CSS**（CSS変数によるテーマ管理） |
| Webサーバー | **PHP組み込みサーバー**（`php -S 0.0.0.0:8000 -t public`） |
| テンプレートエンジン | PHPネイティブ（`.php`ファイル内でHTML出力） |
| 外部依存 | **なし**（Composerやnpm不要） |

---

## 2. ディレクトリ構成

```
yakutai-generate/
├── app/                          # アプリケーション層
│   ├── bootstrap.php             # 共通初期化・入力バリデーション関数
│   ├── Database.php              # DB接続管理（シングルトン）
│   ├── MedicineRepository.php    # 医薬品CRUD操作
│   └── PharmacyRepository.php    # 薬局情報取得・更新
├── database/                     # データ層
│   ├── schema.sql                # テーブル定義（DDL）
│   └── app.sqlite                # SQLiteデータベース（実行時自動生成）
├── docs/                         # ドキュメント
│   └── 実装プラン.md              # 実装計画書
├── public/                       # 公開ディレクトリ（Webルート）
│   ├── index.php                 # 薬袋作成画面（メインページ）
│   ├── admin.php                 # 医薬品管理画面
│   ├── pharmacy.php              # 薬局情報設定画面
│   ├── api.php                   # JSON APIエンドポイント
│   └── assets/
│       └── css/
│           └── app.css           # 共通スタイルシート
├── README.md                     # 起動方法の簡易説明
├── gpt.md                        # 別途作成されたシステム解析レポート
└── .gitignore
```

### 各ディレクトリの役割

| ディレクトリ | 役割 |
|-------------|------|
| `app/` | ビジネスロジック・DB接続・バリデーション。画面やAPIから共通利用される |
| `database/` | スキーマ定義とSQLiteファイル。初回アクセス時にDBファイルが自動生成される |
| `public/` | Webサーバーのドキュメントルート。画面・API・静的アセットを配置 |
| `docs/` | 開発者向けドキュメント |

---

## 3. 起動方法と初期化フロー

### 3.1 起動コマンド

```bash
php -S 0.0.0.0:8000 -t public
```

各画面のURL:

- 薬袋作成: `http://localhost:8000/index.php`
- 医薬品管理: `http://localhost:8000/admin.php`
- 薬局情報設定: `http://localhost:8000/pharmacy.php`

### 3.2 自動初期化フロー

初回アクセス時に以下のプロセスが自動実行されます:

```
ブラウザからアクセス
    │
    ▼
public/*.php → app/bootstrap.php を require
    │
    ▼
Database::connection() 実行
    │
    ├─ database/ ディレクトリが無ければ作成
    ├─ SQLite接続を確立 (database/app.sqlite)
    ├─ PDO例外モード + FETCH_ASSOC を設定
    ├─ schema.sql を実行 (CREATE TABLE IF NOT EXISTS で冪等)
    └─ pharmacies テーブルが0件なら サンプル薬局データを1件INSERT
    │
    ▼
画面表示 or API応答
```

**ポイント**: `CREATE TABLE IF NOT EXISTS` を使用しているため、2回目以降のアクセスではスキーマ実行がスキップされ、安全に動作します。

---

## 4. データベース設計

### 4.1 medicines テーブル（医薬品マスタ）

| カラム名 | 型 | 制約 | 説明 |
|----------|------|------|------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | 一意識別子 |
| `medicine_name` | TEXT | NOT NULL | 医薬品名 |
| `medicine_type` | TEXT | NOT NULL | 種別キー（`external` / `internal` / `kampo` / `as_needed`） |
| `dosage_usage` | TEXT | NOT NULL | 用法（例: 食後） |
| `dosage_amount` | TEXT | NOT NULL | 用量（例: 1錠） |
| `daily_frequency` | TEXT | NOT NULL | 1日の服用回数（例: 1日3回） |
| `description` | TEXT | DEFAULT '' | 医薬品の説明文 |
| `created_at` | TEXT | NOT NULL | 登録日時（ISO 8601） |
| `updated_at` | TEXT | NOT NULL | 更新日時（ISO 8601） |

**インデックス**:
- `idx_medicines_name` — `medicine_name` カラムに対するインデックス（名前検索の高速化）
- `idx_medicines_type` — `medicine_type` カラムに対するインデックス

### 4.2 pharmacies テーブル（薬局情報）

| カラム名 | 型 | 制約 | 説明 |
|----------|------|------|------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | 一意識別子 |
| `pharmacy_name` | TEXT | NOT NULL | 薬局名 |
| `address` | TEXT | NOT NULL | 住所 |
| `phone` | TEXT | NOT NULL | 電話番号 |
| `fax` | TEXT | NOT NULL | FAX番号 |
| `created_at` | TEXT | NOT NULL | 登録日時 |
| `updated_at` | TEXT | NOT NULL | 更新日時 |

> **運用上の特徴**: pharmacies テーブルは実質 **1レコードのみ** で運用される設計です。`PharmacyRepository::get()` は `LIMIT 1` で先頭の1件だけを取得します。

### 4.3 医薬品種別の定義

| キー | 表示名 | UI上の表示 |
|------|--------|-----------|
| `internal` | 内服薬 | ✅ 選択可能 |
| `external` | 外用薬 | ✅ 選択可能 |
| `as_needed` | 頓服薬 | ✅ 選択可能 |
| `kampo` | 漢方薬 | ❌ コード定義あり・UI非表示（`continue`で除外） |

---

## 5. アプリケーション層の詳細

### 5.1 bootstrap.php — 共通初期化

```
機能:
├─ require_once で Database.php / MedicineRepository.php / PharmacyRepository.php を読み込み
├─ medicineTypes()      … 医薬品種別のキー→表示名マッピング配列を返却
├─ validateMedicineInput()  … 医薬品入力の必須チェック + 種別妥当性チェック
└─ validatePharmacyInput()  … 薬局情報の必須チェック
```

**バリデーション仕様**:

- `validateMedicineInput()`: `medicine_name`, `medicine_type`, `dosage_usage`, `dosage_amount`, `daily_frequency` が必須。`medicine_type` は `medicineTypes()` のキーに存在する値のみ許可
- `validatePharmacyInput()`: `pharmacy_name`, `address`, `phone`, `fax` が必須
- いずれも不正な場合は `InvalidArgumentException` をスロー

### 5.2 Database.php — DB接続管理

```
Database（finalクラス）
├─ $pdo: ?PDO（静的プロパティ、シングルトン）
├─ connection(): PDO
│   ├─ 既に接続済みならそのまま返却
│   ├─ database/ ディレクトリを確認/作成
│   ├─ SQLite接続を確立
│   ├─ PDO属性を設定（ERRMODE_EXCEPTION, FETCH_ASSOC）
│   └─ initializeSchema() を呼び出し
└─ initializeSchema(PDO): void
    ├─ schema.sql を読み込んで実行
    └─ pharmacies が0件ならサンプルデータをINSERT
```

### 5.3 MedicineRepository.php — 医薬品リポジトリ

| メソッド | 処理内容 |
|----------|----------|
| `searchByName(string $keyword = '')` | キーワード空なら全件取得、あれば `LIKE` 部分一致検索。名前昇順でソート |
| `find(int $id)` | IDで1件取得。見つからなければ `null` を返却 |
| `create(array $data)` | 新規INSERT。`created_at` / `updated_at` に現在時刻を設定。挿入IDを返却 |
| `update(int $id, array $data)` | 指定IDのレコードを更新。`updated_at` を現在時刻に更新 |
| `delete(int $id)` | 指定IDのレコードを削除 |

### 5.4 PharmacyRepository.php — 薬局リポジトリ

| メソッド | 処理内容 |
|----------|----------|
| `get()` | `pharmacies` テーブルの先頭1件を取得。レコードがなければ空配列を返却 |
| `update(array $data)` | 既存レコードがなければINSERT、あればUPDATE（Upsertパターン） |

---

## 6. 画面仕様

### 6.1 薬袋作成画面（`public/index.php`）— メイン画面

**画面レイアウト**: 左右2カラム構成

| 左カラム（入力パネル） | 右カラム（プレビュー） |
|----------------------|---------------------|
| 患者名入力 | 薬袋A5プレビュー |
| 医薬品種別選択 | リアルタイム反映 |
| 医薬品名検索 → 検索結果リスト | 薬局情報の表示 |
| 用法・用量・1日回数・医薬品名・説明 | 種別に応じたテーマカラー |
| 管理画面・設定画面へのリンク | |
| 印刷ボタン | |

**フロントエンド動作**:

1. **ページ読み込み時**: `fetchMedicines()` で全医薬品を取得し検索結果リストに表示。`fetchPharmacy()` で薬局情報を取得しプレビュー下部に表示
2. **医薬品検索**: 検索ボタンクリックで `api.php?path=medicines&keyword=...` に非同期リクエスト
3. **検索結果選択**: `<select>` の `change` イベントで選択した医薬品のデータをフォームに自動反映
4. **リアルタイムプレビュー**: 全入力フィールドの `input` / `change` イベントで `syncPreview()` を実行し、右カラムのプレビューを即時更新
5. **テーマ切替**: `applyTheme(type)` で薬種別に応じたCSSクラス（`type-internal` / `type-external` / `type-as-needed`）を付与し、テーマカラーを変更
6. **印刷**: `window.print()` の呼び出しで、CSSの`@media print`ルールにより入力パネルが非表示となり、薬袋プレビュー部分のみがA5で出力される

### 6.2 医薬品管理画面（`public/admin.php`）

**機能一覧**:

- **検索**: GETパラメータ `keyword` で医薬品名を部分一致検索
- **登録**: フォーム送信（POST、`action=create`）で新規医薬品を登録
- **編集**: 一覧の「編集」リンクで `?edit={id}` に遷移 → フォームに既存値がプリセットされ、送信で更新（`action=update`）
- **削除**: 一覧の「削除」ボタンで確認ダイアログ後に削除（`action=delete`）
- **一覧表示**: テーブル形式で ID・医薬品名・種別・用法・用量・1日回数・操作ボタンを表示

**サーバーサイド処理フロー**:

```
POST受信
    │
    ├─ action=delete → MedicineRepository::delete()
    ├─ action=update → validateMedicineInput() → MedicineRepository::update()
    └─ action=create → validateMedicineInput() → MedicineRepository::create()
    │
    ▼
例外発生時はエラーメッセージを表示（Throwableキャッチ）
```

### 6.3 薬局情報設定画面（`public/pharmacy.php`）

- 薬局名・住所・電話番号・FAX番号を編集可能
- POST送信で `validatePharmacyInput()` → `PharmacyRepository::update()` を実行
- 成功時/失敗時にメッセージを画面表示
- 最大幅680pxに制限された単カラムレイアウト

---

## 7. APIエンドポイント（`public/api.php`）

すべてのレスポンスは `Content-Type: application/json; charset=utf-8` で返却されます。

### 7.1 医薬品API

| メソッド | パス | 処理 | レスポンス |
|----------|------|------|-----------|
| `GET` | `?path=medicines&keyword=` | 医薬品検索（キーワード空なら全件） | `{"data": [...]}` |
| `POST` | `?path=medicines` | 医薬品新規登録 | `{"id": 新規ID}` (201) |
| `PUT` | `?path=medicines/{id}` | 医薬品更新 | `{"updated": true}` |
| `DELETE` | `?path=medicines/{id}` | 医薬品削除 | `{"deleted": true}` |

### 7.2 薬局API

| メソッド | パス | 処理 | レスポンス |
|----------|------|------|-----------|
| `GET` | `?path=pharmacy` | 薬局情報取得 | `{"data": {...}}` |
| `PUT` | `?path=pharmacy` | 薬局情報更新 | `{"updated": true}` |

### 7.3 エラーハンドリング

| HTTPステータス | 条件 | レスポンス |
|---------------|------|-----------|
| `404` | 未定義のルート | `{"error": "Not found"}` |
| `422` | 入力バリデーションエラー（`InvalidArgumentException`） | `{"error": "エラーメッセージ"}` |
| `500` | その他の例外（`Throwable`） | `{"error": "エラーメッセージ"}` |

### 7.4 ルーティングの仕組み

APIルーティングはフレームワークを使用せず、`$_GET['path']` の値と `$_SERVER['REQUEST_METHOD']` のif文分岐で実装されています。`medicines/{id}` のようなパスパラメータは正規表現 `#^medicines/(\d+)$#` でマッチング・抽出します。`PUT` / `DELETE` リクエストのボディは `php://input` から読み取ります。

---

## 8. スタイリングと印刷設計（`app.css`）

### 8.1 デザインコンセプト

**「和モダン」** をコンセプトとしたUIデザインです。

- **カラーパレット**: クリーム系背景（`#f4efe5`）に濃い茶系テキスト（`#2f241a`）、渋いレンガ色のアクセント（`#8c3d2f`）
- **フォント**: 見出しに明朝体（`Hiragino Mincho ProN`, `Yu Mincho`）、本文にゴシック体（`Hiragino Kaku Gothic ProN`, `Yu Gothic`）
- **視覚効果**: 背景のラジアルグラデーション、カードのバックドロップフィルター（`blur`）、ボタンのホバーアニメーション

### 8.2 CSS変数（テーマトークン）

```css
--bg-main: #f4efe5       /* メイン背景色 */
--bg-soft: #fcfaf5       /* ソフト背景色 */
--card-bg: rgba(255,255,255,0.92)  /* カード背景（半透明） */
--brand: #8c3d2f         /* ブランドカラー */
--accent-blue: #1f57a5   /* 内服薬テーマ */
--accent-red: #b1282f    /* 外用薬テーマ */
--accent-green: #2f8a3a  /* 頓服薬テーマ */
```

### 8.3 薬袋プレビューのレイアウト

- サイズ: `148mm × 210mm`（A5相当）
- 二重線の上下装飾ライン（`::before` / `::after` 疑似要素）
- 中央に薬種別タイトル → 患者名 → 用法・用量ボックス → 医薬品名・説明 → 薬局情報
- 医薬品種別ごとのテーマカラー切替:
  - `.type-internal` → 青（`--accent-blue`）: 内服薬
  - `.type-external` → 赤（`--accent-red`）: 外用薬
  - `.type-as-needed` → 緑（`--accent-green`）: 頓服薬

### 8.4 印刷用スタイル

```
@page { size: A5; margin: 0; }

@media print:
  - .no-print クラスの要素を非表示（入力パネル等）
  - body の余白・背景をリセット
  - .layout のグリッドをブロック化
  - .print-area の角丸・影を除去し、純粋な148mm×210mmで出力
```

### 8.5 レスポンシブ対応

```
@media (max-width: 1050px):
  - body の padding を縮小
  - 2カラムレイアウトを1カラムに切替
```

---

## 9. 代表的な処理シーケンス

### 9.1 薬袋の作成から印刷まで

```
① index.php にアクセス
② ページロード時に fetchMedicines() + fetchPharmacy() で非同期取得
③ 患者名を入力（リアルタイムにプレビュー反映）
④ 医薬品種別を選択（テーマカラーが切り替わる）
⑤ 検索キーワードを入力 → 検索ボタンで候補を更新
⑥ 候補リストから医薬品を選択 → 用法・用量等がフォームに自動展開
⑦ 必要に応じて手動で編集（プレビューがリアルタイム更新）
⑧ 印刷ボタン → window.print() → A5サイズで薬袋のみ印刷
```

### 9.2 医薬品マスタの登録

```
① admin.php にアクセス
② フォームに医薬品情報を入力
③ 「登録する」ボタンでPOST送信
④ サーバー側で validateMedicineInput() → MedicineRepository::create()
⑤ 結果メッセージの表示 + 一覧の更新
```

### 9.3 薬局情報の変更

```
① pharmacy.php にアクセス
② 現在の薬局情報がフォームにプリセット表示
③ 編集して「保存」ボタンでPOST送信
④ サーバー側で validatePharmacyInput() → PharmacyRepository::update()
⑤ 結果メッセージの表示
```

---

## 10. セキュリティ・設計上の特徴

### 安全対策（実装済み）

- **SQLインジェクション対策**: 全SQLクエリでPDOプリペアドステートメントを使用
- **XSS対策**: 全出力箇所で `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` を適用
- **入力バリデーション**: サーバーサイドで必須チェック・値妥当性チェックを実施
- **例外ハンドリング**: API・画面ともにTry-Catchで例外を捕捉し、ユーザーフレンドリーなメッセージを表示

### 設計上の特徴

- **ゼロ依存**: Composer / npm / 外部ライブラリ不要。PHPとブラウザだけで動作
- **自動セットアップ**: 初回アクセスでDB・テーブル・サンプルデータが自動生成
- **冪等な初期化**: `CREATE TABLE IF NOT EXISTS` により、繰り返し実行しても安全
- **シングルトンDB接続**: リクエスト内で一度だけ接続を確立し再利用
- **APIと画面の同居**: 小規模運用を想定し、同一ディレクトリにAPIと画面を配置

---

## 11. ファイル一覧と行数

| ファイルパス | 行数 | サイズ | 役割 |
|-------------|------|--------|------|
| `app/bootstrap.php` | 59行 | 1.9KB | 共通初期化・バリデーション |
| `app/Database.php` | 57行 | 1.8KB | DB接続管理 |
| `app/MedicineRepository.php` | 81行 | 3.0KB | 医薬品CRUD |
| `app/PharmacyRepository.php` | 54行 | 1.7KB | 薬局情報管理 |
| `database/schema.sql` | 25行 | 0.8KB | テーブル定義 |
| `public/index.php` | 174行 | 7.0KB | メイン画面（薬袋作成） |
| `public/admin.php` | 120行 | 5.6KB | 医薬品管理画面 |
| `public/pharmacy.php` | 61行 | 2.2KB | 薬局設定画面 |
| `public/api.php` | 75行 | 2.4KB | JSON API |
| `public/assets/css/app.css` | 316行 | 6.1KB | スタイルシート |

**合計**: 約1,022行 / 約32.5KB（コード部分のみ）

---

## 12. まとめ

本システムは、**薬袋作成業務に必要な最小機能を、PHP + SQLite で実用的にまとめた軽量Webアプリケーション**です。

- 「患者名は毎回手入力」「医薬品マスタを再利用」「A5印刷に最適化」という主要ワークフローを実現
- 管理画面・API・リアルタイムプレビューが一体化された構成
- フレームワーク不使用で約1,000行というコンパクトな実装
- 初回アクセスで完全自動セットアップされ、即座に利用可能
