<?php

// [ADDED] ファイル役割: path クエリと REQUEST_METHOD の組み合わせで分岐し、JSONを返すAPIエンドポイント。
// [ADDED] 入出力: 入力は GET/POST/PUT/DELETE（PUT/DELETEは php://input を parse_str）、出力は header() + json_encode() + 必要に応じて http_response_code()/exit。
// [ADDED] セキュリティ注意: 認証・認可チェックはこのファイルに実装されていないため、誰が更新系APIを呼べるかは不明（要確認）。

/**
 * api.php — JSON APIエンドポイント
 *
 * 薬袋作成画面（index.php）のフロントエンドJavaScriptから
 * 非同期で呼び出されるJSON APIです。
 *
 * 全レスポンスは Content-Type: application/json; charset=utf-8 で返却されます。
 * JSONは日本語をエスケープせず出力します（JSON_UNESCAPED_UNICODE）。
 *
 * 実装済みルート:
 *   [医薬品関連]
 *   - GET    ?path=medicines&keyword=  : 医薬品検索（キーワード空なら全件）
 *   - POST   ?path=medicines           : 医薬品新規登録
 *   - PUT    ?path=medicines/{id}      : 医薬品更新
 *   - DELETE ?path=medicines/{id}      : 医薬品削除
 *
 *   [薬局関連]
 *   - GET    ?path=pharmacy            : 薬局情報取得
 *   - PUT    ?path=pharmacy            : 薬局情報更新
 *
 * エラーハンドリング:
 *   - 404: 未定義のルート
 *   - 422: 入力バリデーションエラー（InvalidArgumentException）
 *   - 500: その他の例外
 *
 * ルーティングの仕組み:
 *   フレームワークを使用せず、$_GET['path'] と $_SERVER['REQUEST_METHOD'] の
 *   if文分岐で実装。パスパラメータは正規表現でマッチング・抽出。
 */

// PHP厳密型宣言
declare(strict_types = 1)
;

// 共通初期化ファイルの読み込み（DB接続、リポジトリ、バリデーション関数）
require_once __DIR__ . '/../app/bootstrap.php';

// レスポンスのContent-TypeをJSONに設定
header('Content-Type: application/json; charset=utf-8');

// データベース接続を取得（シングルトン）
$pdo = Database::connection();

// 医薬品リポジトリのインスタンスを生成（DI: PDO接続を注入）
$medicines = new MedicineRepository($pdo);

// 薬局リポジトリのインスタンスを生成（DI: PDO接続を注入）
$pharmacy = new PharmacyRepository($pdo);

// HTTPリクエストメソッドを取得（GET / POST / PUT / DELETE）
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ルーティング用のパスパラメータを取得（例: 'medicines', 'pharmacy', 'medicines/1'）
$path = $_GET['path'] ?? '';

// --- ルーティング処理（try-catchで例外をハンドリング） ---
try {
    // [ADDED] 処理フロー: ルート判定 -> メソッド判定 -> バリデーション関数 -> Repository実行 -> JSON返却。
    // =========================================================================
    // 医薬品一覧・新規登録ルート（?path=medicines）
    // =========================================================================
    if ($path === 'medicines') {

        // --- GET: 医薬品検索 ---
        // 利用元: index.php の fetchMedicines() 関数（非同期取得）
        if ($method === 'GET') {
            // 検索キーワードを取得（未指定時は空文字 = 全件取得）
            $keyword = trim((string)($_GET['keyword'] ?? ''));

            // 検索結果をJSON形式で出力
            echo json_encode(['data' => $medicines->searchByName($keyword)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // --- POST: 医薬品新規登録 ---
        if ($method === 'POST') {
            // POSTデータをバリデーション
            $data = validateMedicineInput($_POST);

            // バリデーション済みデータでINSERT実行
            $id = $medicines->create($data);

            // HTTPステータス201（Created）を設定
            http_response_code(201);

            // 挿入されたIDをJSON形式で出力
            echo json_encode(['id' => $id], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // =========================================================================
    // 医薬品個別操作ルート（?path=medicines/{id}）
    // =========================================================================
    // パスからIDを正規表現で抽出（例: 'medicines/123' → $matches[1] = '123'）
    // [ADDED] パスパラメータ抽出: medicines/{id} の {id} は正規表現で数値のみ許可。
    if (preg_match('#^medicines/(\d+)$#', $path, $matches) === 1) {
        // マッチしたIDを整数に変換
        $id = (int)$matches[1];

        // PUT/DELETEリクエストのボディをphp://inputから読み取り、パース
        // （PHPはPUT/DELETEのボディを$_POSTに自動格納しないため手動で処理）
        parse_str(file_get_contents('php://input'), $raw);

        // --- PUT: 医薬品更新 ---
        if ($method === 'PUT') {
            // リクエストボディのデータをバリデーション
            $data = validateMedicineInput($raw);

            // バリデーション済みデータでUPDATE実行
            $medicines->update($id, $data);

            // 更新成功をJSON形式で出力
            echo json_encode(['updated' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // --- DELETE: 医薬品削除 ---
        if ($method === 'DELETE') {
            // 指定IDのレコードを削除
            $medicines->delete($id);

            // 削除成功をJSON形式で出力
            echo json_encode(['deleted' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // =========================================================================
    // 薬局情報ルート（?path=pharmacy）
    // =========================================================================
    if ($path === 'pharmacy') {

        // --- GET: 薬局情報取得 ---
        // 利用元: index.php の fetchPharmacy() 関数（薬袋プレビューの薬局情報表示）
        if ($method === 'GET') {
            // 薬局情報をJSON形式で出力
            echo json_encode(['data' => $pharmacy->get()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // --- PUT: 薬局情報更新 ---
        if ($method === 'PUT') {
            // PUTリクエストのボディをphp://inputから読み取り、パース
            parse_str(file_get_contents('php://input'), $raw);

            // リクエストボディのデータをバリデーション
            $data = validatePharmacyInput($raw);

            // バリデーション済みデータで更新実行（Upsertパターン）
            $pharmacy->update($data);

            // 更新成功をJSON形式で出力
            echo json_encode(['updated' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // =========================================================================
    // 未定義ルート — 404 Not Found
    // =========================================================================
    // 上記のいずれのルートにもマッチしなかった場合
    http_response_code(404);
    echo json_encode(['error' => 'Not found'], JSON_UNESCAPED_UNICODE);

// --- 入力バリデーションエラー（422 Unprocessable Entity） ---
}
catch (InvalidArgumentException $e) {
    // validateMedicineInput() や validatePharmacyInput() からスローされた例外
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);

// --- その他の予期しないエラー（500 Internal Server Error） ---
}
catch (Throwable $e) {
    // DB接続エラー、SQL実行エラーなど
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
