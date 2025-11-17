
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
    
    if (isset($_POST['update_book'])) {
        $book_id = intval($_POST['book_id']);
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $description = trim($_POST['description']);
        $preview_content = trim($_POST['preview_content']);
        $price = floatval($_POST['price']);
        $category_id = intval($_POST['category_id']);
        $is_free = isset($_POST['is_free']) ? 1 : 0;
        $featured = isset($_POST['featured']) ? 1 : 0;
        $stock_quantity = intval($_POST['stock_quantity']);
        $pages = intval($_POST['pages']);
        $publication_year = intval($_POST['publication_year']);
        $isbn = trim($_POST['isbn']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE books SET title=?, author=?, description=?, preview_content=?, price=?, category_id=?, is_free=?, featured=?, stock_quantity=?, pages=?, publication_year=?, isbn=?, status=? WHERE id=?");
        $stmt->bind_param("ssssdiisiiissi", $title, $author, $description, $preview_content, $price, $category_id, $is_free, $featured, $stock_quantity, $pages, $publication_year, $isbn, $status, $book_id);
        $stmt->execute();
        $stmt->close();
    }
    
    if (isset($_POST['update_order_status'])) {
        $order_id = intval($_POST['order_id']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();
        $stmt->close();
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
$total_books = $conn->query("SELECT COUNT(*) as count FROM books")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
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
$result = $conn->query("SELECT * FROM competitions ORDER BY start_date DESC");
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
$result = $conn->query("SELECT * FROM users ORDER BY registration_date DESC LIMIT 10");
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
        }
        
        .sidebar {
            background: var(--dark);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: 10px 15px;
            margin: 2px 0;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,.1);
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
        }
        
        .stat-card.primary { background: linear-gradient(45deg, #007bff, #0056b3); }
        .stat-card.success { background: linear-gradient(45deg, #28a745, #1e7e34); }
        .stat-card.warning { background: linear-gradient(45deg, #ffc107, #e0a800); }
        .stat-card.danger { background: linear-gradient(45deg, #dc3545, #c82333); }
        
        .table-actions {
            white-space: nowrap;
        }
        
        .badge-pending { background-color: var(--warning); }
        .badge-confirmed { background-color: var(--success); }
        .badge-shipped { background-color: #17a2b8; }
        .badge-delivered { background-color: var(--success); }
        .badge-cancelled { background-color: var(--danger); }
        
        .submission-content {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            padding: 10px;
            border-radius: 5px;
            background: #f8f9fa;
        }
        
        .preview-text {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 5px;
            background: #f8f9fa;
            line-height: 1.6;
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
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
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
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($books as $book): ?>
                                <tr>
                                    <td><?php echo $book['id']; ?></td>
                                    <td>
                                        <img src="<?php echo $book['cover_image']; ?>" alt="Cover" style="width: 50px; height: 60px; object-fit: cover;">
                                    </td>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td>$<?php echo number_format($book['price'], 2); ?></td>
                                    <td><?php echo $book['stock_quantity']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $book['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo ucfirst($book['status']); ?>
                                        </span>
                                    </td>
                                    <td class="table-actions">
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteBook(<?php echo $book['id']; ?>)">
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
                                    <td><?php echo ucfirst($order['format']); ?></td>
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
                                    </td>
                                    <td class="table-actions">
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
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

        <!-- Competitions Section -->
        <section id="competitions" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Competitions Management</h2>
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
                                    <td><?php echo ucfirst($competition['type']); ?></td>
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
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCompetition(<?php echo $competition['id']; ?>)">
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
                                    <td><?php echo ucfirst($submission['competition_type']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($submission['submission_time'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $submission['is_winner'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $submission['is_winner'] ? 'Winner' : 'Not Winner'; ?>
                                        </span>
                                    </td>
                                    <td class="table-actions">
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

    <!-- Update Order Status Modal -->
    <div class="modal fade" id="updateOrderStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                // Don't prevent default for "Back to Site" link
                if (this.getAttribute('href') === 'index.php') {
                    return; // Allow normal link behavior
                }
                
                e.preventDefault();
                
                // Remove active class from all links
                document.querySelectorAll('.sidebar .nav-link').forEach(l => l.classList.remove('active'));
                // Add active class to clicked link
                this.classList.add('active');
                
                // Hide all sections
                document.querySelectorAll('section').forEach(section => {
                    section.style.display = 'none';
                });
                
                // Show target section
                const targetId = this.getAttribute('href').substring(1);
                document.getElementById(targetId).style.display = 'block';
            });
        });

        // Show dashboard by default
        document.getElementById('dashboard').style.display = 'block';

        // Order status update
        function updateOrderStatus(orderId, currentStatus) {
            document.getElementById('update_order_id').value = orderId;
            document.getElementById('update_order_status').value = currentStatus;
            new bootstrap.Modal(document.getElementById('updateOrderStatusModal')).show();
        }

        // Book management functions
        function deleteBook(bookId) {
            if (confirm('Are you sure you want to delete this book?')) {
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

        // User management functions
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
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

        // Competition management functions
        function deleteCompetition(competitionId) {
            if (confirm('Are you sure you want to delete this competition?')) {
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
            
            // Show file section if file exists
            if (submission.file_path) {
                document.getElementById('view_file_section').style.display = 'block';
                document.getElementById('view_file_link').href = submission.file_path;
            } else {
                document.getElementById('view_file_section').style.display = 'none';
            }
            
            // Show winner section if winner
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
