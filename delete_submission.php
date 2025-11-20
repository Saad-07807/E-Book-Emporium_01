
<?php
require_once 'config.php';

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $submission_id = intval($input['submission_id']);
    
    $conn = getDBConnection();
    
    // Delete from winners table if exists
    $conn->query("DELETE FROM winners WHERE submission_id = $submission_id");
    
    // Now delete the submission
    $stmt = $conn->prepare("DELETE FROM competition_submissions WHERE id = ?");
    $stmt->bind_param("i", $submission_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting submission']);
    }
    
    $stmt->close();
    $conn->close();
}
?>
