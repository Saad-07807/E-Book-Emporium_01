<?php
require_once 'config.php';

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $competition_id = intval($input['competition_id']);
    $status = $input['status'];
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE competitions SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $competition_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating competition status']);
    }
    
    $stmt->close();
    $conn->close();
}
?>