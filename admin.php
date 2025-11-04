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
    
    if (isset($_POST['add_book'])) {
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $category_id = intval($_POST['category_id']);
        $is_free = isset($_POST['is_free']) ? 1 : 0;
        $featured = isset($_POST['featured']) ? 1 : 0;
        $stock_quantity = intval($_POST['stock_quantity']);
        $pages = intval($_POST['pages']);
        $publication_year = intval($_POST['publication_year']);
        $isbn = trim($_POST['isbn']);
        
        $stmt = $conn->prepare("INSERT INTO books (title, author, description, price, category_id, is_free, featured, stock_quantity, pages, publication_year, isbn) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdiisiiis", $title, $author, $description, $price, $category_id, $is_free, $featured, $stock_quantity, $pages, $publication_year, $isbn);
        $stmt->execute();
        $stmt->close();
    }
    
    if (isset($_POST['update_book'])) {
        $book_id = intval($_POST['book_id']);
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $category_id = intval($_POST['category_id']);
        $is_free = isset($_POST['is_free']) ? 1 : 0;
        $featured = isset($_POST['featured']) ? 1 : 0;
        $stock_quantity = intval($_POST['stock_quantity']);
        $pages = intval($_POST['pages']);
        $publication_year = intval($_POST['publication_year']);
        $isbn = trim($_POST['isbn']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE books SET title=?, author=?, description=?, price=?, category_id=?, is_free=?, featured=?, stock_quantity=?, pages=?, publication_year=?, isbn=?, status=? WHERE id=?");
        $stmt->bind_param("sssdiisiiissi", $title, $author, $description, $price, $category_id, $is_free, $featured, $stock_quantity, $pages, $publication_year, $isbn, $status, $book_id);
        $stmt->execute();
        $stmt->close();
    }
    
    if (isset($_POST['add_competition'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $type = $_POST['type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $rules = trim($_POST['rules']);
        $prizes = trim($_POST['prizes']);
        
        $stmt = $conn->prepare("INSERT INTO competitions (title, description, type, start_date, end_date, rules, prizes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $title, $description, $type, $start_date, $end_date, $rules, $prizes);
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
            <a class="nav-link" href="#categories">
                <i class="fas fa-tags me-2"></i>Categories
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
            <a class="nav-link" href="#reports">
                <i class="fas fa-chart-bar me-2"></i>Reports
            </a>
            <a class="nav-link" href="index.php">
                <i class="fas fa-home me-2"></i>Back to Site
            </a>
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
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
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
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
                                        <button class="btn btn-sm btn-outline-primary" onclick="editBook(<?php echo htmlspecialchars(json_encode($book)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
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

        <!-- Categories Section -->
        <section id="categories" style="display: none;">
            <h2 class="mb-4">Categories Management</h2>
            <div class="row">
                <?php foreach($categories as $category): ?>
                <div class="col-md-3 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="<?php echo $category['icon']; ?> fa-2x mb-3 text-primary"></i>
                            <h5><?php echo htmlspecialchars($category['name']); ?></h5>
                            <p class="text-muted"><?php echo htmlspecialchars($category['description']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
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
                                        <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
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
                                        <button class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger">
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
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompetitionModal">
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
                                        <button class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger">
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

        <!-- Reports Section -->
        <section id="reports" style="display: none;">
            <h2 class="mb-4">Reports & Analytics</h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Sales Overview</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Top Categories</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="categoriesChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Add Book Modal -->
    <div class="modal fade" id="addBookModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" class="form-control" name="title" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Author</label>
                                    <input type="text" class="form-control" name="author" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Price ($)</label>
                                    <input type="number" class="form-control" name="price" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-control" name="category_id" required>
                                        <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Stock Quantity</label>
                                    <input type="number" class="form-control" name="stock_quantity" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Pages</label>
                                    <input type="number" class="form-control" name="pages">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Publication Year</label>
                                    <input type="number" class="form-control" name="publication_year" min="1900" max="2030">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">ISBN</label>
                                    <input type="text" class="form-control" name="isbn">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_free" id="is_free">
                                    <label class="form-check-label" for="is_free">Free Book</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="featured" id="featured">
                                    <label class="form-check-label" for="featured">Featured Book</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_book">Add Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Competition Modal -->
    <div class="modal fade" id="addCompetitionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Competition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Type</label>
                                    <select class="form-control" name="type" required>
                                        <option value="essay">Essay Writing</option>
                                        <option value="story">Story Writing</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rules</label>
                            <textarea class="form-control" name="rules" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Prizes</label>
                            <textarea class="form-control" name="prizes" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_competition">Add Competition</button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Navigation
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
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
        function editBook(book) {
            // Implementation for editing book
            alert('Edit book: ' + book.title);
            // You would populate a modal with book data for editing
        }

        function deleteBook(bookId) {
            if (confirm('Are you sure you want to delete this book?')) {
                // Implementation for deleting book
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
                        alert('Error deleting book');
                    }
                });
            }
        }

        // Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Sales Chart
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Sales ($)',
                        data: [1200, 1900, 1500, 2200, 1800, 2500],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });

            // Categories Chart
            const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
            const categoriesChart = new Chart(categoriesCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Fiction', 'Science', 'History', 'Romance', 'Mystery', 'Sci-Fi'],
                    datasets: [{
                        data: [30, 15, 20, 10, 15, 10],
                        backgroundColor: [
                            '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#20c997'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>