
<?php
require_once 'config.php';

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $winner_id = intval($input['winner_id']);
    
    $conn = getDBConnection();
    
    // Update the submission to remove winner status
    $winner_stmt = $conn->prepare("SELECT submission_id FROM winners WHERE id = ?");
    $winner_stmt->bind_param("i", $winner_id);
    $winner_stmt->execute();
    $result = $winner_stmt->get_result();
    
    if ($result->num_rows === 1) {
        $winner = $result->fetch_assoc();
        $conn->query("UPDATE competition_submissions SET is_winner = 0 WHERE id = " . $winner['submission_id']);
    }
    $winner_stmt->close();
    
    // Now delete the winner entry
    $stmt = $conn->prepare("DELETE FROM winners WHERE id = ?");
    $stmt->bind_param("i", $winner_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting winner']);
    }
    
    $stmt->close();
    $conn->close();
}
?>
