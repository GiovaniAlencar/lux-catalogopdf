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
$marcaNormalizada = normalizarMarca($marca);

if ($marcaNormalizada === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Selecione uma marca válida.']);
    exit();
}

require_once __DIR__ . '/lib/config.php';
$pdo = luxDb('catalogo');

$stmt = $pdo->prepare('SELECT id, id_erp, nome FROM products WHERE marca = ? ORDER BY nome');
$stmt->execute([$marcaNormalizada]);
$produtosVinculados = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($produtosVinculados)) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'Esta marca não pode ser excluída porque possui produtos vinculados.',
        'products' => array_map(static function ($produto) {
            return [
                'id' => (int) $produto['id'],
                'id_erp' => $produto['id_erp'] !== null ? (int) $produto['id_erp'] : null,
                'nome' => $produto['nome'],
            ];
        }, $produtosVinculados),
    ]);
    exit();
}

if (!excluirMarca($marcaNormalizada)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível excluir a marca.']);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'Marca excluída com sucesso.',
    'marca' => $marcaNormalizada,
]);
