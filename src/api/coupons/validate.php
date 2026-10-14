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

$couponCode = trim($input['code'] ?? '');
$orderValue = (float)($input['orderValue'] ?? 0);

if (empty($couponCode)) {
    echo json_encode(['error' => 'Código do cupom é obrigatório']);
    exit;
}

try {
    // Busca o cupom
    $stmt = $pdo->prepare("
        SELECT id, code, description, discount_type, discount_value, 
               min_order_value, max_discount, usage_limit, used_count,
               valid_from, valid_until, status
        FROM ecommerce_coupons 
        WHERE code = ? AND status = 'active'
    ");
    $stmt->execute([$couponCode]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        echo json_encode(['error' => 'Cupom não encontrado ou inativo']);
        exit;
    }

    // Verifica se o cupom está dentro do prazo
    $now = new DateTime();
    if ($coupon['valid_from'] && new DateTime($coupon['valid_from']) > $now) {
        echo json_encode(['error' => 'Cupom ainda não está válido']);
        exit;
    }

    if ($coupon['valid_until'] && new DateTime($coupon['valid_until']) < $now) {
        echo json_encode(['error' => 'Cupom expirado']);
        exit;
    }

    // Verifica valor mínimo do pedido
    if ($coupon['min_order_value'] > 0 && $orderValue < $coupon['min_order_value']) {
        echo json_encode([
            'error' => 'Valor mínimo do pedido não atingido',
            'minValue' => $coupon['min_order_value']
        ]);
        exit;
    }

    // Verifica limite de uso
    if ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
        echo json_encode(['error' => 'Cupom esgotado']);
        exit;
    }

    // Calcula o desconto
    $discountAmount = 0;
    if ($coupon['discount_type'] === 'percentage') {
        $discountAmount = ($orderValue * $coupon['discount_value']) / 100;
        if ($coupon['max_discount'] && $discountAmount > $coupon['max_discount']) {
            $discountAmount = $coupon['max_discount'];
        }
    } else {
        $discountAmount = $coupon['discount_value'];
    }

    echo json_encode([
        'success' => true,
        'coupon' => [
            'id' => $coupon['id'],
            'code' => $coupon['code'],
            'description' => $coupon['description'],
            'discountType' => $coupon['discount_type'],
            'discountValue' => (float)$coupon['discount_value'],
            'discountAmount' => $discountAmount,
            'minOrderValue' => (float)$coupon['min_order_value'],
            'maxDiscount' => $coupon['max_discount'] ? (float)$coupon['max_discount'] : null
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao validar cupom: ' . $e->getMessage()]);
}
?>
