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
    $stmt = $conn->prepare("UPDATE books SET status = 'inactive' WHERE id = ?");
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