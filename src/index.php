<?php require_once __DIR__ . '/auth_check.php'; require_once __DIR__ . '/lib/config.php'; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Catálogo - Lux Catálogo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
        }
        .feature-card {
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .stats-card {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="d-flex justify-content-end mb-2">
                <span class="text-white me-3"><i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['usuario_nome'] ?? '') ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-4">
                        <i class="fas fa-spray-can"></i> Lux Catálogo
                    </h1>
                    <p class="lead mb-4">
                        Sistema completo para gerenciamento de produtos de perfumaria. 
                        Cadastre, edite e gere catálogos profissionais com facilidade.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="cadastro.php" class="btn btn-light btn-lg">
                            <i class="fas fa-plus"></i> Cadastrar Produto
                        </a>
                        <a href="listar_produtos.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-list"></i> Ver Produtos
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <i class="fas fa-spray-can" style="font-size: 8rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="container my-5">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card feature-card h-100">
                    <div class="card-body text-center">
                        <div class="feature-icon text-primary">
                            <i class="fas fa-edit"></i>
                        </div>
                        <h5 class="card-title">Edição Completa</h5>
                        <p class="card-text">
                            Edite todos os dados dos produtos, incluindo imagens com conversão automática de WebP para JPG.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card feature-card h-100">
                    <div class="card-body text-center">
                        <div class="feature-icon text-success">
                            <i class="fas fa-image"></i>
                        </div>
                        <h5 class="card-title">Conversão Automática</h5>
                        <p class="card-text">
                            Upload de imagens WebP com conversão automática para JPG para melhor compatibilidade.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card feature-card h-100">
                    <div class="card-body text-center">
                        <div class="feature-icon text-info">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h5 class="card-title">Catálogos PDF</h5>
                        <p class="card-text">
                            Gere catálogos profissionais em PDF com os produtos selecionados.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="container my-5">
        <h2 class="text-center mb-4">Ações Rápidas</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <a href="cadastro.php" class="btn btn-primary w-100 py-3">
                    <i class="fas fa-plus-circle mb-2"></i><br>
                    Novo Produto
                </a>
            </div>
            <div class="col-md-3">
                <a href="listar_produtos.php" class="btn btn-success w-100 py-3">
                    <i class="fas fa-list mb-2"></i><br>
                    Listar Produtos
                </a>
            </div>
            <div class="col-md-3">
                <a href="gerar_catalogo.php" class="btn btn-info w-100 py-3">
                    <i class="fas fa-file-pdf mb-2"></i><br>
                    Gerar Catálogo
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= htmlspecialchars(luxConfig()['site_url']) ?>" target="_blank" rel="noopener" class="btn btn-warning w-100 py-3">
                    <i class="fas fa-eye mb-2"></i><br>
                    Ver Catálogo
                </a>
            </div>
        </div>
    </div>

    <!-- Download dos Catálogos -->
    <div class="container my-5">
        <h2 class="text-center mb-4"><i class="fas fa-download"></i> Download dos Catálogos</h2>
        <p class="text-center text-muted mb-4">Clique no catálogo desejado para gerar e baixar o PDF.</p>
        <div class="row g-3">
            <div class="col-md-4 col-lg-3">
                <a href="gerar_catalogo_arabes_novo.php" class="btn btn-outline-primary w-100 py-3" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf mb-2 d-block"></i> Arabes
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="gerar_catalogo_frances.php" class="btn btn-outline-primary w-100 py-3" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf mb-2 d-block"></i> Francês
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="gerar_catalogo_miniaturas.php" class="btn btn-outline-primary w-100 py-3" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf mb-2 d-block"></i> Miniaturas
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="gerar_catalogo_hidratantes.php" class="btn btn-outline-primary w-100 py-3" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf mb-2 d-block"></i> Hidratantes
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="gerar_catalogo_vs.php" class="btn btn-outline-primary w-100 py-3" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf mb-2 d-block"></i> Body-Splash
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="gerar_catalogo_sprays.php" class="btn btn-outline-primary w-100 py-3" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf mb-2 d-block"></i> Body-Sprays
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="gerar_catalogo_ciclo.php" class="btn btn-outline-danger w-100 py-3" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf mb-2 d-block"></i> Ciclo (nacionais)
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="gerar_catalogo_sem_preco.php" class="btn btn-outline-secondary w-100 py-3" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf mb-2 d-block"></i> Sem preço (Arabes)
                </a>
            </div>
            <div class="col-md-4 col-lg-3">
                <a href="gerar_catalogo_sem_preco_miniaturas.php" class="btn btn-outline-secondary w-100 py-3" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf mb-2 d-block"></i> Sem preço (Miniaturas)
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-spray-can"></i> Lux Catálogo</h5>
                    <p class="mb-0">Sistema de gerenciamento de produtos de perfumaria</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">
                        <i class="fas fa-code"></i> Desenvolvido com PHP e Bootstrap
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
