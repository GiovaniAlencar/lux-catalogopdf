<?php
require_once __DIR__ . '/auth_check.php';
// Conexão com o banco de dados
$pdo = new PDO('mysql:host=catalogo_db;dbname=catalogo;charset=utf8', 'root', 'root');

// Recupera os produtos
$stmt = $pdo->query("SELECT * FROM products ORDER BY nome");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos Cadastrados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .produto-imagem {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
            min-width: 80px;
            min-height: 80px;
        }
        .acoes-botoes {
            white-space: nowrap;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .produto-row td {
            vertical-align: middle;
        }
        .produto-row td:first-child {
            width: 80px;
            min-width: 80px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-box"></i> Produtos Cadastrados</h2>
            <div>
                <a href="cadastro.php" class="btn btn-success me-2">
                    <i class="fas fa-plus"></i> Novo Produto
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#gerarCatalogoModal">
                    <i class="fas fa-file-pdf"></i> Gerar Catálogo
                </button>
            </div>
        </div>

        <?php if (isset($_GET['sucesso'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i>
                <?php
                switch ($_GET['sucesso']) {
                    case 'produto_cadastrado':
                        echo 'Produto cadastrado com sucesso!';
                        break;
                    case 'produto_atualizado':
                        echo 'Produto atualizado com sucesso!';
                        break;
                    case 'produto_excluido':
                        echo 'Produto excluído com sucesso!';
                        break;
                    default:
                        echo 'Operação realizada com sucesso!';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['erro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <?php
                switch ($_GET['erro']) {
                    case 'produto_nao_encontrado':
                        echo 'Produto não encontrado.';
                        break;
                    case 'id_invalido':
                        echo 'ID do produto inválido.';
                        break;
                    case 'erro_exclusao':
                        echo 'Erro ao excluir o produto.';
                        break;
                    case 'erro_banco':
                        echo 'Erro no banco de dados.';
                        break;
                    default:
                        echo 'Ocorreu um erro. Tente novamente.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($produtos)): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> Nenhum produto cadastrado ainda.
                <br><a href="cadastro.php" class="btn btn-primary mt-2">Cadastrar Primeiro Produto</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th style="width: 100px;">Imagem</th>
                            <th>Nome</th>
                            <th>Marca</th>
                            <th style="width: 120px;">Preço</th>
                            <th style="width: 100px;">Sexo</th>
                            <th style="width: 120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $produto): ?>
                            <tr>
                                <td><strong><?= $produto['id'] ?></strong></td>
                                <td style="width: 100px; min-width: 100px; text-align: center;">
                                    <?php if ($produto['imagem'] && file_exists('uploads/' . $produto['imagem'])): ?>
                                        <img src="uploads/<?= $produto['imagem'] ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" class="produto-imagem">
                                    <?php else: ?>
                                        <div class="text-muted" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc; border-radius: 5px;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($produto['nome']) ?></strong>
                                    <?php if ($produto['descricao']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars(substr($produto['descricao'], 0, 50)) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($produto['marca'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge bg-success">
                                        <?= ($produto['preco'] !== null && $produto['preco'] !== '') ? 'R$ ' . number_format((float) $produto['preco'], 2, ',', '.') : 'Sem preço' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $sexo_icon = '';
                                    $sexo_class = '';
                                    switch ($produto['sexo']) {
                                        case 'masculino':
                                            $sexo_icon = 'mars';
                                            $sexo_class = 'text-primary';
                                            break;
                                        case 'feminino':
                                            $sexo_icon = 'venus';
                                            $sexo_class = 'text-danger';
                                            break;
                                        case 'unissex':
                                            $sexo_icon = 'venus-mars';
                                            $sexo_class = 'text-info';
                                            break;
                                    }
                                    ?>
                                    <i class="fas fa-<?= $sexo_icon ?> <?= $sexo_class ?>"></i>
                                    <?= ucfirst($produto['sexo']) ?>
                                </td>
                                <td class="acoes-botoes">
                                    <a href="editar_produto.php?id=<?= $produto['id'] ?>" class="btn btn-warning btn-sm" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmarExclusao(<?= $produto['id'] ?>)" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para Gerar Catálogo -->
    <div class="modal fade" id="gerarCatalogoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gerar Catálogo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="gerar_catalogo.php" method="POST">
                    <div class="modal-body">
                        <p>Selecione os produtos que deseja incluir no catálogo:</p>
                        
                        <!-- Campo de Pesquisa -->
                        <div class="mb-3">
                            <label for="pesquisaCatalogo" class="form-label">Pesquisar produtos:</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="pesquisaCatalogo" 
                                       placeholder="Digite o nome, marca ou descrição do produto...">
                            </div>
                            <small class="form-text text-muted">
                                Pesquise por nome, marca ou descrição para filtrar os produtos
                            </small>
                        </div>

                        <!-- Contadores -->
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted">
                                <span id="produtosEncontrados"><?= count($produtos) ?></span> produtos encontrados
                            </small>
                            <small class="text-muted">
                                <span id="produtosSelecionados">0</span> produtos selecionados
                            </small>
                        </div>

                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selecionarTodos"></th>
                                        <th>Produto</th>
                                        <th>Marca</th>
                                        <th>Preço</th>
                                    </tr>
                                </thead>
                                <tbody id="tabelaProdutos">
                                    <?php foreach ($produtos as $produto): ?>
                                        <tr class="produto-row" data-nome="<?= strtolower(htmlspecialchars($produto['nome'])) ?>" 
                                            data-marca="<?= strtolower(htmlspecialchars($produto['marca'] ?? '')) ?>" 
                                            data-descricao="<?= strtolower(htmlspecialchars($produto['descricao'] ?? '')) ?>">
                                            <td><input type="checkbox" name="produtos[]" value="<?= $produto['id'] ?>" class="produto-checkbox"></td>
                                            <td>
                                                <strong><?= htmlspecialchars($produto['nome']) ?></strong>
                                                <?php if ($produto['descricao']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars(substr($produto['descricao'], 0, 30)) ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($produto['marca'] ?? 'N/A') ?></td>
                                            <td><?= ($produto['preco'] !== null && $produto['preco'] !== '') ? 'R$ ' . number_format((float) $produto['preco'], 2, ',', '.') : 'Sem preço' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Gerar Catálogo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Selecionar todos os produtos
        document.getElementById('selecionarTodos').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.produto-checkbox:not([style*="display: none"])');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            atualizarContadorSelecionados();
        });

        // Confirmar exclusão
        function confirmarExclusao(id) {
            if (confirm('Tem certeza que deseja excluir este produto?')) {
                window.location.href = 'excluir_produto.php?id=' + id;
            }
        }

        // Pesquisa em tempo real
        document.getElementById('pesquisaCatalogo').addEventListener('input', function() {
            const termo = this.value.toLowerCase().trim();
            const linhas = document.querySelectorAll('.produto-row');
            let encontrados = 0;

            linhas.forEach(linha => {
                const nome = linha.getAttribute('data-nome');
                const marca = linha.getAttribute('data-marca');
                const descricao = linha.getAttribute('data-descricao');
                
                const match = nome.includes(termo) || 
                             marca.includes(termo) || 
                             descricao.includes(termo);

                if (match || termo === '') {
                    linha.style.display = '';
                    encontrados++;
                } else {
                    linha.style.display = 'none';
                }
            });

            // Atualiza contadores
            document.getElementById('produtosEncontrados').textContent = encontrados;
            
            // Reseta o checkbox "selecionar todos" quando há pesquisa
            if (termo !== '') {
                document.getElementById('selecionarTodos').checked = false;
            }
        });

        // Atualizar contador de produtos selecionados
        function atualizarContadorSelecionados() {
            const checkboxes = document.querySelectorAll('.produto-checkbox:checked');
            document.getElementById('produtosSelecionados').textContent = checkboxes.length;
        }

        // Adicionar listener para checkboxes individuais
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('produto-checkbox')) {
                atualizarContadorSelecionados();
                
                // Verifica se todos os produtos visíveis estão selecionados
                const checkboxesVisiveis = document.querySelectorAll('.produto-checkbox:not([style*="display: none"])');
                const checkboxesSelecionados = document.querySelectorAll('.produto-checkbox:checked:not([style*="display: none"])');
                
                document.getElementById('selecionarTodos').checked = 
                    checkboxesVisiveis.length > 0 && checkboxesVisiveis.length === checkboxesSelecionados.length;
            }
        });

        // Inicializar contador
        atualizarContadorSelecionados();
    </script>
</body>
</html>
