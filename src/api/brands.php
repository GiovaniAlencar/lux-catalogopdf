<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configuração do banco de dados
try {
    $pdoCatalogo = new PDO('mysql:host=localhost;dbname=luxcatalogo;charset=utf8', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro na conexão com o banco de dados: ' . $e->getMessage()]);
    exit;
}

// Busca marcas distintas
$query = "SELECT DISTINCT marca FROM products WHERE marca IS NOT NULL AND marca != '' ORDER BY marca";
$stmt = $pdoCatalogo->query($query);
$marcas = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Retorna as marcas em formato JSON
echo json_encode($marcas);
?>
