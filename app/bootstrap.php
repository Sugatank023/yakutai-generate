<?php

/**
 * bootstrap.php — 共通初期化・入力バリデーションファイル
 *
 * このファイルは、全画面（index.php / admin.php / pharmacy.php）および
 * API（api.php）から共通で読み込まれるエントリポイントです。
 *
 * 役割:
 *   - データベース接続クラス（Database.php）の読み込み
 *   - リポジトリクラス（MedicineRepository.php / PharmacyRepository.php）の読み込み
 *   - 医薬品種別の定義（medicineTypes関数）
 *   - 医薬品入力のバリデーション（validateMedicineInput関数）
 *   - 薬局情報入力のバリデーション（validatePharmacyInput関数）
 */

// PHP厳密型宣言: 関数の引数・戻り値の型を厳密にチェックする
declare(strict_types = 1)
;

// データベース接続を管理するシングルトンクラスの読み込み
require_once __DIR__ . '/Database.php';

// 医薬品のCRUD操作を行うリポジトリクラスの読み込み
require_once __DIR__ . '/MedicineRepository.php';

// 薬局情報の取得・更新を行うリポジトリクラスの読み込み
require_once __DIR__ . '/PharmacyRepository.php';

/**
 * medicineTypes — 医薬品種別の定義マップを返す関数
 *
 * システム全体で使用する医薬品種別のキーと日本語表示名の対応を定義します。
 * 注意: 'kampo'（漢方薬）はコード上定義されていますが、
 *       index.php および admin.php のプルダウンでは continue で除外されており、
 *       UI上では選択できない実装になっています。
 *
 * @return array キー（英語識別子）=> 値（日本語表示名）の連想配列
 */
function medicineTypes(): array
{
    return [
        'external' => '外用薬', // 外用薬（塗り薬、貼り薬など）— テーマカラー: 赤
        'internal' => '内服薬', // 内服薬（飲み薬）— テーマカラー: 青
        'kampo' => '漢方薬', // 漢方薬 — 定義のみ、UI非表示
        'as_needed' => '頓服薬', // 頓服薬（必要時に服用）— テーマカラー: 緑
    ];
}

/**
 * validateMedicineInput — 医薬品入力データのバリデーション関数
 *
 * admin.php のフォーム送信時、および api.php のPOST/PUTリクエスト時に
 * 呼び出され、入力データの妥当性を検証します。
 *
 * バリデーション内容:
 *   1. 必須フィールド（medicine_name, medicine_type, dosage_usage, dosage_amount, daily_frequency）の存在・非空チェック
 *   2. medicine_type が medicineTypes() に定義されたキーであるかのチェック
 *
 * @param array $input フォームまたはAPIから受け取った入力データ
 * @return array バリデーション済み・トリム済みのデータ配列
 * @throws InvalidArgumentException 必須項目の欠如、または種別が不正な場合
 */
function validateMedicineInput(array $input): array
{
    // 必須フィールドの定義
    $required = ['medicine_name', 'medicine_type', 'dosage_usage', 'dosage_amount', 'daily_frequency'];

    // 各必須フィールドが存在し、かつ空文字でないことを検証
    foreach ($required as $field) {
        if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
            // 未設定または空の場合は例外をスロー
            throw new InvalidArgumentException($field . ' は必須です。');
        }
    }

    // 医薬品種別の一覧を取得
    $types = medicineTypes();

    // 入力された種別が定義済みのキーに含まれるか検証
    if (!array_key_exists($input['medicine_type'], $types)) {
        throw new InvalidArgumentException('medicine_type が不正です。');
    }

    // バリデーション通過後、各値をトリムして返却
    // description は任意項目のため、未設定の場合は空文字をセット
    return [
        'medicine_name' => trim((string)$input['medicine_name']), // 医薬品名（トリム済み）
        'medicine_type' => (string)$input['medicine_type'], // 医薬品種別キー
        'dosage_usage' => trim((string)$input['dosage_usage']), // 用法（例: 食後）
        'dosage_amount' => trim((string)$input['dosage_amount']), // 用量（例: 1錠）
        'daily_frequency' => trim((string)$input['daily_frequency']), // 1日回数（例: 1日3回）
        'description' => trim((string)($input['description'] ?? '')), // 説明（任意、デフォルト空文字）
    ];
}

/**
 * validatePharmacyInput — 薬局情報入力データのバリデーション関数
 *
 * pharmacy.php のフォーム送信時、および api.php のPUTリクエスト時に
 * 呼び出され、薬局情報の入力データの妥当性を検証します。
 *
 * バリデーション内容:
 *   - 必須フィールド（pharmacy_name, address, phone, fax）の存在・非空チェック
 *
 * @param array $input フォームまたはAPIから受け取った入力データ
 * @return array バリデーション済み・トリム済みのデータ配列
 * @throws InvalidArgumentException 必須項目が欠如している場合
 */
function validatePharmacyInput(array $input): array
{
    // 必須フィールドの定義
    $required = ['pharmacy_name', 'address', 'phone', 'fax'];

    // 各必須フィールドが存在し、かつ空文字でないことを検証
    foreach ($required as $field) {
        if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
            // 未設定または空の場合は例外をスロー
            throw new InvalidArgumentException($field . ' は必須です。');
        }
    }

    // バリデーション通過後、各値をトリムして返却
    return [
        'pharmacy_name' => trim((string)$input['pharmacy_name']), // 薬局名
        'address' => trim((string)$input['address']), // 住所
        'phone' => trim((string)$input['phone']), // 電話番号
        'fax' => trim((string)$input['fax']), // FAX番号
    ];
}
