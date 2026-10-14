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

try {
    $pdo->beginTransaction();

    // Gera ID único do pedido
    $orderId = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

    // Insere o pedido
    $stmt = $pdo->prepare("
        INSERT INTO ecommerce_orders (
            order_id, user_id, customer_name, customer_email, customer_phone,
            subtotal, discount, total, status, payment_method, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $orderId,
        $input['userId'] ?? null,
        $input['customer']['name'],
        $input['customer']['email'],
        $input['customer']['phone'],
        $input['subtotal'],
        $input['discount'] ?? 0,
        $input['total'],
        'aguardando_confirmacao',
        'whatsapp',
        $input['notes'] ?? null
    ]);

    $orderDbId = $pdo->lastInsertId();

    // Insere os itens do pedido
    $stmt = $pdo->prepare("
        INSERT INTO ecommerce_order_items (
            order_id, product_id, product_name, product_price, quantity, total_price
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($input['items'] as $item) {
        $stmt->execute([
            $orderDbId,
            $item['productId'],
            $item['productName'] ?? 'Produto',
            $item['price'],
            $item['quantity'],
            $item['price'] * $item['quantity']
        ]);
    }

    // Se foi usado cupom, registra o uso
    if (!empty($input['couponCode'])) {
        $stmt = $pdo->prepare("
            SELECT id, discount_type, discount_value, max_discount 
            FROM ecommerce_coupons 
            WHERE code = ? AND status = 'active' 
            AND (valid_until IS NULL OR valid_until > NOW())
            AND (usage_limit IS NULL OR used_count < usage_limit)
        ");
        $stmt->execute([$input['couponCode']]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($coupon) {
            // Calcula o desconto
            $discountAmount = 0;
            if ($coupon['discount_type'] === 'percentage') {
                $discountAmount = ($input['subtotal'] * $coupon['discount_value']) / 100;
                if ($coupon['max_discount'] && $discountAmount > $coupon['max_discount']) {
                    $discountAmount = $coupon['max_discount'];
                }
            } else {
                $discountAmount = $coupon['discount_value'];
            }

            // Registra o uso do cupom
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_coupon_usage (coupon_id, order_id, user_id, discount_amount) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$coupon['id'], $orderDbId, $input['userId'] ?? null, $discountAmount]);

            // Atualiza contador de uso
            $stmt = $pdo->prepare("UPDATE ecommerce_coupons SET used_count = used_count + 1 WHERE id = ?");
            $stmt->execute([$coupon['id']]);
        }
    }

    $pdo->commit();

    // Log da criação
    $stmt = $pdo->prepare("
        INSERT INTO ecommerce_sync_logs (entity_type, entity_id, action, status, message) 
        VALUES ('order', ?, 'create', 'success', 'Pedido criado com sucesso')
    ");
    $stmt->execute([$orderId]);

    echo json_encode([
        'success' => true,
        'orderId' => $orderId,
        'message' => 'Pedido criado com sucesso'
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Erro ao criar pedido: ' . $e->getMessage()]);
}
?>
