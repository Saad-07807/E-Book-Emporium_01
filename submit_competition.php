<?php
require_once 'config.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to participate']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $competition_id = intval($_POST['competition_id']);
    $participant_name = trim($_POST['participant_name']);
    $participant_email = trim($_POST['participant_email']);
    $submission_title = trim($_POST['submission_title']);
    $submission_content = trim($_POST['submission_content']);
    $user_id = $_SESSION['user_id'];
    
    // Handle file upload
    $file_path = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/competitions/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION);
        $file_name = 'submission_' . time() . '_' . $user_id . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        // Validate file type
        $allowed_types = ['pdf', 'doc', 'docx', 'txt'];
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $file_path)) {
                // File uploaded successfully
            } else {
                $file_path = null;
            }
        }
    }
    
    $conn = getDBConnection();
    
    // Check if user already submitted for this competition
    $check_stmt = $conn->prepare("SELECT id FROM competition_submissions WHERE competition_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $competition_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already submitted an entry for this competition']);
        exit();
    }
    
    // Insert submission
    $stmt = $conn->prepare("INSERT INTO competition_submissions (competition_id, user_id, participant_name, participant_email, title, content, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssss", $competition_id, $user_id, $participant_name, $participant_email, $submission_title, $submission_content, $file_path);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Your submission has been received successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error submitting your entry. Please try again.']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>