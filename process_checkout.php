<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header("Location: index.php?error=login_required");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required_fields = ['customer_name', 'customer_email', 'customer_phone', 'format', 'shipping_address'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            header("Location: checkout.php?error=missing_fields");
            exit();
        }
    }

    // Get cart from session
    $cart = $_SESSION['cart'] ?? [];
    
    if (empty($cart)) {
        header("Location: checkout.php?error=empty_cart");
        exit();
    }

    $conn = getDBConnection();
    
    // Calculate base total
    $base_amount = floatval($_POST['base_amount']);
    $format = $_POST['format'];
    
    // Calculate additional charges based on format
    $shipping_charges = 0;
    switch ($format) {
        case 'hardcopy':
            $shipping_charges = 5.99;
            break;
        case 'cd':
            $shipping_charges = 3.99;
            break;
        case 'pdf':
        default:
            $shipping_charges = 0;
            break;
    }
    
    $total_amount = $base_amount + $shipping_charges;
    
    // Create order
    $user_id = $_SESSION['user_id'];
    $customer_name = trim($_POST['customer_name']);
    $customer_email = trim($_POST['customer_email']);
    $customer_phone = trim($_POST['customer_phone']);
    $shipping_address = trim($_POST['shipping_address']);
    
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, format, shipping_charges, shipping_address, customer_name, customer_email, customer_phone, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')");
    $stmt->bind_param("idssssss", $user_id, $total_amount, $format, $shipping_charges, $shipping_address, $customer_name, $customer_email, $customer_phone);
    
    if ($stmt->execute()) {
        $order_id = $conn->insert_id;
        
        // Add order items
        foreach ($cart as $item) {
            $book_stmt = $conn->prepare("INSERT INTO order_items (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)");
            $book_stmt->bind_param("iiid", $order_id, $item['id'], $item['quantity'], $item['price']);
            $book_stmt->execute();
            $book_stmt->close();
        }
        
        // Clear cart
        $_SESSION['cart'] = [];
        
        // Send confirmation email
        $subject = "Order Confirmation - E-Book Emporium";
        $format_display = ucfirst($format);
        $shipping_display = $shipping_charges > 0 ? " (+$$shipping_charges shipping)" : "";
        
        $message = "
            <html>
            <head>
                <title>Order Confirmation</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #703d0c; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f8f4f0; }
                    .order-details { background: white; padding: 15px; border-radius: 5px; margin: 10px 0; }
                    .footer { text-align: center; padding: 20px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>E-Book Emporium</h1>
                        <h2>Order Confirmation</h2>
                    </div>
                    <div class='content'>
                        <h3>Thank you for your order, $customer_name!</h3>
                        <p>Your order #$order_id has been received and is being processed.</p>
                        
                        <div class='order-details'>
                            <h4>Order Details:</h4>
                            <p><strong>Order Total:</strong> $$total_amount</p>
                            <p><strong>Format:</strong> $format_display$shipping_display</p>
                            <p><strong>Shipping Address:</strong> $shipping_address</p>
                        </div>
                        
                        <p>We will notify you when your order ships. For digital downloads, you will receive access instructions shortly.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2024 E-Book Emporium. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        sendEmail($customer_email, $subject, $message);
        
        header("Location: index.php?message=order_success&order_id=$order_id");
    } else {
        header("Location: checkout.php?error=order_failed");
    }
    
    $stmt->close();
    $conn->close();
    exit();
}

header("Location: checkout.php");
exit();
?>