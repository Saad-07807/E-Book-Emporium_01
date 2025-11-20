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
    $status = $input['status'];
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE books SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $book_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating book status']);
    }
    
    $stmt->close();
    $conn->close();
}
?>