
<?php
require_once 'config.php';

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = intval($input['user_id']);
    
    $conn = getDBConnection();
    
    // Delete related order items first
    $order_result = $conn->query("SELECT id FROM orders WHERE user_id = $user_id");
    while ($order = $order_result->fetch_assoc()) {
        $conn->query("DELETE FROM order_items WHERE order_id = " . $order['id']);
    }
    
    // Delete all related records
    $conn->query("DELETE FROM orders WHERE user_id = $user_id");
    $conn->query("DELETE FROM competition_submissions WHERE user_id = $user_id");
    $conn->query("DELETE FROM winners WHERE user_id = $user_id");
    $conn->query("DELETE FROM book_reviews WHERE user_id = $user_id");
    $conn->query("DELETE FROM subscriptions WHERE user_id = $user_id");
    
    // Now delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting user']);
    }
    
    $stmt->close();
    $conn->close();
}
?>
