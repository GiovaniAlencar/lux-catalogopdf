<?php
require_once __DIR__ . '/auth_check.php';
// Inclui a função de conversão de imagem
require_once 'converter_imagem.php';
require_once __DIR__ . '/marca_options.php';

// Conexão com o banco de dados
$pdo = new PDO('mysql:host=catalogo_db;dbname=catalogo;charset=utf8', 'root', 'root');

// Dados do formulário
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
    header('Location: cadastro.php?erro=dados_invalidos');
    exit();
}

// Processa o upload da imagem com conversão automática
$imagem = null;
if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
    $imagem = processarUploadImagem($_FILES['imagem'], 'uploads/');
    
    if (!$imagem) {
        header('Location: cadastro.php?erro=erro_upload');
        exit();
    }
} else {
    header('Location: cadastro.php?erro=imagem_obrigatoria');
    exit();
}

try {
    // Inserção no banco de dados
    $stmt = $pdo->prepare("
        INSERT INTO products (id_erp, nome, marca, descricao, notas_topo, notas_coracao, notas_fundo, preco, imagem, sexo) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        $imagem, 
        $sexo
    ]);
    
    if ($resultado) {
        header('Location: listar_produtos.php?sucesso=produto_cadastrado');
    } else {
        header('Location: cadastro.php?erro=erro_cadastro');
    }
    
} catch (PDOException $e) {
    error_log('Erro ao cadastrar produto: ' . $e->getMessage());
    header('Location: cadastro.php?erro=erro_banco');
}

exit();
?>
