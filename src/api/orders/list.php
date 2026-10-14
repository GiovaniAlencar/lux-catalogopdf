<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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

try {
    $phone = $_GET['phone'] ?? null;
    $userId = $_GET['userId'] ?? null;

    if (!$phone && !$userId) {
        echo json_encode(['error' => 'Telefone ou userId é obrigatório']);
        exit;
    }

    // Busca pedidos
    $whereClause = '';
    $params = [];

    if ($phone) {
        $whereClause = 'WHERE customer_phone = ?';
        $params[] = $phone;
    } elseif ($userId) {
        $whereClause = 'WHERE user_id = ?';
        $params[] = $userId;
    }

    $stmt = $pdo->prepare("
        SELECT 
            o.id, o.order_id, o.customer_name, o.customer_email, o.customer_phone,
            o.subtotal, o.discount, o.total, o.status, o.payment_method,
            o.created_at, o.updated_at,
            GROUP_CONCAT(
                CONCAT(oi.quantity, 'x ', oi.product_name, ' - R$ ', oi.total_price) 
                SEPARATOR '; '
            ) as items_summary
        FROM ecommerce_orders o
        LEFT JOIN ecommerce_order_items oi ON o.id = oi.order_id
        $whereClause
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");

    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formata os pedidos
    $formattedOrders = [];
    foreach ($orders as $order) {
        $formattedOrders[] = [
            'id' => $order['order_id'],
            'customer' => [
                'name' => $order['customer_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone']
            ],
            'subtotal' => (float)$order['subtotal'],
            'discount' => (float)$order['discount'],
            'total' => (float)$order['total'],
            'status' => $order['status'],
            'paymentMethod' => $order['payment_method'],
            'createdAt' => $order['created_at'],
            'updatedAt' => $order['updated_at'],
            'itemsSummary' => $order['items_summary']
        ];
    }

    echo json_encode($formattedOrders);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao buscar pedidos: ' . $e->getMessage()]);
}
?>
