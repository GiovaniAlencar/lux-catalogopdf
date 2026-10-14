<?php
// Desabilita exibição de erros para evitar HTML na resposta JSON
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Limpa qualquer output anterior
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuração do banco de dados ERP
try {
    $pdoERP = new PDO('mysql:host=localhost;dbname=luxerp;charset=utf8', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['error' => 'Erro na conexão com o banco de dados: ' . $e->getMessage()]);
    exit;
}

try {
    // Busca categorias do banco ERP
    $query = "
        SELECT DISTINCT c.id, c.nome as name, c.descricao as description
        FROM categorias c
        INNER JOIN produtos p ON p.categoria_id = c.id
        INNER JOIN estoques e ON e.produto_id = p.id
        WHERE e.quantidade > 0
        ORDER BY c.nome
    ";
    
    $stmt = $pdoERP->query($query);
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($categorias)) {
        // Se não encontrar categorias na tabela, retorna categorias padrão baseadas na imagem
        $categorias = [
            ['id' => 3, 'name' => 'Árabes', 'description' => 'Perfumes árabes'],
            ['id' => 4, 'name' => 'Hidratantes', 'description' => 'Hidratantes corporais'],
            ['id' => 5, 'name' => 'Sprays', 'description' => 'Sprays corporais'],
            ['id' => 6, 'name' => 'Miniaturas', 'description' => 'Miniaturas de perfumes'],
            ['id' => 7, 'name' => 'Body Splash', 'description' => 'Body splash'],
            ['id' => 8, 'name' => 'Francês', 'description' => 'Perfumes franceses']
        ];
    }
    
    ob_clean();
    echo json_encode($categorias);
    
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['error' => 'Erro ao buscar categorias: ' . $e->getMessage()]);
}
?>
