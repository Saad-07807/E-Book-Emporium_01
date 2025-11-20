
<?php
// register.php - Handle User Registration with Verification
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle verification code request
    if (isset($_POST['send_verification'])) {
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        
        // Validate all fields are filled
        if (empty($email) || empty($username) || empty($password) || empty($full_name)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit();
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit();
        }
        
        // Validate password length
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
            exit();
        }
        
        $conn = getDBConnection();
        
        // Check if user already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $check_stmt->bind_param("ss", $email, $username);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'User with this email or username already exists']);
            $check_stmt->close();
            $conn->close();
            exit();
        }
        $check_stmt->close();
        $conn->close();
        
        // Generate verification code
        $verification_code = rand(100000, 999999); // 6-digit code
        
        // Store all registration data in session
        $_SESSION['registration_data'] = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'full_name' => $full_name,
            'verification_code' => $verification_code,
            'verification_expiry' => time() + 600 // 10 minutes expiry
        ];
        
        // Return success with verification code for on-screen display
        echo json_encode([
            'success' => true, 
            'message' => '<div class="text-center"><i class="fas fa-shield-alt fa-2x mb-3 text-success"></i><h4>Your Verification Code</h4><div class="verification-code-display" style="font-size: 24px; font-weight: bold; color: var(--accent); padding: 10px; background: var(--secondary); border-radius: 5px; margin: 10px 0; border: 2px solid var(--accent);">' . $verification_code . '</div><small class="text-muted">This code will remain visible. Enter it below to complete registration.</small></div>',
            'code' => $verification_code
        ]);
        exit();
    }
    
    // Handle verification and registration
    if (isset($_POST['verify_and_register'])) {
        $entered_code = trim($_POST['verification_code']);
        
        // Check if registration session exists
        if (!isset($_SESSION['registration_data'])) {
            echo json_encode(['success' => false, 'message' => 'Registration session expired. Please start over.']);
            exit();
        }
        
        $reg_data = $_SESSION['registration_data'];
        
        // Verify the entered code matches
        if ($reg_data['verification_code'] != $entered_code) {
            echo json_encode(['success' => false, 'message' => 'Invalid verification code. Please try again.']);
            exit();
        }
        
        // Check if code has expired
        if (time() > $reg_data['verification_expiry']) {
            unset($_SESSION['registration_data']);
            echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please request a new one.']);
            exit();
        }
        
        $conn = getDBConnection();
        
        // Final check if user exists (prevent race condition)
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $check_stmt->bind_param("ss", $reg_data['email'], $reg_data['username']);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'User already exists. Please try different credentials.']);
            $check_stmt->close();
            $conn->close();
            unset($_SESSION['registration_data']);
            exit();
        }
        $check_stmt->close();
        
        // Create the user account
        $hashed_password = password_hash($reg_data['password'], PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, email_verified) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("ssss", $reg_data['username'], $reg_data['email'], $hashed_password, $reg_data['full_name']);
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            
            // Automatically log the user in
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $reg_data['full_name'];
            $_SESSION['user_role'] = 'user';
            $_SESSION['username'] = $reg_data['username'];
            $_SESSION['user_email'] = $reg_data['email'];
            
            // Clear the registration session data
            unset($_SESSION['registration_data']);
            
            echo json_encode(['success' => true, 'message' => 'Registration successful! Welcome to E-Book Emporium.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
        }
        
        $stmt->close();
        $conn->close();
        exit();
    }
}

// If accessed directly, redirect to homepage
header("Location: index.php");
exit();
?>
