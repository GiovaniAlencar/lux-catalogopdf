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

$orderId = $input['orderId'] ?? '';

if (empty($orderId)) {
    echo json_encode(['error' => 'ID do pedido é obrigatório']);
    exit;
}

try {
    // Busca o pedido
    $stmt = $pdo->prepare("
        SELECT 
            o.order_id, o.customer_name, o.customer_email, o.customer_phone,
            o.subtotal, o.discount, o.total, o.status, o.created_at,
            oi.product_name, oi.product_price, oi.quantity, oi.total_price
        FROM ecommerce_orders o
        LEFT JOIN ecommerce_order_items oi ON o.id = oi.order_id
        WHERE o.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $orderData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orderData)) {
        echo json_encode(['error' => 'Pedido não encontrado']);
        exit;
    }

    $order = $orderData[0];

    // Monta a mensagem do WhatsApp
    $message = "*NOVO PEDIDO - LUX PERFUMES*\n\n";
    $message .= "*Cliente:*\n";
    $message .= "• Nome: " . $order['customer_name'] . "\n";
    $message .= "• Email: " . $order['customer_email'] . "\n";
    $message .= "• Telefone: " . $order['customer_phone'] . "\n\n";
    
    $message .= "*Pedido #" . $order['order_id'] . "*\n";
    $message .= "• Data: " . date('d/m/Y H:i', strtotime($order['created_at'])) . "\n\n";
    
    $message .= "*ITENS:*\n";
    foreach ($orderData as $item) {
        $message .= "• " . $item['quantity'] . "x " . $item['product_name'] . "\n";
        $message .= "  R$ " . number_format($item['product_price'], 2, ',', '.') . " = R$ " . number_format($item['total_price'], 2, ',', '.') . "\n";
    }
    
    $message .= "\n*RESUMO:*\n";
    $message .= "• Subtotal: R$ " . number_format($order['subtotal'], 2, ',', '.') . "\n";
    if ($order['discount'] > 0) {
        $message .= "• Desconto: -R$ " . number_format($order['discount'], 2, ',', '.') . "\n";
    }
    $message .= "• *TOTAL: R$ " . number_format($order['total'], 2, ',', '.') . "*\n\n";
    
    $message .= "Status: " . ucfirst(str_replace('_', ' ', $order['status'])) . "\n\n";
    $message .= "Acesse o painel administrativo para gerenciar este pedido.";

    // Atualiza o pedido com a mensagem
    $stmt = $pdo->prepare("UPDATE ecommerce_orders SET whatsapp_message = ? WHERE order_id = ?");
    $stmt->execute([$message, $orderId]);

    // Log da ação
    $stmt = $pdo->prepare("
        INSERT INTO ecommerce_sync_logs (entity_type, entity_id, action, status, message) 
        VALUES ('order', ?, 'whatsapp', 'success', 'Mensagem WhatsApp gerada')
    ");
    $stmt->execute([$orderId]);

    echo json_encode([
        'success' => true,
        'message' => $message,
        'whatsappUrl' => 'https://wa.me/5511999999999?text=' . urlencode($message)
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao gerar mensagem WhatsApp: ' . $e->getMessage()]);
}
?>
