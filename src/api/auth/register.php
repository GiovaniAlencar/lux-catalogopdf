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

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$password = $input['password'] ?? '';

// Validações
if (empty($name) || empty($email) || empty($phone) || empty($password)) {
    echo json_encode(['error' => 'Todos os campos são obrigatórios']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['error' => 'A senha deve ter pelo menos 6 caracteres']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Email inválido']);
    exit;
}

try {
    // Verifica se o email já existe
    $stmt = $pdo->prepare("SELECT id FROM ecommerce_users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['error' => 'Este email já está cadastrado']);
        exit;
    }

    // Verifica se o telefone já existe
    $stmt = $pdo->prepare("SELECT id FROM ecommerce_users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        echo json_encode(['error' => 'Este telefone já está cadastrado']);
        exit;
    }

    // Criptografa a senha
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insere o usuário
    $stmt = $pdo->prepare("
        INSERT INTO ecommerce_users (name, email, phone, password, status) 
        VALUES (?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$name, $email, $phone, $hashedPassword]);

    $userId = $pdo->lastInsertId();

    // Log da criação
    $stmt = $pdo->prepare("
        INSERT INTO ecommerce_sync_logs (entity_type, entity_id, action, status, message) 
        VALUES ('customer', ?, 'create', 'success', 'Usuário criado com sucesso')
    ");
    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Usuário criado com sucesso',
        'userId' => $userId
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao criar usuário: ' . $e->getMessage()]);
}
?>
