<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/lib/config.php';

/** Só aceita destino interno (evita redirecionar para outro site). */
function destinoSeguro(?string $d): string
{
    $d = (string) $d;
    if ($d === '' || preg_match('#^[a-z][a-z0-9+.-]*:|^//|^\\\\|[\r\n]#i', $d) || strpos($d, '\\') !== false) {
        return 'index.php';
    }
    return $d;
}

// Se já está logado, redireciona para o dashboard
if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . destinoSeguro($_GET['redirecionar'] ?? ''));
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        $erro = 'Preencha e-mail e senha.';
    } else {
        try {
            $pdo = luxDb('catalogo');
            $stmt = $pdo->prepare("SELECT id, nome, email, senha FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = (int) $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $destino = destinoSeguro($_POST['redirecionar'] ?? '');
                header('Location: ' . $destino);
                exit;
            }
        } catch (PDOException $e) {
            error_log('Login DB: ' . $e->getMessage());
        }
        $erro = 'E-mail ou senha inválidos.';
    }
}

$redirecionar = isset($_GET['redirecionar']) ? htmlspecialchars($_GET['redirecionar']) : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lux Catálogo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .login-card { box-shadow: 0 10px 40px rgba(0,0,0,0.2); border: none; border-radius: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card login-card">
                    <div class="card-body p-5">
                        <h4 class="text-center mb-4"><i class="fas fa-spray-can text-primary"></i> Lux Catálogo</h4>
                        <h5 class="text-center text-muted mb-4">Entrar</h5>
                        <?php if ($erro): ?>
                            <div class="alert alert-danger py-2"><?= htmlspecialchars($erro) ?></div>
                        <?php endif; ?>
                        <form method="post" action="login.php">
                            <?php if ($redirecionar): ?><input type="hidden" name="redirecionar" value="<?= htmlspecialchars($redirecionar) ?>"><?php endif; ?>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                            </div>
                            <div class="mb-4">
                                <label for="senha" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="senha" name="senha" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt me-2"></i>Entrar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
