<?php

// [ADDED] このRepositoryは medicines テーブル専用のDBアクセスを集約し、API/管理画面から呼び出されるCRUD SQLを実行する。
// [ADDED] 入力値の妥当性チェック自体は bootstrap.php の validateMedicineInput() 側で実施され、ここでは受け取った値をSQLにバインドして永続化する。

declare(strict_types=1);

final class MedicineRepository
{
    // [ADDED] 依存: Database::connection() が返す PDO を受け取り、このクラス内の全メソッドで再利用する。
    public function __construct(private readonly PDO $pdo)
    {
    }

    // [ADDED] 用途: 医薬品名で検索し、keywordが空なら全件取得、非空なら LIKE 検索を行う。
    // [ADDED] 戻り値: medicines テーブル行の配列（0件時は空配列）。副作用: なし（SELECTのみ）。
    public function searchByName(string $keyword = ''): array
    {
        if ($keyword === '') {
            $stmt = $this->pdo->query('SELECT * FROM medicines ORDER BY medicine_name ASC');
            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->prepare('SELECT * FROM medicines WHERE medicine_name LIKE :keyword ORDER BY medicine_name ASC');
        $stmt->execute([':keyword' => '%' . $keyword . '%']);
        return $stmt->fetchAll();
    }

    // [ADDED] 用途: 主キーIDで1件取得する。見つからない場合は null を返す。
    // [ADDED] SQL目的: SELECT * FROM medicines WHERE id = :id（単一レコード取得）。
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM medicines WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // [ADDED] 用途: 医薬品1件を新規登録する。
    // [ADDED] SQL目的: INSERT INTO medicines ...。副作用: DB更新（created_at/updated_atを現在時刻で設定）。
    // [ADDED] 戻り値: lastInsertId() を int に変換した新規ID。
    public function create(array $data): int
    {
        $sql = 'INSERT INTO medicines
            (medicine_name, medicine_type, dosage_usage, dosage_amount, daily_frequency, description, created_at, updated_at)
            VALUES (:medicine_name, :medicine_type, :dosage_usage, :dosage_amount, :daily_frequency, :description, :created_at, :updated_at)';
        $stmt = $this->pdo->prepare($sql);
        $now = date('c');
        $stmt->execute([
            ':medicine_name' => $data['medicine_name'],
            ':medicine_type' => $data['medicine_type'],
            ':dosage_usage' => $data['dosage_usage'],
            ':dosage_amount' => $data['dosage_amount'],
            ':daily_frequency' => $data['daily_frequency'],
            ':description' => $data['description'] ?? '',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // [ADDED] 用途: 指定IDの医薬品レコードを更新する。
    // [ADDED] SQL目的: UPDATE medicines SET ... WHERE id = :id。副作用: DB更新（updated_atを更新）。
    // [ADDED] 戻り値: PDOStatement::execute() の真偽値。
    public function update(int $id, array $data): bool
    {
        $sql = 'UPDATE medicines
                SET medicine_name = :medicine_name,
                    medicine_type = :medicine_type,
                    dosage_usage = :dosage_usage,
                    dosage_amount = :dosage_amount,
                    daily_frequency = :daily_frequency,
                    description = :description,
                    updated_at = :updated_at
                WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':medicine_name' => $data['medicine_name'],
            ':medicine_type' => $data['medicine_type'],
            ':dosage_usage' => $data['dosage_usage'],
            ':dosage_amount' => $data['dosage_amount'],
            ':daily_frequency' => $data['daily_frequency'],
            ':description' => $data['description'] ?? '',
            ':updated_at' => date('c'),
        ]);
    }

    // [ADDED] 用途: 指定IDの医薬品を削除する。
    // [ADDED] SQL目的: DELETE FROM medicines WHERE id = :id。副作用: DB更新（削除）。
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM medicines WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
