<?php
require_once 'config.php';

if (isset($_GET['code'])) {
    $verification_code = $_GET['code'];
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, username, full_name, email, verification_expiry FROM users WHERE verification_code = ? AND email_verified = 0");
    $stmt->bind_param("s", $verification_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if verification code is expired
        if (strtotime($user['verification_expiry']) > time()) {
            // Verify email
            $update_stmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, verification_expiry = NULL WHERE id = ?");
            $update_stmt->bind_param("i", $user['id']);
            
            if ($update_stmt->execute()) {
                // Auto login after verification
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = 'user';
                
                header("Location: index.php?message=email_verified");
            } else {
                header("Location: index.php?error=verification_failed");
            }
            $update_stmt->close();
        } else {
            header("Location: index.php?error=verification_expired");
        }
    } else {
        header("Location: index.php?error=invalid_verification");
    }
    
    $stmt->close();
    $conn->close();
    exit();
}

header("Location: index.php");
exit();
?>