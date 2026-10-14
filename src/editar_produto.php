<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/marca_options.php';
// Conexão com o banco de dados
require_once __DIR__ . '/lib/config.php';
$pdo = luxDb('catalogo');
$marcasPadrao = obterMarcasPadrao();

// Verifica se foi passado um ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: listar_produtos.php');
    exit();
}

$id = (int)$_GET['id'];

// Busca o produto no banco
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$produto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produto) {
    header('Location: listar_produtos.php');
    exit();
}

if (!empty($produto['marca']) && normalizarMarca($produto['marca']) === '') {
    $marcasPadrao = ordenarMarcas(array_merge($marcasPadrao, [$produto['marca']]));
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Produto - <?= htmlspecialchars($produto['nome']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .imagem-preview {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        .imagem-atual {
            max-width: 150px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 5px;
        }
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-edit"></i> Editar Produto</h2>
                    <a href="listar_produtos.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Produto: <?= htmlspecialchars($produto['nome']) ?></h5>
                    </div>
                    <div class="card-body">
                        <form action="atualizar_produto.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?= $produto['id'] ?>">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <!-- Informações Básicas -->
                                    <div class="form-section">
                                        <h6><i class="fas fa-info-circle"></i> Informações Básicas</h6>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="nome" class="form-label">Nome do Produto *</label>
                                                <input type="text" class="form-control" id="nome" name="nome" 
                                                       value="<?= htmlspecialchars($produto['nome']) ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="marca" class="form-label">Marca</label>
                                                <input type="text" class="form-control mb-2" id="marca_search" placeholder="Buscar marca...">
                                                <div class="d-flex gap-2">
                                                    <select class="form-select" id="marca" name="marca">
                                                        <option value="">Selecione uma marca</option>
                                                        <?php foreach ($marcasPadrao as $marcaPadrao): ?>
                                                            <option value="<?= htmlspecialchars($marcaPadrao) ?>" <?= ($produto['marca'] ?? '') === $marcaPadrao ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($marcaPadrao) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#novaMarcaModal">
                                                        Nova
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#excluirMarcaModal">
                                                        Excluir
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="descricao" class="form-label">Descrição *</label>
                                            <textarea class="form-control" id="descricao" name="descricao" rows="3" 
                                                      maxlength="510" required><?= htmlspecialchars($produto['descricao']) ?></textarea>
                                            <small id="charCount" class="form-text text-muted">0/510 caracteres</small>
                                        </div>
                                    </div>

                                    <!-- Notas Olfativas -->
                                    <div class="form-section">
                                        <h6><i class="fas fa-leaf"></i> Notas Olfativas</h6>
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="notas_topo" class="form-label">Notas de Topo</label>
                                                <input type="text" class="form-control" id="notas_topo" name="notas_topo" 
                                                       value="<?= htmlspecialchars($produto['notas_topo'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="notas_coracao" class="form-label">Notas de Coração</label>
                                                <input type="text" class="form-control" id="notas_coracao" name="notas_coracao" 
                                                       value="<?= htmlspecialchars($produto['notas_coracao'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="notas_fundo" class="form-label">Notas de Fundo</label>
                                                <input type="text" class="form-control" id="notas_fundo" name="notas_fundo" 
                                                       value="<?= htmlspecialchars($produto['notas_fundo'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Preço e Categoria -->
                                    <div class="form-section">
                                        <h6><i class="fas fa-tag"></i> Preço e Categoria</h6>
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="preco" class="form-label">Preço</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="number" class="form-control" id="preco" name="preco" 
                                                           step="0.01" min="0" value="<?= htmlspecialchars((string) ($produto['preco'] ?? '')) ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="id_erp" class="form-label">ID ERP</label>
                                                <input type="number" class="form-control" id="id_erp" name="id_erp"
                                                       min="1" value="<?= htmlspecialchars((string) ($produto['id_erp'] ?? '')) ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="sexo" class="form-label">Sexo *</label>
                                                <select class="form-select" id="sexo" name="sexo" required>
                                                    <option value="masculino" <?= $produto['sexo'] == 'masculino' ? 'selected' : '' ?>>Masculino</option>
                                                    <option value="feminino" <?= $produto['sexo'] == 'feminino' ? 'selected' : '' ?>>Feminino</option>
                                                    <option value="unissex" <?= $produto['sexo'] == 'unissex' ? 'selected' : '' ?>>Unissex</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <!-- Imagem -->
                                    <div class="form-section">
                                        <h6><i class="fas fa-image"></i> Imagem do Produto</h6>
                                        
                                        <!-- Imagem Atual -->
                                        <?php if ($produto['imagem'] && file_exists('uploads/' . $produto['imagem'])): ?>
                                            <div class="mb-3">
                                                <label class="form-label">Imagem Atual:</label>
                                                <div class="text-center">
                                                    <img src="uploads/<?= $produto['imagem'] ?>" alt="Imagem atual" class="imagem-atual">
                                                    <br><small class="text-muted"><?= $produto['imagem'] ?></small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle"></i> Nenhuma imagem cadastrada
                                            </div>
                                        <?php endif; ?>

                                        <!-- Nova Imagem -->
                                        <div class="mb-3">
                                            <label for="nova_imagem" class="form-label">Nova Imagem:</label>
                                            <input type="file" class="form-control" id="nova_imagem" name="nova_imagem" 
                                                   accept="image/*" onchange="previewImagem(this)">
                                            <small class="form-text text-muted">
                                                Formatos aceitos: JPG, PNG, WebP (será convertido automaticamente para JPG)
                                            </small>
                                        </div>

                                        <!-- Preview da Nova Imagem -->
                                        <div id="preview-container" class="mb-3" style="display: none;">
                                            <label class="form-label">Preview:</label>
                                            <div class="text-center">
                                                <img id="imagem-preview" class="imagem-preview">
                                            </div>
                                        </div>

                                        <!-- Opções de Imagem -->
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="manter_imagem_atual" name="manter_imagem_atual" value="1">
                                                <label class="form-check-label" for="manter_imagem_atual">
                                                    Manter imagem atual (se não selecionar nova imagem)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Botões de Ação -->
                            <div class="d-flex justify-content-between mt-4">
                                <a href="listar_produtos.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salvar Alterações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="novaMarcaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cadastrar Nova Marca</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="novaMarcaFeedback" class="alert d-none" role="alert"></div>
                    <div class="mb-3">
                        <label for="nova_marca_nome" class="form-label">Nome da marca</label>
                        <input type="text" class="form-control" id="nova_marca_nome" maxlength="100">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="salvarNovaMarca">Salvar marca</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="excluirMarcaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Excluir Marca</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="excluirMarcaFeedback" class="alert d-none" role="alert"></div>
                    <div class="mb-3">
                        <label for="marca_excluir_search" class="form-label">Buscar marca</label>
                        <input type="text" class="form-control" id="marca_excluir_search" placeholder="Digite para filtrar...">
                    </div>
                    <div class="mb-3">
                        <label for="marca_excluir" class="form-label">Marca</label>
                        <select class="form-select" id="marca_excluir" size="8"></select>
                    </div>
                    <div id="produtosVinculadosWrapper" class="d-none">
                        <label class="form-label">Produtos vinculados</label>
                        <ul class="mb-0" id="produtosVinculadosLista"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmarExcluirMarca">Excluir marca</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const descricao = document.getElementById('descricao');
        const charCount = document.getElementById('charCount');
        const buscaMarca = document.getElementById('marca_search');
        const selectMarca = document.getElementById('marca');
        const inputNovaMarca = document.getElementById('nova_marca_nome');
        const feedbackNovaMarca = document.getElementById('novaMarcaFeedback');
        const modalNovaMarca = new bootstrap.Modal(document.getElementById('novaMarcaModal'));
        const modalExcluirMarca = new bootstrap.Modal(document.getElementById('excluirMarcaModal'));
        const feedbackExcluirMarca = document.getElementById('excluirMarcaFeedback');
        const buscaExcluirMarca = document.getElementById('marca_excluir_search');
        const selectExcluirMarca = document.getElementById('marca_excluir');
        const produtosVinculadosWrapper = document.getElementById('produtosVinculadosWrapper');
        const produtosVinculadosLista = document.getElementById('produtosVinculadosLista');
        let marcasDisponiveis = Array.from(selectMarca.options).slice(1).map((option) => option.value);

        descricao.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length + '/510 caracteres';
            charCount.className = length > 500 ? 'form-text text-warning' : 'form-text text-muted';
        });

        descricao.dispatchEvent(new Event('input'));

        function previewImagem(input) {
            const preview = document.getElementById('imagem-preview');
            const container = document.getElementById('preview-container');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    container.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
                return;
            }

            container.style.display = 'none';
        }

        function ordenarMarcas(lista) {
            return [...lista].sort((a, b) => a.localeCompare(b, 'pt-BR', { sensitivity: 'base' }));
        }

        function atualizarFeedbackMarca(tipo, mensagem) {
            feedbackNovaMarca.className = 'alert alert-' + tipo;
            feedbackNovaMarca.textContent = mensagem;
            feedbackNovaMarca.classList.remove('d-none');
        }

        function limparFeedbackMarca() {
            feedbackNovaMarca.className = 'alert d-none';
            feedbackNovaMarca.textContent = '';
        }

        function atualizarFeedbackExclusao(tipo, mensagem) {
            feedbackExcluirMarca.className = 'alert alert-' + tipo;
            feedbackExcluirMarca.textContent = mensagem;
            feedbackExcluirMarca.classList.remove('d-none');
        }

        function limparFeedbackExclusao() {
            feedbackExcluirMarca.className = 'alert d-none';
            feedbackExcluirMarca.textContent = '';
        }

        function limparProdutosVinculados() {
            produtosVinculadosLista.innerHTML = '';
            produtosVinculadosWrapper.classList.add('d-none');
        }

        function atualizarSelectMarca() {
            const selecionada = selectMarca.value;
            const filtro = buscaMarca.value.trim().toLowerCase();
            const marcasFiltradas = ordenarMarcas(marcasDisponiveis).filter((marca) =>
                marca.toLowerCase().includes(filtro)
            );

            selectMarca.innerHTML = '<option value="">Selecione uma marca</option>';

            marcasFiltradas.forEach((marca) => {
                const option = document.createElement('option');
                option.value = marca;
                option.textContent = marca;
                if (marca === selecionada) {
                    option.selected = true;
                }
                selectMarca.appendChild(option);
            });

            if (selecionada && !marcasFiltradas.includes(selecionada) && marcasDisponiveis.includes(selecionada)) {
                const option = document.createElement('option');
                option.value = selecionada;
                option.textContent = selecionada;
                option.selected = true;
                selectMarca.appendChild(option);
            }
        }

        function atualizarSelectExclusao() {
            const valorAtual = selectExcluirMarca.value;
            const filtro = buscaExcluirMarca.value.trim().toLowerCase();
            const marcasFiltradas = ordenarMarcas(marcasDisponiveis).filter((marca) =>
                marca.toLowerCase().includes(filtro)
            );

            selectExcluirMarca.innerHTML = '';

            marcasFiltradas.forEach((marca) => {
                const option = document.createElement('option');
                option.value = marca;
                option.textContent = marca;
                if (marca === valorAtual) {
                    option.selected = true;
                }
                selectExcluirMarca.appendChild(option);
            });

            if (!selectExcluirMarca.value && selectExcluirMarca.options.length > 0) {
                selectExcluirMarca.options[0].selected = true;
            }
        }

        function adicionarMarca(marca) {
            if (!marcasDisponiveis.some((item) => item.toLowerCase() === marca.toLowerCase())) {
                marcasDisponiveis.push(marca);
            }

            atualizarSelectMarca();
            atualizarSelectExclusao();
            selectMarca.value = marca;
        }

        function removerMarcaDaTela(marca) {
            marcasDisponiveis = marcasDisponiveis.filter((item) => item.toLowerCase() !== marca.toLowerCase());

            if (selectMarca.value && selectMarca.value.toLowerCase() === marca.toLowerCase()) {
                selectMarca.value = '';
            }

            atualizarSelectMarca();
            atualizarSelectExclusao();
        }

        buscaMarca.addEventListener('input', atualizarSelectMarca);
        buscaExcluirMarca.addEventListener('input', atualizarSelectExclusao);

        document.getElementById('novaMarcaModal').addEventListener('shown.bs.modal', function() {
            limparFeedbackMarca();
            inputNovaMarca.value = '';
            inputNovaMarca.focus();
        });

        document.getElementById('excluirMarcaModal').addEventListener('shown.bs.modal', function() {
            limparFeedbackExclusao();
            limparProdutosVinculados();
            buscaExcluirMarca.value = '';
            atualizarSelectExclusao();
            buscaExcluirMarca.focus();
        });

        document.getElementById('salvarNovaMarca').addEventListener('click', async function() {
            const marca = inputNovaMarca.value.trim();

            if (!marca) {
                atualizarFeedbackMarca('warning', 'Informe o nome da marca.');
                return;
            }

            const formData = new FormData();
            formData.append('marca', marca);

            try {
                const response = await fetch('salvar_marca.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    atualizarFeedbackMarca('danger', data.message || 'Não foi possível salvar a marca.');
                    return;
                }

                adicionarMarca(data.marca);
                modalNovaMarca.hide();
            } catch (error) {
                atualizarFeedbackMarca('danger', 'Erro ao salvar a marca.');
            }
        });

        document.getElementById('confirmarExcluirMarca').addEventListener('click', async function() {
            const marca = selectExcluirMarca.value;

            if (!marca) {
                atualizarFeedbackExclusao('warning', 'Selecione uma marca para excluir.');
                limparProdutosVinculados();
                return;
            }

            const formData = new FormData();
            formData.append('marca', marca);

            try {
                const response = await fetch('excluir_marca.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (response.status === 409) {
                    atualizarFeedbackExclusao('warning', data.message || 'A marca possui produtos vinculados.');
                    produtosVinculadosLista.innerHTML = '';

                    (data.products || []).forEach((produto) => {
                        const item = document.createElement('li');
                        item.textContent = produto.id_erp ? `${produto.nome} (ID ERP: ${produto.id_erp})` : produto.nome;
                        produtosVinculadosLista.appendChild(item);
                    });

                    produtosVinculadosWrapper.classList.remove('d-none');
                    return;
                }

                if (!response.ok || !data.success) {
                    atualizarFeedbackExclusao('danger', data.message || 'Não foi possível excluir a marca.');
                    limparProdutosVinculados();
                    return;
                }

                removerMarcaDaTela(data.marca);
                limparProdutosVinculados();
                modalExcluirMarca.hide();
            } catch (error) {
                atualizarFeedbackExclusao('danger', 'Erro ao excluir a marca.');
                limparProdutosVinculados();
            }
        });

        // Validação do formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const novaImagem = document.getElementById('nova_imagem');
            const manterImagem = document.getElementById('manter_imagem_atual');
            
            // Se não selecionou nova imagem e não marcou para manter a atual
            if (!novaImagem.files[0] && !manterImagem.checked) {
                if (!confirm('Nenhuma imagem foi selecionada. Deseja continuar sem alterar a imagem?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        atualizarSelectMarca();
        atualizarSelectExclusao();
    </script>
</body>
</html>
