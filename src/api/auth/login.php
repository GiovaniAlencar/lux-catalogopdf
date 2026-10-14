<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configuração do banco de dados
try {
    $pdo = new PDO('mysql:host=localhost;dbname=luxcatalogo;charset=utf8', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro na conexão com o banco de dados: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Recebe os dados do POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

// Validações
if (empty($email) || empty($password)) {
    echo json_encode(['error' => 'Email e senha são obrigatórios']);
    exit;
}

try {
    // Busca o usuário
    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, password, status, last_login 
        FROM ecommerce_users 
        WHERE email = ? AND status = 'active'
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['error' => 'Email ou senha incorretos']);
        exit;
    }

    // Verifica a senha
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['error' => 'Email ou senha incorretos']);
        exit;
    }

    // Atualiza o último login
    $stmt = $pdo->prepare("UPDATE ecommerce_users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Remove a senha da resposta
    unset($user['password']);

    echo json_encode([
        'success' => true,
        'user' => $user
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao fazer login: ' . $e->getMessage()]);
}
?>
