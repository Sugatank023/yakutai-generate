-- =============================================================================
-- schema.sql — データベーススキーマ定義ファイル
-- =============================================================================
--
-- 薬袋印刷WEBシステムで使用するSQLiteデータベースのテーブル定義ファイル。
-- Database.php の initializeSchema() メソッドから読み込まれ、
-- 毎回のDB接続時に実行される。
--
-- CREATE TABLE IF NOT EXISTS を使用しているため、
-- テーブルが既に存在する場合はスキップされる（冪等な処理）。
--
-- テーブル構成:
--   1. medicines  — 医薬品マスタ（薬袋作成時に参照する医薬品情報）
--   2. pharmacies — 薬局情報（薬袋の下部に印刷される薬局の基本情報）
-- =============================================================================

-- =============================================================================
-- medicines テーブル — 医薬品マスタ
-- =============================================================================
-- 薬袋作成時に検索・選択される医薬品情報を格納するテーブル。
-- admin.php の管理画面から登録・編集・削除が可能。
-- index.php の検索機能から名前で部分一致検索される。
-- =============================================================================
CREATE TABLE IF NOT EXISTS medicines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,   -- 一意識別子（自動採番）
    medicine_name TEXT NOT NULL,            -- 医薬品名（例: ロキソニン錠60mg）
    medicine_type TEXT NOT NULL,            -- 医薬品種別キー（external/internal/kampo/as_needed）
    dosage_usage TEXT NOT NULL,             -- 用法（例: 食後、就寝前）
    dosage_amount TEXT NOT NULL,            -- 用量（例: 1錠、2カプセル）
    daily_frequency TEXT NOT NULL,          -- 1日の服用回数（例: 1日3回）
    description TEXT DEFAULT '',            -- 医薬品の説明・注意事項（任意項目）
    created_at TEXT NOT NULL,               -- レコード作成日時（ISO 8601形式）
    updated_at TEXT NOT NULL                -- レコード更新日時（ISO 8601形式）
);

-- 医薬品名に対するインデックス（名前検索の高速化のため）
-- searchByName() メソッドの LIKE 検索パフォーマンスを向上させる
CREATE INDEX IF NOT EXISTS idx_medicines_name ON medicines (medicine_name);

-- 医薬品種別に対するインデックス（種別での絞り込みの高速化のため）
CREATE INDEX IF NOT EXISTS idx_medicines_type ON medicines (medicine_type);

-- =============================================================================
-- pharmacies テーブル — 薬局情報
-- =============================================================================
-- 薬袋の下部に印刷される薬局の基本情報を格納するテーブル。
-- このテーブルは実質1レコードのみで運用される設計。
-- pharmacy.php の設定画面から編集可能。
-- 初回起動時に Database.php の initializeSchema() がサンプルデータを1件投入する。
-- =============================================================================
CREATE TABLE IF NOT EXISTS pharmacies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,   -- 一意識別子（自動採番）
    pharmacy_name TEXT NOT NULL,            -- 薬局名（例: ○○薬局）
    address TEXT NOT NULL,                  -- 住所（例: 東京都○○区1-2-3）
    phone TEXT NOT NULL,                    -- 電話番号（例: 03-1234-5678）
    fax TEXT NOT NULL,                      -- FAX番号（例: 03-1234-5679）
    created_at TEXT NOT NULL,               -- レコード作成日時（ISO 8601形式）
    updated_at TEXT NOT NULL                -- レコード更新日時（ISO 8601形式）
);
