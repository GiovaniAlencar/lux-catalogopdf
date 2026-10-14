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

// Busca gêneros distintos
$query = "SELECT DISTINCT sexo FROM products WHERE sexo IS NOT NULL AND sexo != '' ORDER BY sexo";
$stmt = $pdoCatalogo->query($query);
$generos = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Formata os gêneros com primeira letra maiúscula
$generos = array_map('ucfirst', array_map('strtolower', $generos));

// Retorna os gêneros em formato JSON
echo json_encode($generos);
