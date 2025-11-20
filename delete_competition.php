
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
    
    $conn = getDBConnection();
    
    // Delete related records first
    $conn->query("DELETE FROM competition_submissions WHERE competition_id = $competition_id");
    $conn->query("DELETE FROM winners WHERE competition_id = $competition_id");
    
    // Now delete the competition
    $stmt = $conn->prepare("DELETE FROM competitions WHERE id = ?");
    $stmt->bind_param("i", $competition_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting competition']);
    }
    
    $stmt->close();
    $conn->close();
}
?>
