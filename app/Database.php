<?php

/**
 * Database.php — データベース接続管理クラス
 *
 * SQLiteデータベースへの接続をシングルトンパターンで管理します。
 * リクエスト内で一度だけ接続を確立し、以降は同じ接続を再利用することで
 * パフォーマンスとリソース効率を最適化しています。
 *
 * 初回接続時の自動初期化:
 *   1. database/ ディレクトリがなければ自動作成
 *   2. SQLite接続を確立
 *   3. schema.sql を読み込んでテーブルを作成（CREATE TABLE IF NOT EXISTS で冪等）
 *   4. pharmacies テーブルが空の場合、サンプル薬局データを1件投入
 */

// PHP厳密型宣言
declare(strict_types = 1)
;

/**
 * Database クラス — SQLiteデータベース接続のシングルトン管理
 *
 * final宣言により、このクラスの継承を禁止しています。
 * 静的メソッドのみで構成され、インスタンス化は不要です。
 */
final class Database
{
    /**
     * PDO接続インスタンスを保持する静的プロパティ
     * null: 未接続状態
     * PDO: 接続済み状態
     * シングルトンパターンにより、リクエスト中に一度だけ生成される
     */
    private static ?PDO $pdo = null;

    /**
     * connection — データベース接続を取得するメソッド
     *
     * シングルトンパターンで実装されており、初回呼び出し時にのみ
     * 新規接続を確立します。2回目以降は既存の接続を返却します。
     *
     * 初回接続時の処理フロー:
     *   1. database/ ディレクトリの存在確認・作成
     *   2. SQLite接続の確立（database/app.sqlite）
     *   3. PDO属性の設定（例外モード、連想配列フェッチ）
     *   4. スキーマの初期化（initializeSchema呼び出し）
     *
     * @return PDO データベース接続インスタンス
     */
    public static function connection(): PDO
    {
        // 既に接続が確立されている場合は、そのまま返却（シングルトン）
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        // database/ ディレクトリのパスを構築（このファイルの親ディレクトリ/database）
        $databaseDir = __DIR__ . '/../database';

        // database/ ディレクトリが存在しなければ再帰的に作成
        if (!is_dir($databaseDir)) {
            mkdir($databaseDir, 0777, true);
        }

        // SQLiteデータベースファイルのパスを構築
        $dbPath = $databaseDir . '/app.sqlite';

        // PDO接続を確立（SQLiteドライバ使用）
        self::$pdo = new PDO('sqlite:' . $dbPath);

        // エラーモードを例外モードに設定（SQLエラー時にPDOExceptionをスロー）
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // デフォルトのフェッチモードを連想配列に設定（カラム名をキーとした配列で取得）
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // スキーマの初期化（テーブル作成・サンプルデータ投入）
        self::initializeSchema(self::$pdo);

        // 初期化済みの接続インスタンスを返却
        return self::$pdo;
    }

    /**
     * initializeSchema — データベーススキーマの初期化メソッド
     *
     * schema.sql を読み込んでテーブルを作成し、
     * pharmacies テーブルが空の場合はサンプルデータを投入します。
     *
     * CREATE TABLE IF NOT EXISTS を使用しているため、
     * 既にテーブルが存在する場合は何もしない冪等な処理です。
     *
     * @param PDO $pdo データベース接続インスタンス
     * @throws RuntimeException schema.sql の読み込みに失敗した場合
     */
    private static function initializeSchema(PDO $pdo): void
    {
        // schema.sql ファイルの内容を読み込み
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');

        // ファイル読み込み失敗時は例外をスロー
        if ($schema === false) {
            throw new RuntimeException('schema.sql の読み込みに失敗しました。');
        }

        // SQL文を実行してテーブルを作成（CREATE TABLE IF NOT EXISTS で冪等）
        $pdo->exec($schema);

        // pharmacies テーブルのレコード数を確認
        $count = (int)$pdo->query('SELECT COUNT(*) FROM pharmacies')->fetchColumn();

        // レコードが0件（初回起動時）の場合、サンプル薬局データを投入
        if ($count === 0) {
            // INSERT文を準備（プリペアドステートメントでSQLインジェクション対策）
            $stmt = $pdo->prepare(
                'INSERT INTO pharmacies (pharmacy_name, address, phone, fax, created_at, updated_at)
                 VALUES (:name, :address, :phone, :fax, :created_at, :updated_at)'
            );

            // 現在日時をISO 8601形式で取得
            $now = date('c');

            // サンプル薬局データを投入
            $stmt->execute([
                ':name' => 'サンプル薬局', // 薬局名（初期値）
                ':address' => '東京都サンプル区1-2-3', // 住所（初期値）
                ':phone' => '03-0000-0000', // 電話番号（初期値）
                ':fax' => '03-0000-0001', // FAX番号（初期値）
                ':created_at' => $now, // 作成日時
                ':updated_at' => $now, // 更新日時
            ]);
        }
    }
}
