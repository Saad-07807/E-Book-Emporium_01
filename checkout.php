<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - E-Book Emporium</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #f8f4f0;
            --secondary: #e9e1d8;
            --accent: #703d0c;
            --light: #d7c9b9;
            --dark: #4a3c2a;
            --text: #2d2419;
            --text-light: #6b5d4d;
            --success: #8b7355;
            --card-bg: #ffffff;
            --header-bg: #ffffff;
        }

        [data-theme="dark"] {
            --primary: #121212;
            --secondary: #1e1e1e;
            --accent: #703d0c;
            --light: #2d2d2d;
            --dark: #0a0a0a;
            --text: #e0e0e0;
            --text-light: #a0a0a0;
            --success: #8b5a2b;
            --card-bg: #1e1e1e;
            --header-bg: #1a1a1a;
        }

        body {
            background-color: var(--primary);
            color: var(--text);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .checkout-container {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 20px;
            border: 1px solid var(--light);
        }
        
        .order-summary {
            background: var(--secondary);
            border-radius: 8px;
            padding: 20px;
            border: 1px solid var(--light);
        }
        
        .format-option {
            border: 2px solid var(--light);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--card-bg);
        }
        
        .format-option.selected {
            border-color: var(--accent);
            background-color: rgba(112, 61, 12, 0.1);
        }
        
        .format-option input[type="radio"] {
            margin-right: 10px;
        }
        
        .additional-charge {
            color: var(--success);
            font-weight: bold;
        }
        
        .btn-primary {
            background-color: var(--accent);
            border-color: var(--accent);
        }
        
        .btn-primary:hover {
            background-color: #6d4520;
            border-color: #6d4520;
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid var(--light);
        }
        
        .card-header {
            background: var(--secondary);
            border-bottom: 1px solid var(--light);
            color: var(--text);
        }
        
        .form-control {
            background: var(--secondary);
            border: 1px solid var(--light);
            color: var(--text);
        }
        
        .form-control:focus {
            background: var(--secondary);
            border-color: var(--accent);
            color: var(--text);
        }
        
        .alert {
            border: 1px solid var(--light);
        }
    </style>
</head>
<body>
    <?php 
    require_once 'config.php';
    
    if (!isLoggedIn()) {
        header("Location: index.php?error=login_required");
        exit();
    }
    
    // Get cart from session
    $cart = $_SESSION['cart'] ?? [];
    
    if (empty($cart)) {
        header("Location: index.php?error=empty_cart");
        exit();
    }
    
    // Calculate total
    $total = 0;
    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    ?>
    
    <!-- Header -->
    <header class="header" style="background: var(--header-bg); border-bottom: 1px solid var(--light); padding: 1rem 0;">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="index.php" class="text-decoration-none">
                    <h3 class="mb-0" style="color: var(--accent);">
                        <i class="fas fa-book"></i> E-Book Emporium
                    </h3>
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Continue Shopping
                </a>
            </div>
        </div>
    </header>

    <div class="container mt-4 mb-5">
        <div class="row">
            <div class="col-md-8">
                <div class="checkout-container">
                    <h2 class="mb-4" style="color: var(--accent);">Checkout</h2>
                    
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger">
                            <?php
                            switch ($_GET['error']) {
                                case 'missing_fields':
                                    echo 'Please fill in all required fields.';
                                    break;
                                case 'empty_cart':
                                    echo 'Your cart is empty. Please add items before checking out.';
                                    break;
                                default:
                                    echo 'An error occurred. Please try again.';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="process_checkout.php">
                        <input type="hidden" name="base_amount" value="<?php echo $total; ?>">
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Customer Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Full Name *</label>
                                            <input type="text" class="form-control" name="customer_name" value="<?php echo $_SESSION['user_name']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email *</label>
                                            <input type="email" class="form-control" name="customer_email" value="<?php echo $_SESSION['user_email']; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" name="customer_phone" required>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Delivery Format</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Select Format *</label>
                                    <div class="format-options">
                                        <div class="format-option selected" onclick="selectFormat('pdf')">
                                            <input type="radio" name="format" value="pdf" id="format-pdf" checked>
                                            <label for="format-pdf" class="mb-0">
                                                <strong>PDF (Digital Download)</strong>
                                                <div class="text-muted">Instant access after purchase</div>
                                                <div class="additional-charge">+ $0.00</div>
                                            </label>
                                        </div>
                                        <div class="format-option" onclick="selectFormat('hardcopy')">
                                            <input type="radio" name="format" value="hardcopy" id="format-hardcopy">
                                            <label for="format-hardcopy" class="mb-0">
                                                <strong>Hard Copy</strong>
                                                <div class="text-muted">Printed book delivered to your address</div>
                                                <div class="additional-charge">+ $5.99 (Shipping included)</div>
                                            </label>
                                        </div>
                                        <div class="format-option" onclick="selectFormat('cd')">
                                            <input type="radio" name="format" value="cd" id="format-cd">
                                            <label for="format-cd" class="mb-0">
                                                <strong>CD</strong>
                                                <div class="text-muted">Physical CD with eBook files</div>
                                                <div class="additional-charge">+ $3.99 (Shipping included)</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Shipping Address *</label>
                                    <textarea class="form-control" name="shipping_address" rows="4" required placeholder="Enter your complete shipping address"></textarea>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 py-3">
                            <i class="fas fa-shopping-bag me-2"></i>Place Order
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="order-summary">
                    <h5 class="mb-3" style="color: var(--accent);">Order Summary</h5>
                    <?php foreach($cart as $item): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?php echo htmlspecialchars($item['title']); ?> x<?php echo $item['quantity']; ?></span>
                        <span>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span>$<?php echo number_format($total, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Format Charge:</span>
                        <span id="format-charge">$0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Total</span>
                        <span id="order-total">$<?php echo number_format($total, 2); ?></span>
                    </div>
                </div>
                
                <div class="mt-4 p-3" style="background: var(--card-bg); border-radius: 8px; border: 1px solid var(--light);">
                    <h6 class="mb-3">Secure Checkout</h6>
                    <div class="d-flex gap-2 mb-3">
                        <i class="fas fa-lock text-success"></i>
                        <small class="text-muted">Your payment information is secure and encrypted</small>
                    </div>
                    <div class="d-flex gap-2">
                        <i class="fas fa-shield-alt text-success"></i>
                        <small class="text-muted">SSL protected checkout</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const baseAmount = <?php echo $total; ?>;
        const formatCharges = {
            'pdf': 0,
            'hardcopy': 5.99,
            'cd': 3.99
        };

        function selectFormat(format) {
            // Update radio button
            document.getElementById(`format-${format}`).checked = true;
            
            // Update UI
            document.querySelectorAll('.format-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // Calculate and update totals
            const formatCharge = formatCharges[format];
            const totalAmount = baseAmount + formatCharge;
            
            document.getElementById('format-charge').textContent = `$${formatCharge.toFixed(2)}`;
            document.getElementById('order-total').textContent = `$${totalAmount.toFixed(2)}`;
        }

        // Initialize with PDF selected
        document.addEventListener('DOMContentLoaded', function() {
            selectFormat('pdf');
        });
    </script>
</body>
</html>