<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = Database::connection();
$medicines = new MedicineRepository($pdo);
$pharmacy = new PharmacyRepository($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = $_GET['path'] ?? '';

try {
    if ($path === 'medicines') {
        if ($method === 'GET') {
            $keyword = trim((string)($_GET['keyword'] ?? ''));
            echo json_encode(['data' => $medicines->searchByName($keyword)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($method === 'POST') {
            $data = validateMedicineInput($_POST);
            $id = $medicines->create($data);
            http_response_code(201);
            echo json_encode(['id' => $id], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if (preg_match('#^medicines/(\d+)$#', $path, $matches) === 1) {
        $id = (int)$matches[1];
        parse_str(file_get_contents('php://input'), $raw);

        if ($method === 'PUT') {
            $data = validateMedicineInput($raw);
            $medicines->update($id, $data);
            echo json_encode(['updated' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($method === 'DELETE') {
            $medicines->delete($id);
            echo json_encode(['deleted' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if ($path === 'pharmacy') {
        if ($method === 'GET') {
            echo json_encode(['data' => $pharmacy->get()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($method === 'PUT') {
            parse_str(file_get_contents('php://input'), $raw);
            $data = validatePharmacyInput($raw);
            $pharmacy->update($data);
            echo json_encode(['updated' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found'], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
