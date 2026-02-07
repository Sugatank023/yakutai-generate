<?php

declare(strict_types=1);

final class MedicineRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

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

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM medicines WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

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

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM medicines WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
