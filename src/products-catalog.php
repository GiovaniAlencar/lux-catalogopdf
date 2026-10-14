<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configuração dos bancos de dados
try {
    $pdoCatalogo = new PDO('mysql:host=localhost;dbname=luxcatalogo;charset=utf8', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdoERP = new PDO('mysql:host=localhost;dbname=luxerp;charset=utf8', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro na conexão com o banco de dados: ' . $e->getMessage()]);
    exit;
}

// Busca produtos no ERP com estoque > 0 e categoria_id = 3
$queryERP = "
    SELECT e.produto_id, p.valor_venda, e.quantidade
    FROM estoques e
    JOIN produtos p ON p.id = e.produto_id
    WHERE e.quantidade > 0 AND p.categoria_id > 2
";
$stmtERP = $pdoERP->query($queryERP);
$produtosERP = $stmtERP->fetchAll(PDO::FETCH_ASSOC);

if (empty($produtosERP)) {
    echo json_encode(['error' => 'Nenhum produto disponível para o catálogo.']);
    exit;
}

// Busca informações complementares no catálogo
$idsERP = array_column($produtosERP, 'produto_id');
$placeholders = implode(',', array_fill(0, count($idsERP), '?'));

$queryCatalogo = "
    SELECT p.id_erp, p.nome, p.descricao, p.sexo, p.marca, p.imagem
    FROM products p
    WHERE p.id_erp IN ($placeholders)
";
$stmtCatalogo = $pdoCatalogo->prepare($queryCatalogo);
$stmtCatalogo->execute($idsERP);
$produtosCatalogo = $stmtCatalogo->fetchAll(PDO::FETCH_ASSOC);

// Associa os produtos do ERP com os do catálogo
$produtosCompletos = [];
foreach ($produtosERP as $produtoERP) {
    foreach ($produtosCatalogo as $produtoCatalogo) {
        if ($produtoCatalogo['id_erp'] == $produtoERP['produto_id']) {
            // Formata os dados para o formato esperado pelo frontend
            $produtosCompletos[] = [
                'id' => (int)$produtoCatalogo['id_erp'],
                'name' => $produtoCatalogo['nome'],
                'description' => $produtoCatalogo['descricao'],
                'price' => (float)$produtoERP['valor_venda'],
                'image' => !empty($produtoCatalogo['imagem']) 
                    ? 'http://192.168.2.105/luxcatalogo/uploads/' . $produtoCatalogo['imagem'] 
                    : null,
                'brand' => $produtoCatalogo['marca'],
                'type' => 'Perfumaria Árabe', // Categoria padrão
                'gender' => ucfirst(strtolower($produtoCatalogo['sexo'])),
                'stock' => (int)$produtoERP['quantidade'],
                'createdAt' => date('Y-m-d')
            ];
        }
    }
}

// Parâmetros de filtro
$filters = [];
if (isset($_GET['q']) && !empty($_GET['q'])) {
    $search = strtolower($_GET['q']);
    $filters[] = function($product) use ($search) {
        return strpos(strtolower($product['name']), $search) !== false || 
               strpos(strtolower($product['description']), $search) !== false;
    };
}

if (isset($_GET['brand']) && !empty($_GET['brand'])) {
    $brands = explode(',', $_GET['brand']);
    $filters[] = function($product) use ($brands) {
        return in_array($product['brand'], $brands);
    };
}

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $types = explode(',', $_GET['type']);
    $filters[] = function($product) use ($types) {
        return in_array($product['type'], $types);
    };
}

if (isset($_GET['gender']) && !empty($_GET['gender'])) {
    $genders = explode(',', $_GET['gender']);
    $filters[] = function($product) use ($genders) {
        return in_array($product['gender'], $genders);
    };
}

// Aplicar filtros
if (!empty($filters)) {
    foreach ($filters as $filter) {
        $produtosCompletos = array_filter($produtosCompletos, $filter);
    }
}

// Ordenação
$sort = $_GET['sort'] ?? 'newest';
switch ($sort) {
    case 'price-low':
        usort($produtosCompletos, function($a, $b) {
            return $a['price'] <=> $b['price'];
        });
        break;
    case 'price-high':
        usort($produtosCompletos, function($a, $b) {
            return $b['price'] <=> $a['price'];
        });
        break;
    case 'newest':
    default:
        // Já está ordenado por padrão
        break;
}

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
$total = count($produtosCompletos);

$offset = ($page - 1) * $limit;
$produtosPaginados = array_slice($produtosCompletos, $offset, $limit);

// Retorna os dados em formato JSON
echo json_encode([
    'products' => array_values($produtosPaginados),
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
    'totalPages' => ceil($total / $limit)
]);
