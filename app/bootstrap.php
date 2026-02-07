<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/MedicineRepository.php';
require_once __DIR__ . '/PharmacyRepository.php';

function medicineTypes(): array
{
    return [
        'external' => '外用薬',
        'internal' => '内服薬',
        'kampo' => '漢方薬',
        'as_needed' => '頓服薬',
    ];
}

function validateMedicineInput(array $input): array
{
    $required = ['medicine_name', 'medicine_type', 'dosage_usage', 'dosage_amount', 'daily_frequency'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
            throw new InvalidArgumentException($field . ' は必須です。');
        }
    }

    $types = medicineTypes();
    if (!array_key_exists($input['medicine_type'], $types)) {
        throw new InvalidArgumentException('medicine_type が不正です。');
    }

    return [
        'medicine_name' => trim((string)$input['medicine_name']),
        'medicine_type' => (string)$input['medicine_type'],
        'dosage_usage' => trim((string)$input['dosage_usage']),
        'dosage_amount' => trim((string)$input['dosage_amount']),
        'daily_frequency' => trim((string)$input['daily_frequency']),
        'description' => trim((string)($input['description'] ?? '')),
    ];
}

function validatePharmacyInput(array $input): array
{
    $required = ['pharmacy_name', 'address', 'phone', 'fax'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
            throw new InvalidArgumentException($field . ' は必須です。');
        }
    }

    return [
        'pharmacy_name' => trim((string)$input['pharmacy_name']),
        'address' => trim((string)$input['address']),
        'phone' => trim((string)$input['phone']),
        'fax' => trim((string)$input['fax']),
    ];
}
