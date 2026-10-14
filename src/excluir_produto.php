<?php
require_once __DIR__ . '/auth_check.php';
// Conexão com o banco de dados
$pdo = new PDO('mysql:host=catalogo_db;dbname=catalogo;charset=utf8', 'root', 'root');

// Verifica se foi passado um ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: listar_produtos.php?erro=id_invalido');
    exit();
}

$id = (int)$_GET['id'];

try {
    // Busca o produto para obter o nome da imagem
    $stmt = $pdo->prepare("SELECT nome, imagem FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produto) {
        header('Location: listar_produtos.php?erro=produto_nao_encontrado');
        exit();
    }
    
    // Remove a imagem do servidor se existir
    if ($produto['imagem'] && file_exists('uploads/' . $produto['imagem'])) {
        unlink('uploads/' . $produto['imagem']);
    }
    
    // Remove o produto do banco de dados
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $resultado = $stmt->execute([$id]);
    
    if ($resultado) {
        header('Location: listar_produtos.php?sucesso=produto_excluido');
    } else {
        header('Location: listar_produtos.php?erro=erro_exclusao');
    }
    
} catch (PDOException $e) {
    error_log('Erro ao excluir produto: ' . $e->getMessage());
    header('Location: listar_produtos.php?erro=erro_banco');
}

exit();
?>
