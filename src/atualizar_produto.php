<?php
require_once __DIR__ . '/auth_check.php';
// Inclui a função de conversão de imagem
require_once 'converter_imagem.php';
require_once __DIR__ . '/marca_options.php';

// Conexão com o banco de dados
require_once __DIR__ . '/lib/config.php';
$pdo = luxDb('catalogo');

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar_produtos.php');
    exit();
}

// Dados do formulário
$id = (int)$_POST['id'];
$nome = trim($_POST['nome']);
$marca = normalizarMarca($_POST['marca'] ?? '');
$descricao = trim($_POST['descricao']);
$notas_topo = trim($_POST['notas_topo'] ?? '');
$notas_coracao = trim($_POST['notas_coracao'] ?? '');
$notas_fundo = trim($_POST['notas_fundo'] ?? '');
$preco = trim($_POST['preco'] ?? '');
$preco = $preco !== '' ? (float) $preco : null;
$id_erp = trim($_POST['id_erp'] ?? '');
$id_erp = $id_erp !== '' ? (int) $id_erp : null;
$sexo = $_POST['sexo'];

// Validações básicas
if (empty($nome) || empty($descricao)) {
    header('Location: editar_produto.php?id=' . $id . '&erro=dados_invalidos');
    exit();
}

// Busca o produto atual para obter a imagem atual
$stmt = $pdo->prepare("SELECT imagem FROM products WHERE id = ?");
$stmt->execute([$id]);
$produto_atual = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produto_atual) {
    header('Location: listar_produtos.php?erro=produto_nao_encontrado');
    exit();
}

$imagem_atual = $produto_atual['imagem'];
$nova_imagem = null;

// Processa nova imagem se foi enviada
if (isset($_FILES['nova_imagem']) && $_FILES['nova_imagem']['error'] === UPLOAD_ERR_OK) {
    $arquivo_upload = $_FILES['nova_imagem'];
    
    // Processa o upload com conversão automática
    $nova_imagem = processarUploadImagem($arquivo_upload, 'uploads/');
    
    if (!$nova_imagem) {
        header('Location: editar_produto.php?id=' . $id . '&erro=erro_upload');
        exit();
    }
    
    // Remove a imagem antiga se existir e for diferente da nova
    if ($imagem_atual && $imagem_atual !== $nova_imagem && file_exists('uploads/' . $imagem_atual)) {
        unlink('uploads/' . $imagem_atual);
    }
} elseif (isset($_POST['manter_imagem_atual']) && $_POST['manter_imagem_atual'] == '1') {
    // Mantém a imagem atual
    $nova_imagem = $imagem_atual;
} else {
    // Se não enviou nova imagem e não marcou para manter, mantém a atual
    $nova_imagem = $imagem_atual;
}

try {
    // Atualiza o produto no banco de dados
    $stmt = $pdo->prepare("
        UPDATE products 
        SET id_erp = ?, nome = ?, marca = ?, descricao = ?, notas_topo = ?, notas_coracao = ?, notas_fundo = ?, preco = ?, imagem = ?, sexo = ?
        WHERE id = ?
    ");
    
    $resultado = $stmt->execute([
        $id_erp,
        $nome,
        $marca,
        $descricao,
        $notas_topo,
        $notas_coracao,
        $notas_fundo,
        $preco,
        $nova_imagem,
        $sexo,
        $id
    ]);
    
    if ($resultado) {
        // Sucesso
        header('Location: listar_produtos.php?sucesso=produto_atualizado');
    } else {
        // Erro na atualização
        header('Location: editar_produto.php?id=' . $id . '&erro=erro_atualizacao');
    }
    
} catch (PDOException $e) {
    // Erro no banco de dados
    error_log('Erro ao atualizar produto: ' . $e->getMessage());
    header('Location: editar_produto.php?id=' . $id . '&erro=erro_banco');
}

exit();
?>
