
<?php
require_once 'config.php';

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $book_id = intval($input['book_id']);
    
    $conn = getDBConnection();
    
    // Delete related records first to maintain referential integrity
    $conn->query("DELETE FROM order_items WHERE book_id = $book_id");
    $conn->query("DELETE FROM book_reviews WHERE book_id = $book_id");
    $conn->query("DELETE FROM subscriptions WHERE book_id = $book_id");
    
    // Now delete the book
    $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting book']);
    }
    
    $stmt->close();
    $conn->close();
}
?>
