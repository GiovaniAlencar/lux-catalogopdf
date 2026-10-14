<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/marca_options.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

$marca = trim($_POST['marca'] ?? '');

if ($marca === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Informe o nome da marca.']);
    exit();
}

if (mb_strlen($marca, 'UTF-8') > 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A marca deve ter no máximo 100 caracteres.']);
    exit();
}

$marcaSalva = salvarMarcaCustomizada($marca);

if ($marcaSalva === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível salvar a marca.']);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'Marca salva com sucesso.',
    'marca' => $marcaSalva,
]);
