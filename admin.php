<?php
require_once 'config.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    // Add new book
    if (isset($_POST['add_book'])) {
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $description = trim($_POST['description']);
        $preview_content = trim($_POST['preview_content']);
        $price = floatval($_POST['price']);
        $category_id = intval($_POST['category_id']);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $status = $_POST['status'];
        $cover_image = trim($_POST['cover_image']);
        
        // Handle file upload for cover image
        if (isset($_FILES['cover_image_file']) && $_FILES['cover_image_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/covers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['cover_image_file']['name'], PATHINFO_EXTENSION);
            $file_name = 'cover_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['cover_image_file']['tmp_name'], $file_path)) {
                $cover_image = $file_path;
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO books (title, author, description, preview_content, price, category_id, featured, status, cover_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdiiss", $title, $author, $description, $preview_content, $price, $category_id, $featured, $status, $cover_image);
        $stmt->execute();
        $stmt->close();
    }
    
    // Update book
    if (isset($_POST['update_book'])) {
        $book_id = intval($_POST['book_id']);
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $description = trim($_POST['description']);
        $preview_content = trim($_POST['preview_content']);
        $price = floatval($_POST['price']);
        $category_id = intval($_POST['category_id']);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $status = $_POST['status'];
        $cover_image = trim($_POST['cover_image']);
        
        // Handle file upload for cover image
        if (isset($_FILES['cover_image_file']) && $_FILES['cover_image_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/covers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['cover_image_file']['name'], PATHINFO_EXTENSION);
            $file_name = 'cover_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['cover_image_file']['tmp_name'], $file_path)) {
                $cover_image = $file_path;
            }
        }
        
        $stmt = $conn->prepare("UPDATE books SET title=?, author=?, description=?, preview_content=?, price=?, category_id=?, featured=?, status=?, cover_image=? WHERE id=?");
        $stmt->bind_param("ssssdiissi", $title, $author, $description, $preview_content, $price, $category_id, $featured, $status, $cover_image, $book_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Add new competition
    if (isset($_POST['add_competition'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $type = $_POST['type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $rules = trim($_POST['rules']);
        $prizes = trim($_POST['prizes']);
        $status = $_POST['status'];
        $max_submissions = intval($_POST['max_submissions']);
        $entry_fee = floatval($_POST['entry_fee']);
        
        $stmt = $conn->prepare("INSERT INTO competitions (title, description, type, start_date, end_date, rules, prizes, status, max_submissions, entry_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssid", $title, $description, $type, $start_date, $end_date, $rules, $prizes, $status, $max_submissions, $entry_fee);
        $stmt->execute();
        $stmt->close();
    }
    
    // Update competition
    if (isset($_POST['update_competition'])) {
        $competition_id = intval($_POST['competition_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $type = $_POST['type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $rules = trim($_POST['rules']);
        $prizes = trim($_POST['prizes']);
        $status = $_POST['status'];
        $max_submissions = intval($_POST['max_submissions']);
        $entry_fee = floatval($_POST['entry_fee']);
        
        $stmt = $conn->prepare("UPDATE competitions SET title=?, description=?, type=?, start_date=?, end_date=?, rules=?, prizes=?, status=?, max_submissions=?, entry_fee=? WHERE id=?");
        $stmt->bind_param("ssssssssidi", $title, $description, $type, $start_date, $end_date, $rules, $prizes, $status, $max_submissions, $entry_fee, $competition_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Update user role
    if (isset($_POST['update_user_role'])) {
        $user_id = intval($_POST['user_id']);
        $role = $_POST['role'];
        
        $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
        $stmt->bind_param("si", $role, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Update order status
    if (isset($_POST['update_order_status'])) {
        $order_id = intval($_POST['order_id']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();
        $stmt->close();
        
        // Send email notification
        $order_stmt = $conn->prepare("SELECT o.*, u.email, u.full_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
        $order_stmt->bind_param("i", $order_id);
        $order_stmt->execute();
        $order_result = $order_stmt->get_result();
        
        if ($order_result->num_rows === 1) {
            $order = $order_result->fetch_assoc();
            $subject = "Order Status Updated - E-Book Emporium";
            $message = "
                <html>
                <head>
                    <title>Order Status Update</title>
                </head>
                <body>
                    <h2>Hello " . $order['full_name'] . "!</h2>
                    <p>Your order #" . $order_id . " status has been updated to: <strong>" . ucfirst($status) . "</strong></p>
                    <p><strong>Format:</strong> " . ucfirst($order['format']) . "</p>
                    <p><strong>Total Amount:</strong> $" . number_format($order['total_amount'], 2) . "</p>
                    <p>Thank you for shopping with us!</p>
                </body>
                </html>
            ";
            
            sendEmail($order['email'], $subject, $message);
        }
        $order_stmt->close();
    }

    if (isset($_POST['mark_as_winner'])) {
        $submission_id = intval($_POST['submission_id']);
        $competition_id = intval($_POST['competition_id']);
        $user_id = intval($_POST['user_id']);
        $prize_details = trim($_POST['prize_details']);
        
        // Mark submission as winner
        $stmt = $conn->prepare("UPDATE competition_submissions SET is_winner=1 WHERE id=?");
        $stmt->bind_param("i", $submission_id);
        $stmt->execute();
        $stmt->close();
        
        // Add to winners table
        $stmt = $conn->prepare("INSERT INTO winners (competition_id, user_id, submission_id, prize_details) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $competition_id, $user_id, $submission_id, $prize_details);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
    
    // Redirect to prevent form resubmission
    header("Location: admin.php");
    exit();
}

// Get data for admin dashboard
$conn = getDBConnection();

// Statistics
$total_books = $conn->query("SELECT COUNT(*) as count FROM books WHERE status != 'deleted'")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1")->fetch_assoc()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status='paid'")->fetch_assoc()['total'] ?? 0;

// Recent orders
$recent_orders = [];
$result = $conn->query("
    SELECT o.*, u.full_name, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.order_date DESC 
    LIMIT 10
");
while($row = $result->fetch_assoc()) {
    $recent_orders[] = $row;
}

// Books
$books = [];
$result = $conn->query("
    SELECT b.*, c.name as category_name 
    FROM books b 
    LEFT JOIN categories c ON b.category_id = c.id 
    WHERE b.status != 'deleted'
    ORDER BY b.created_date DESC
");
while($row = $result->fetch_assoc()) {
    $books[] = $row;
}

// Categories
$categories = [];
$result = $conn->query("SELECT * FROM categories");
while($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Competitions
$competitions = [];
$result = $conn->query("SELECT * FROM competitions WHERE status != 'deleted' ORDER BY start_date DESC");
while($row = $result->fetch_assoc()) {
    $competitions[] = $row;
}

// Competition Submissions
$submissions = [];
$result = $conn->query("
    SELECT cs.*, c.title as competition_title, c.type as competition_type, 
           u.full_name, u.email, u.username,
           w.prize_details
    FROM competition_submissions cs
    JOIN competitions c ON cs.competition_id = c.id
    JOIN users u ON cs.user_id = u.id
    LEFT JOIN winners w ON cs.id = w.submission_id
    ORDER BY cs.submission_time DESC
");
while($row = $result->fetch_assoc()) {
    $submissions[] = $row;
}

// Winners
$winners = [];
$result = $conn->query("
    SELECT w.*, u.full_name, u.email, c.title as competition_title,
           cs.title as submission_title
    FROM winners w
    JOIN users u ON w.user_id = u.id
    JOIN competitions c ON w.competition_id = c.id
    JOIN competition_submissions cs ON w.submission_id = cs.id
    ORDER BY w.announced_date DESC
");
while($row = $result->fetch_assoc()) {
    $winners[] = $row;
}

// Users
$users = [];
$result = $conn->query("SELECT * FROM users ORDER BY registration_date DESC");
while($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - E-Book Emporium</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #f8f9fa;
            --secondary: #e9ecef;
            --accent: #703d0c;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --dark: #343a40;
            --light: #f8f9fa;
            --text: #212529;
            --text-light: #6c757d;
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
            transition: all 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: var(--dark);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: 12px 15px;
            margin: 2px 0;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: var(--accent);
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stat-card.primary { background: linear-gradient(45deg, #007bff, #0056b3); }
        .stat-card.success { background: linear-gradient(45deg, #28a745, #1e7e34); }
        .stat-card.warning { background: linear-gradient(45deg, #ffc107, #e0a800); }
        .stat-card.danger { background: linear-gradient(45deg, #dc3545, #c82333); }
        
        .table-actions {
            white-space: nowrap;
        }
        
        .badge-pending { background-color: var(--warning); color: #000; }
        .badge-confirmed { background-color: #17a2b8; }
        .badge-shipped { background-color: #6f42c1; }
        .badge-delivered { background-color: var(--success); }
        .badge-cancelled { background-color: var(--danger); }
        
        .submission-content {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--light);
            padding: 10px;
            border-radius: 5px;
            background: var(--secondary);
        }
        
        .preview-text {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--light);
            padding: 15px;
            border-radius: 5px;
            background: var(--secondary);
            line-height: 1.6;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .close-btn {
            background: none;
            border: none;
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 20px;
            cursor: pointer;
            color: var(--text);
            z-index: 1050;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .close-btn:hover {
            background-color: var(--light);
            color: var(--accent);
        }
        
        .modal-header {
            position: relative;
            background: var(--secondary);
            border-bottom: 1px solid var(--light);
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid var(--light);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: var(--secondary);
            border-bottom: 1px solid var(--light);
            color: var(--text);
        }
        
        .table {
            color: var(--text);
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0,0,0,0.02);
        }
        
        [data-theme="dark"] .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255,255,255,0.02);
        }
        
        .format-badge {
            font-size: 0.75em;
        }
        
        .shipping-charge {
            font-size: 0.8em;
            color: var(--success);
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-white">E-Book Emporium</h4>
            <p class="text-muted">Admin Panel</p>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link active" href="#dashboard">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a>
            <a class="nav-link" href="#books">
                <i class="fas fa-book me-2"></i>Books Management
            </a>
            <a class="nav-link" href="#orders">
                <i class="fas fa-shopping-cart me-2"></i>Orders
            </a>
            <a class="nav-link" href="#users">
                <i class="fas fa-users me-2"></i>Users
            </a>
            <a class="nav-link" href="#competitions">
                <i class="fas fa-trophy me-2"></i>Competitions
            </a>
            <a class="nav-link" href="#submissions">
                <i class="fas fa-file-upload me-2"></i>Submissions
            </a>
            <a class="nav-link" href="#winners">
                <i class="fas fa-award me-2"></i>Winners
            </a>
            <a class="nav-link" href="index.php">
                <i class="fas fa-home me-2"></i>Back to Site
            </a>
        </nav>
    </div>

    <div class="main-content">
        <!-- Dashboard Section -->
        <section id="dashboard">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Dashboard</h2>
                <span class="text-muted">Welcome, <?php echo $_SESSION['user_name']; ?></span>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card primary">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo $total_books; ?></h3>
                                <p>Total Books</p>
                            </div>
                            <i class="fas fa-book fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card success">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo $total_users; ?></h3>
                                <p>Total Users</p>
                            </div>
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card warning">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo $total_orders; ?></h3>
                                <p>Total Orders</p>
                            </div>
                            <i class="fas fa-shopping-cart fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card danger">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3>$<?php echo number_format($total_revenue, 2); ?></h3>
                                <p>Total Revenue</p>
                            </div>
                            <i class="fas fa-dollar-sign fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Orders</h5>
                            <a href="#orders" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Format</th>
                                            <th>Shipping</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($order['full_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                            </td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge format-badge 
                                                    <?php echo $order['format'] == 'pdf' ? 'bg-info' : 
                                                          ($order['format'] == 'hardcopy' ? 'bg-warning' : 'bg-secondary'); ?>">
                                                    <?php echo ucfirst($order['format']); ?>
                                                </span>
                                                <?php if($order['shipping_charges'] > 0): ?>
                                                    <div class="shipping-charge">+$<?php echo number_format($order['shipping_charges'], 2); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>$<?php echo number_format($order['shipping_charges'], 2); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                            <td class="table-actions">
                                                <button class="btn btn-sm btn-outline-primary" onclick="updateOrderStatus(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Books Management Section -->
        <section id="books" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Books Management</h2>
                <button class="btn btn-primary" onclick="showAddBookModal()">
                    <i class="fas fa-plus me-2"></i>Add New Book
                </button>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cover</th>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Price</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($books as $book): ?>
                                <tr>
                                    <td><?php echo $book['id']; ?></td>
                                    <td>
                                        <img src="<?php echo $book['cover_image']; ?>" alt="Cover" style="width: 50px; height: 60px; object-fit: cover; border-radius: 4px;">
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($book['title']); ?></div>
                                        <?php if($book['featured']): ?>
                                            <span class="badge bg-warning text-dark">Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td>$<?php echo number_format($book['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($book['category_name']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $book['status'] == 'active' ? 'bg-success' : ($book['status'] == 'inactive' ? 'bg-warning' : 'bg-secondary'); ?>">
                                            <?php echo ucfirst($book['status']); ?>
                                        </span>
                                    </td>
                                    <td class="table-actions">
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editBook(<?php echo htmlspecialchars(json_encode($book)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="toggleBookStatus(<?php echo $book['id']; ?>, '<?php echo $book['status']; ?>')">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteBook(<?php echo $book['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Orders Section -->
        <section id="orders" style="display: none;">
            <h2 class="mb-4">Orders Management</h2>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Format</th>
                                    <th>Shipping</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($order['full_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                    </td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge format-badge 
                                            <?php echo $order['format'] == 'pdf' ? 'bg-info' : 
                                                  ($order['format'] == 'hardcopy' ? 'bg-warning' : 'bg-secondary'); ?>">
                                            <?php echo ucfirst($order['format']); ?>
                                        </span>
                                        <?php if($order['shipping_charges'] > 0): ?>
                                            <div class="shipping-charge">+$<?php echo number_format($order['shipping_charges'], 2); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo number_format($order['shipping_charges'], 2); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($order['status']); ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $order['payment_status'] == 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                    <td class="table-actions">
                                        <button class="btn btn-sm btn-outline-primary" onclick="updateOrderStatus(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Users Section -->
        <section id="users" style="display: none;">
            <h2 class="mb-4">Users Management</h2>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['role'] == 'admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['registration_date'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <?php if(!$user['email_verified']): ?>
                                        <span class="badge bg-warning text-dark">Unverified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="table-actions">
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="toggleUserStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active']; ?>)">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Competitions Section -->
        <section id="competitions" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Competitions Management</h2>
                <button class="btn btn-primary" onclick="showAddCompetitionModal()">
                    <i class="fas fa-plus me-2"></i>Add New Competition
                </button>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($competitions as $competition): ?>
                                <tr>
                                    <td><?php echo $competition['id']; ?></td>
                                    <td><?php echo htmlspecialchars($competition['title']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $competition['type'] == 'essay' ? 'bg-primary' : 'bg-info'; ?>">
                                            <?php echo ucfirst($competition['type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($competition['start_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($competition['end_date'])); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php echo $competition['status'] == 'active' ? 'bg-success' : 
                                                  ($competition['status'] == 'upcoming' ? 'bg-warning' : 'bg-secondary'); ?>">
                                            <?php echo ucfirst($competition['status']); ?>
                                        </span>
                                    </td>
                                    <td class="table-actions">
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editCompetition(<?php echo htmlspecialchars(json_encode($competition)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="toggleCompetitionStatus(<?php echo $competition['id']; ?>, '<?php echo $competition['status']; ?>')">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCompetition(<?php echo $competition['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Competition Submissions Section -->
        <section id="submissions" style="display: none;">
            <h2 class="mb-4">Competition Submissions</h2>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Competition</th>
                                    <th>User</th>
                                    <th>Submission Title</th>
                                    <th>Type</th>
                                    <th>Submission Date</th>
                                    <th>Winner Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($submissions as $submission): ?>
                                <tr>
                                    <td><?php echo $submission['id']; ?></td>
                                    <td><?php echo htmlspecialchars($submission['competition_title']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($submission['full_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($submission['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($submission['title']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $submission['competition_type'] == 'essay' ? 'bg-primary' : 'bg-info'; ?>">
                                            <?php echo ucfirst($submission['competition_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($submission['submission_time'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $submission['is_winner'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $submission['is_winner'] ? 'Winner' : 'Not Winner'; ?>
                                        </span>
                                    </td>
                                    <td class="table-actions">
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-info" onclick="viewSubmission(<?php echo htmlspecialchars(json_encode($submission)); ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if(!$submission['is_winner']): ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="markAsWinner(<?php echo $submission['id']; ?>, <?php echo $submission['competition_id']; ?>, <?php echo $submission['user_id']; ?>)">
                                                <i class="fas fa-trophy"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteSubmission(<?php echo $submission['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Winners Section -->
        <section id="winners" style="display: none;">
            <h2 class="mb-4">Competition Winners</h2>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Competition</th>
                                    <th>Winner</th>
                                    <th>Submission Title</th>
                                    <th>Prize Details</th>
                                    <th>Announced Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($winners as $winner): ?>
                                <tr>
                                    <td><?php echo $winner['id']; ?></td>
                                    <td><?php echo htmlspecialchars($winner['competition_title']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($winner['full_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($winner['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($winner['submission_title']); ?></td>
                                    <td><?php echo htmlspecialchars($winner['prize_details']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($winner['announced_date'])); ?></td>
                                    <td class="table-actions">
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteWinner(<?php echo $winner['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Modals -->
    <!-- Add/Edit Book Modal -->
    <div class="modal fade" id="bookModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bookModalTitle">Add New Book</h5>
                    <button type="button" class="close-btn" data-bs-dismiss="modal">&times;</button>
                </div>
                <form method="POST" id="bookForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="book_id" id="book_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" class="form-control" name="title" id="book_title" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Author</label>
                                    <input type="text" class="form-control" name="author" id="book_author" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="book_description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preview Content</label>
                            <textarea class="form-control" name="preview_content" id="book_preview_content" rows="4"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Price</label>
                                    <input type="number" step="0.01" class="form-control" name="price" id="book_price" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-control" name="category_id" id="book_category" required>
                                        <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-control" name="status" id="book_status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cover Image URL</label>
                            <input type="url" class="form-control" name="cover_image" id="book_cover" placeholder="Or upload file below">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Or Upload Cover Image</label>
                            <input type="file" class="form-control" name="cover_image_file" id="book_cover_file" accept="image/*">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="featured" id="book_featured">
                            <label class="form-check-label">Featured Book</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_book" id="bookSubmitBtn">Add Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Competition Modal -->
    <div class="modal fade" id="competitionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="competitionModalTitle">Add New Competition</h5>
                    <button type="button" class="close-btn" data-bs-dismiss="modal">&times;</button>
                </div>
                <form method="POST" id="competitionForm">
                    <div class="modal-body">
                        <input type="hidden" name="competition_id" id="competition_id">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" id="competition_title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="competition_description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Type</label>
                                    <select class="form-control" name="type" id="competition_type" required>
                                        <option value="essay">Essay</option>
                                        <option value="story">Story</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-control" name="status" id="competition_status" required>
                                        <option value="upcoming">Upcoming</option>
                                        <option value="active">Active</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="datetime-local" class="form-control" name="start_date" id="competition_start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="datetime-local" class="form-control" name="end_date" id="competition_end_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rules</label>
                            <textarea class="form-control" name="rules" id="competition_rules" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Prizes</label>
                            <textarea class="form-control" name="prizes" id="competition_prizes" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Max Submissions</label>
                                    <input type="number" class="form-control" name="max_submissions" id="competition_max_submissions" value="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Entry Fee</label>
                                    <input type="number" step="0.01" class="form-control" name="entry_fee" id="competition_entry_fee" value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_competition" id="competitionSubmitBtn">Add Competition</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="close-btn" data-bs-dismiss="modal">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_user_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_user_email" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-control" name="role" id="edit_user_role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_user_role">Update Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Order Status Modal -->
    <div class="modal fade" id="updateOrderStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="close-btn" data-bs-dismiss="modal">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="update_order_id">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" id="update_order_status" required>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_order_status">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mark as Winner Modal -->
    <div class="modal fade" id="markWinnerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mark Submission as Winner</h5>
                    <button type="button" class="close-btn" data-bs-dismiss="modal">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="submission_id" id="winner_submission_id">
                        <input type="hidden" name="competition_id" id="winner_competition_id">
                        <input type="hidden" name="user_id" id="winner_user_id">
                        <div class="mb-3">
                            <label class="form-label">Prize Details</label>
                            <textarea class="form-control" name="prize_details" id="winner_prize_details" rows="3" required placeholder="Enter prize details (e.g., First Prize: $500 + Publication)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" name="mark_as_winner">Mark as Winner</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Submission Modal -->
    <div class="modal fade" id="viewSubmissionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submission Details</h5>
                    <button type="button" class="close-btn" data-bs-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Competition:</strong>
                            <span id="view_competition_title"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Submission Type:</strong>
                            <span id="view_submission_type"></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Submitted By:</strong>
                            <span id="view_submitter_name"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Email:</strong>
                            <span id="view_submitter_email"></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Submission Title:</strong>
                            <span id="view_submission_title"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Submission Date:</strong>
                            <span id="view_submission_date"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong>Content:</strong>
                        <div class="submission-content mt-2" id="view_submission_content"></div>
                    </div>
                    <div id="view_file_section" class="mb-3" style="display: none;">
                        <strong>Attached File:</strong>
                        <div class="mt-2">
                            <a href="#" id="view_file_link" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download me-2"></i>Download File
                            </a>
                        </div>
                    </div>
                    <div id="view_winner_section" class="mb-3" style="display: none;">
                        <strong>Winner Status:</strong>
                        <span class="badge bg-success">Winner</span>
                        <div class="mt-2">
                            <strong>Prize Details:</strong>
                            <span id="view_prize_details"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navigation
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href') === 'index.php') {
                    return;
                }
                
                e.preventDefault();
                
                document.querySelectorAll('.sidebar .nav-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                
                document.querySelectorAll('section').forEach(section => {
                    section.style.display = 'none';
                });
                
                const targetId = this.getAttribute('href').substring(1);
                document.getElementById(targetId).style.display = 'block';
            });
        });

        document.getElementById('dashboard').style.display = 'block';

        // Book Management Functions
        function showAddBookModal() {
            document.getElementById('bookModalTitle').textContent = 'Add New Book';
            document.getElementById('bookForm').reset();
            document.getElementById('book_id').value = '';
            document.getElementById('bookSubmitBtn').name = 'add_book';
            document.getElementById('bookSubmitBtn').textContent = 'Add Book';
            new bootstrap.Modal(document.getElementById('bookModal')).show();
        }

        function editBook(book) {
            document.getElementById('bookModalTitle').textContent = 'Edit Book';
            document.getElementById('book_id').value = book.id;
            document.getElementById('book_title').value = book.title;
            document.getElementById('book_author').value = book.author;
            document.getElementById('book_description').value = book.description || '';
            document.getElementById('book_preview_content').value = book.preview_content || '';
            document.getElementById('book_price').value = book.price;
            document.getElementById('book_category').value = book.category_id;
            document.getElementById('book_status').value = book.status;
            document.getElementById('book_cover').value = book.cover_image || '';
            document.getElementById('book_featured').checked = book.featured == 1;
            document.getElementById('bookSubmitBtn').name = 'update_book';
            document.getElementById('bookSubmitBtn').textContent = 'Update Book';
            new bootstrap.Modal(document.getElementById('bookModal')).show();
        }

        function toggleBookStatus(bookId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            if (confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this book?`)) {
                fetch('update_book_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        book_id: bookId,
                        status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error updating book status: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating book status');
                });
            }
        }

        function deleteBook(bookId) {
            if (confirm('Are you sure you want to permanently delete this book? This action cannot be undone.')) {
                fetch('delete_book.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ book_id: bookId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting book: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting book');
                });
            }
        }

        // Competition Management Functions
        function showAddCompetitionModal() {
            document.getElementById('competitionModalTitle').textContent = 'Add New Competition';
            document.getElementById('competitionForm').reset();
            document.getElementById('competition_id').value = '';
            document.getElementById('competitionSubmitBtn').name = 'add_competition';
            document.getElementById('competitionSubmitBtn').textContent = 'Add Competition';
            new bootstrap.Modal(document.getElementById('competitionModal')).show();
        }

        function editCompetition(competition) {
            document.getElementById('competitionModalTitle').textContent = 'Edit Competition';
            document.getElementById('competition_id').value = competition.id;
            document.getElementById('competition_title').value = competition.title;
            document.getElementById('competition_description').value = competition.description || '';
            document.getElementById('competition_type').value = competition.type;
            document.getElementById('competition_status').value = competition.status;
            document.getElementById('competition_start_date').value = competition.start_date.replace(' ', 'T');
            document.getElementById('competition_end_date').value = competition.end_date.replace(' ', 'T');
            document.getElementById('competition_rules').value = competition.rules || '';
            document.getElementById('competition_prizes').value = competition.prizes || '';
            document.getElementById('competition_max_submissions').value = competition.max_submissions;
            document.getElementById('competition_entry_fee').value = competition.entry_fee;
            document.getElementById('competitionSubmitBtn').name = 'update_competition';
            document.getElementById('competitionSubmitBtn').textContent = 'Update Competition';
            new bootstrap.Modal(document.getElementById('competitionModal')).show();
        }

        function toggleCompetitionStatus(competitionId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'completed' : 'active';
            if (confirm(`Are you sure you want to mark this competition as ${newStatus}?`)) {
                fetch('update_competition_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        competition_id: competitionId,
                        status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error updating competition status: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating competition status');
                });
            }
        }

        function deleteCompetition(competitionId) {
            if (confirm('Are you sure you want to permanently delete this competition? This action cannot be undone.')) {
                fetch('delete_competition.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ competition_id: competitionId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting competition: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting competition');
                });
            }
        }

        // User Management Functions
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_user_name').value = user.full_name;
            document.getElementById('edit_user_email').value = user.email;
            document.getElementById('edit_user_role').value = user.role;
            new bootstrap.Modal(document.getElementById('userModal')).show();
        }

        function toggleUserStatus(userId, isActive) {
            const newStatus = !isActive;
            if (confirm(`Are you sure you want to ${newStatus ? 'activate' : 'deactivate'} this user?`)) {
                fetch('update_user_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        user_id: userId,
                        is_active: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error updating user status: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating user status');
                });
            }
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to permanently delete this user? This action cannot be undone.')) {
                fetch('delete_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: userId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting user: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting user');
                });
            }
        }

        // Order status update
        function updateOrderStatus(orderId, currentStatus) {
            document.getElementById('update_order_id').value = orderId;
            document.getElementById('update_order_status').value = currentStatus;
            new bootstrap.Modal(document.getElementById('updateOrderStatusModal')).show();
        }

        // Submission management functions
        function deleteSubmission(submissionId) {
            if (confirm('Are you sure you want to delete this submission?')) {
                fetch('delete_submission.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ submission_id: submissionId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting submission: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting submission');
                });
            }
        }

        // Winner management functions
        function deleteWinner(winnerId) {
            if (confirm('Are you sure you want to delete this winner entry?')) {
                fetch('delete_winner.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ winner_id: winnerId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting winner: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting winner');
                });
            }
        }

        // Competition submission functions
        function viewSubmission(submission) {
            document.getElementById('view_competition_title').textContent = submission.competition_title;
            document.getElementById('view_submission_type').textContent = submission.competition_type;
            document.getElementById('view_submitter_name').textContent = submission.full_name;
            document.getElementById('view_submitter_email').textContent = submission.email;
            document.getElementById('view_submission_title').textContent = submission.title;
            document.getElementById('view_submission_date').textContent = new Date(submission.submission_time).toLocaleString();
            document.getElementById('view_submission_content').textContent = submission.content;
            
            if (submission.file_path) {
                document.getElementById('view_file_section').style.display = 'block';
                document.getElementById('view_file_link').href = submission.file_path;
            } else {
                document.getElementById('view_file_section').style.display = 'none';
            }
            
            if (submission.is_winner) {
                document.getElementById('view_winner_section').style.display = 'block';
                document.getElementById('view_prize_details').textContent = submission.prize_details || 'No prize details available';
            } else {
                document.getElementById('view_winner_section').style.display = 'none';
            }
            
            new bootstrap.Modal(document.getElementById('viewSubmissionModal')).show();
        }

        function markAsWinner(submissionId, competitionId, userId) {
            document.getElementById('winner_submission_id').value = submissionId;
            document.getElementById('winner_competition_id').value = competitionId;
            document.getElementById('winner_user_id').value = userId;
            document.getElementById('winner_prize_details').value = '';
            new bootstrap.Modal(document.getElementById('markWinnerModal')).show();
        }
    </script>
</body>
</html>