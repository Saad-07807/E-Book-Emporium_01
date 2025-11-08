<?php
// Database configuration and session start
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ebook_system');

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, username, full_name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        // For demo purposes, using simple password verification
        // In production, use password_verify($password, $user['password'])
        if ($password === 'password' || password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
        }
    }
    $stmt->close();
    $conn->close();
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    
    $conn = getDBConnection();
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);
    
    if ($stmt->execute()) {
        $_SESSION['user_id'] = $conn->insert_id;
        $_SESSION['user_name'] = $full_name;
        $_SESSION['username'] = $username;
        $_SESSION['user_role'] = 'user';
    }
    $stmt->close();
    $conn->close();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Get data from database
$conn = getDBConnection();

// Get featured books
$featured_books = [];
$result = $conn->query("
    SELECT b.*, c.name as category_name 
    FROM books b 
    LEFT JOIN categories c ON b.category_id = c.id 
    WHERE b.featured = 1 AND b.status = 'active' 
    LIMIT 6
");
if ($result) {
    while($row = $result->fetch_assoc()) {
        $featured_books[] = $row;
    }
}

// Get all books for search
$all_books = [];
$result = $conn->query("SELECT * FROM books WHERE status = 'active'");
if ($result) {
    while($row = $result->fetch_assoc()) {
        $all_books[] = $row;
    }
}

// Get categories
$categories = [];
$result = $conn->query("SELECT * FROM categories");
if ($result) {
    while($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get competitions
$competitions = [];
$result = $conn->query("SELECT * FROM competitions WHERE status IN ('active', 'upcoming') ORDER BY start_date ASC LIMIT 3");
if ($result) {
    while($row = $result->fetch_assoc()) {
        $competitions[] = $row;
    }
}

// Get recent winners
$winners = [];
$result = $conn->query("
    SELECT w.*, u.full_name, c.title as competition_title 
    FROM winners w 
    JOIN users u ON w.user_id = u.id 
    JOIN competitions c ON w.competition_id = c.id 
    ORDER BY w.announced_date DESC 
    LIMIT 3
");
if ($result) {
    while($row = $result->fetch_assoc()) {
        $winners[] = $row;
    }
}

// Get dealers
$dealers = [];
$result = $conn->query("SELECT * FROM dealers WHERE is_active = 1");
if ($result) {
    while($row = $result->fetch_assoc()) {
        $dealers[] = $row;
    }
}

$conn->close();

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart = $_SESSION['cart'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Book Emporium | Digital Library</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Light Mode Variables */
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
            --brown-light: #f4e9dc;
            --brown-medium: #d7b899;
            --brown-dark: #8b5a2b;
        }

        /* Dark Mode Variables */
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        html, body {
            height: 100%;
            width: 100%;
            overflow-x: hidden;
        }
        
        body {
            background-color: var(--primary);
            color: var(--text);
            line-height: 1.6;
            transition: all 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header */
        .header {
            background-color: var(--header-bg);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--light);
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .header-brand-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-brand {
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            color: var(--text);
            text-decoration: none;
            white-space: nowrap;
        }
        
        .header-brand i {
            margin-right: 10px;
            color: var(--accent);
        }
        
        .header-brand span {
            color: var(--success);
        }
        
        .header-nav {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            align-items: center;
        }
        
        .nav-item {
            margin-left: 15px;
        }
        
        .nav-link {
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        .nav-link:hover {
            color: var(--accent);
            background-color: rgba(139, 90, 43, 0.1);
        }
        
        .header-toggler {
            border: none;
            background: transparent;
            color: var(--text);
            font-size: 1.25rem;
            padding: 0.25rem 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            display: none;
        }
        
        .header-collapse {
            display: flex;
            align-items: center;
            flex-grow: 1;
            justify-content: space-between;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* User Dropdown Styles */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }

        .user-menu-toggle {
            background: none;
            border: none;
            color: var(--text);
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
        }

        .user-menu-toggle:hover {
            background-color: rgba(139, 90, 43, 0.1);
        }

        .user-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--card-bg);
            border: 1px solid var(--light);
            border-radius: 4px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            min-width: 200px;
            z-index: 1000;
            display: none;
            margin-top: 5px;
        }

        .user-dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            color: var(--text);
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .dropdown-item:hover {
            background-color: var(--light);
        }

        .dropdown-divider {
            height: 1px;
            background-color: var(--light);
            margin: 5px 0;
        }
        
        .search-bar {
            display: flex;
            position: relative;
        }
        
        .search-bar input {
            padding: 8px 15px;
            border: 1px solid var(--light);
            border-radius: 4px 0 0 4px;
            outline: none;
            width: 200px;
            background-color: var(--secondary);
            color: var(--text);
            transition: width 0.3s;
        }
        
        .search-bar input:focus {
            border-color: var(--accent);
        }
        
        .search-bar button {
            background-color: var(--accent);
            color: var(--primary);
            border: none;
            padding: 0 15px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .search-bar button:hover {
            background-color: #6d4520;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--card-bg);
            border-radius: 0 0 4px 4px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            border: 1px solid var(--light);
            border-top: none;
        }
        
        .search-result-item {
            padding: 10px 15px;
            border-bottom: 1px solid var(--light);
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: background-color 0.2s;
        }
        
        .search-result-item:hover {
            background-color: var(--light);
        }
        
        .search-result-item img {
            width: 40px;
            height: 50px;
            object-fit: cover;
            margin-right: 10px;
            border-radius: 3px;
        }
        
        .search-result-info {
            flex: 1;
        }
        
        .search-result-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--text);
        }
        
        .search-result-author {
            color: var(--text-light);
            font-size: 12px;
        }
        
        .search-result-price {
            font-size: 12px;
            font-weight: 600;
            color: var(--success);
        }
        
        .cart-icon {
            position: relative;
            cursor: pointer;
            font-size: 20px;
            color: var(--text);
            padding: 8px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .cart-icon:hover {
            background-color: rgba(139, 90, 43, 0.1);
        }
        
        .cart-count {
            position: absolute;
            top: -2px;
            right: -2px;
            background-color: var(--accent);
            color: var(--primary);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
        }
        
        /* Theme Toggle */
        .theme-toggle {
            background: none;
            border: none;
            color: var(--text);
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .theme-toggle:hover {
            background-color: rgba(139, 90, 43, 0.1);
        }
        
        /* Mobile Search Toggle */
        .mobile-search-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text);
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .mobile-search-toggle:hover {
            background-color: rgba(139, 90, 43, 0.1);
        }
        
        .mobile-search-panel {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: var(--header-bg);
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            z-index: 1000;
            border-bottom: 1px solid var(--light);
        }
        
        .mobile-search-panel.active {
            display: block;
        }
        
        .mobile-search-container {
            display: flex;
            align-items: center;
            width: 100%;
        }
        
        .mobile-search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid var(--light);
            border-radius: 4px 0 0 4px;
            background-color: var(--secondary);
            color: var(--text);
            outline: none;
        }
        
        .mobile-search-input:focus {
            border-color: var(--accent);
        }
        
        .mobile-search-button {
            background-color: var(--accent);
            color: var(--primary);
            border: none;
            padding: 10px 15px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        
        .close-mobile-search {
            background: none;
            border: none;
            color: var(--text);
            font-size: 20px;
            margin-left: 10px;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .close-mobile-search:hover {
            background-color: rgba(139, 90, 43, 0.1);
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(139, 90, 43, 0.85), rgba(139, 90, 43, 0.9)), url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            text-align: center;
            width: 100%;
        }
        
        .hero h1 {
            font-size: 42px;
            margin-bottom: 20px;
        }
        
        .hero p {
            font-size: 18px;
            max-width: 700px;
            margin: 0 auto 30px;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .btn {
            display: inline-block;
            background-color: var(--accent);
            color: white;
            padding: 12px 30px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background-color: #6d4520;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 90, 43, 0.3);
        }
        
        .btn-secondary {
            background-color: var(--success);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #6d5a45;
            box-shadow: 0 4px 12px rgba(139, 115, 85, 0.3);
        }
        
        /* Stats Section */
        .stats {
            background-color: var(--secondary);
            padding: 60px 0;
            text-align: center;
            border-top: 1px solid var(--light);
            border-bottom: 1px solid var(--light);
            width: 100%;
        }
        
        .stats-container {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
        }
        
        .stat-item {
            margin: 15px;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
        }
        
        /* Featured Books */
        .featured-books {
            padding: 80px 0;
            width: 100%;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 40px;
            font-size: 32px;
            color: var(--text);
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background-color: var(--accent);
        }
        
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
        }
        
        .book-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid var(--light);
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .book-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--accent);
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            z-index: 2;
        }
        
        .book-image {
            height: 250px;
            overflow: hidden;
            position: relative;
        }
        
        .book-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .book-card:hover .book-image img {
            transform: scale(1.05);
        }
        
        .book-info {
            padding: 20px;
        }
        
        .book-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            height: 40px;
            overflow: hidden;
            color: var(--text);
        }
        
        .book-author {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .book-price {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 15px;
        }
        
        .book-actions {
            display: flex;
            justify-content: space-between;
        }
        
        .add-to-cart {
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-right: 5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .add-to-cart:hover {
            background-color: #6d4520;
        }
        
        .add-to-cart.added {
            background-color: var(--success);
        }
        
        .preview-btn {
            background-color: var(--light);
            color: var(--text);
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .preview-btn:hover {
            background-color: #c5b39d;
        }
        
        /* Browse Section */
        .browse {
            padding: 80px 0;
            background-color: var(--secondary);
            border-top: 1px solid var(--light);
            border-bottom: 1px solid var(--light);
            width: 100%;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .category-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 25px 15px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid var(--light);
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .category-card:hover {
            background-color: var(--accent);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(139, 90, 43, 0.2);
        }
        
        .category-icon {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .category-title {
            font-size: 14px;
            font-weight: 600;
        }
        
        /* Category Books Section */
        .category-books {
            padding: 80px 0;
            display: none;
            width: 100%;
        }
        
        .category-books.active {
            display: block;
        }
        
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }
        
        .back-to-categories {
            background-color: var(--light);
            color: var(--text);
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        
        .back-to-categories:hover {
            background-color: #c5b39d;
        }
        
        /* Dealers Section */
        .dealers {
            padding: 80px 0;
            width: 100%;
        }
        
        .dealers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            justify-items: center;
        }
        
        .dealer-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            border: 1px solid var(--light);
            width: 100%;
            max-width: 300px;
        }
        
        .dealer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .dealer-icon {
            font-size: 36px;
            margin-bottom: 15px;
            color: var(--accent);
        }
        
        .dealer-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text);
        }
        
        .dealer-city {
            color: var(--text-light);
            margin-bottom: 10px;
        }
        
        .dealer-email {
            color: var(--accent);
            margin-bottom: 10px;
        }
        
        .dealer-phone {
            color: var(--text-light);
            margin-bottom: 15px;
        }
        
        .dealer-address {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        /* About Section */
        .about {
            padding: 80px 0;
            background-color: var(--secondary);
            border-top: 1px solid var(--light);
            width: 100%;
        }
        
        .about-container {
            display: flex;
            gap: 50px;
            align-items: center;
        }
        
        .about-image {
            flex: 1;
        }
        
        .about-image img {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .about-content {
            flex: 1;
        }
        
        .about-content h3 {
            margin-bottom: 20px;
            color: var(--text);
        }
        
        /* Footer */
        footer {
            background-color: var(--header-bg);
            color: var(--text);
            padding: 50px 0 20px;
            border-top: 1px solid var(--light);
            margin-top: auto;
            width: 100%;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .footer-column h3 {
            font-size: 18px;
            margin-bottom: 20px;
            color: var(--accent);
        }
        
        .footer-column ul {
            list-style: none;
        }
        
        .footer-column ul li {
            margin-bottom: 10px;
        }
        
        .footer-column ul li a {
            color: var(--text-light);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-column ul li a:hover {
            color: var(--accent);
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--light);
            color: var(--text-light);
            font-size: 14px;
        }
        
        /* Cart Modal */
        .cart-modal {
            position: fixed;
            top: 0;
            right: -400px;
            width: 380px;
            height: 100vh;
            background-color: var(--card-bg);
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.3);
            transition: right 0.3s;
            z-index: 1000;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            border-left: 1px solid var(--light);
        }
        
        .cart-modal.active {
            right: 0;
        }
        
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light);
        }
        
        .close-cart {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
            transition: color 0.3s;
        }
        
        .close-cart:hover {
            color: var(--text);
        }
        
        .cart-items {
            margin-bottom: 20px;
            flex: 1;
        }
        
        .cart-item {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light);
        }
        
        .cart-item-image {
            width: 60px;
            height: 80px;
            margin-right: 15px;
        }
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 3px;
        }
        
        .cart-item-details {
            flex: 1;
        }
        
        .cart-item-title {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
            color: var(--text);
        }
        
        .cart-item-price {
            color: var(--accent);
            font-weight: 600;
            font-size: 14px;
        }
        
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .quantity-btn {
            background-color: var(--light);
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            color: var(--text);
            transition: background-color 0.3s;
        }
        
        .quantity-btn:hover {
            background-color: var(--accent);
            color: white;
        }
        
        .remove-item {
            color: var(--text-light);
            background: none;
            border: none;
            cursor: pointer;
            margin-left: auto;
            transition: color 0.3s;
        }
        
        .remove-item:hover {
            color: var(--accent);
        }
        
        .cart-total {
            font-size: 20px;
            font-weight: 700;
            text-align: right;
            margin-bottom: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--light);
            color: var(--text);
        }
        
        .checkout-btn {
            width: 100%;
            background-color: var(--success);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .checkout-btn:hover {
            background-color: #6d5a45;
        }
        
        .empty-cart {
            text-align: center;
            color: var(--text-light);
            padding: 40px 0;
        }
        
        /* Added to Cart Notification */
        .cart-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--success);
            color: white;
            padding: 15px 20px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(100px);
            opacity: 0;
            transition: transform 0.3s, opacity 0.3s;
        }
        
        .cart-notification.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .cart-notification i {
            font-size: 20px;
        }

        /* Auth Forms */
        .auth-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            display: none;
        }

        .auth-modal.active {
            display: flex;
        }

        .auth-form {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .auth-form h3 {
            margin-bottom: 20px;
            text-align: center;
            color: var(--accent);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light);
            border-radius: 4px;
            background-color: var(--secondary);
            color: var(--text);
        }

        .form-group input:focus {
            border-color: var(--accent);
            outline: none;
        }

        .auth-switch {
            text-align: center;
            margin-top: 15px;
        }

        .auth-switch a {
            color: var(--accent);
            text-decoration: none;
        }

        .auth-switch a:hover {
            text-decoration: underline;
        }
        
        /* Competition Participation Modal */
        .competition-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            display: none;
        }

        .competition-modal.active {
            display: flex;
        }

        .competition-form {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 8px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
        }

        .competition-form h3 {
            margin-bottom: 20px;
            text-align: center;
            color: var(--accent);
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .container {
                max-width: 100%;
                padding: 0 20px;
            }
        }
        
        @media (max-width: 992px) {
            .header-toggler {
                display: flex;
            }
            
            .header-collapse {
                position: fixed;
                top: 70px;
                left: 0;
                right: 0;
                background-color: var(--header-bg);
                padding: 20px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                display: none;
                z-index: 999;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-collapse.show {
                display: flex;
            }
            
            .header-nav {
                flex-direction: column;
                width: 100%;
                align-items: flex-start;
            }
            
            .nav-item {
                margin: 10px 0;
                width: 100%;
            }
            
            .nav-link {
                display: block;
                padding: 10px 15px;
                width: 100%;
            }
            
            .header-actions {
                margin-top: 15px;
                width: 100%;
                justify-content: space-between;
            }
            
            .search-bar {
                display: none;
            }
            
            .mobile-search-toggle {
                display: block;
            }
            
            .category-header {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .about-container {
                flex-direction: column;
            }
        }
        
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 32px;
            }
            
            .hero p {
                font-size: 16px;
            }
            
            .section-title {
                font-size: 28px;
            }
            
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
            
            .categories-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            
            .cart-modal {
                width: 100%;
                right: -100%;
            }
            
            .stat-number {
                font-size: 28px;
            }
        }
        
        @media (max-width: 576px) {
            .header-brand {
                font-size: 22px;
            }
            
            .hero {
                padding: 60px 0;
            }
            
            .hero h1 {
                font-size: 28px;
            }
            
            .hero p {
                font-size: 14px;
            }
            
            .stats-container {
                flex-direction: column;
            }
            
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            .categories-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .book-actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .add-to-cart, .preview-btn {
                width: 100%;
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container header-container">
            <div class="header-brand-container">
                <a class="header-brand" href="index.php">
                    <i class="fas fa-book"></i>
                    E-Book<span>Emporium</span>
                </a>
                
                <!-- Theme toggle -->
                <button class="theme-toggle" id="theme-toggle">
                    <i class="fas fa-moon"></i>
                </button>
                
                <button class="header-toggler" type="button" id="header-toggler">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <div class="header-collapse" id="header-collapse">
                <ul class="header-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#featured">Featured</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#browse">Browse</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#competitions">Competitions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#dealers">Dealers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <?php if(isLoggedIn() && isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">
                                <i class="fas fa-cog me-2"></i>Admin Panel
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <div class="header-actions">
                    <div class="search-bar">
                        <input type="text" placeholder="Search for books..." id="search-input">
                        <button id="search-button"><i class="fas fa-search"></i></button>
                        <div class="search-results" id="search-results"></div>
                    </div>
                    
                    <button class="mobile-search-toggle" id="mobile-search-toggle">
                        <i class="fas fa-search"></i>
                    </button>
                    
                    <?php if(isLoggedIn()): ?>
                        <!-- User Dropdown -->
                        <div class="user-dropdown">
                            <button class="user-menu-toggle" id="user-menu-toggle">
                                <i class="fas fa-user-circle"></i>
                                <?php echo $_SESSION['user_name']; ?>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="user-dropdown-menu" id="user-dropdown-menu">
                                <a href="dashboard.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                                <a href="profile.php" class="dropdown-item">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                                <a href="orders.php" class="dropdown-item">
                                    <i class="fas fa-shopping-bag"></i> My Orders
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="?logout=true" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                        
                        <div class="cart-icon" id="cart-icon">
                            <i class="fas fa-shopping-cart"></i> 
                            <span class="cart-count"><?php echo count($cart); ?></span>
                        </div>
                    <?php else: ?>
                        <a class="nav-link" href="#" id="login-link">Login</a>
                        <a class="nav-link" href="#" id="register-link">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Search Panel -->
    <div class="mobile-search-panel" id="mobile-search-panel">
        <div class="container">
            <div class="mobile-search-container">
                <input type="text" class="mobile-search-input" placeholder="Search for books..." id="mobile-search-input">
                <button class="mobile-search-button" id="mobile-search-button"><i class="fas fa-search"></i></button>
                <button class="close-mobile-search" id="close-mobile-search">&times;</button>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div class="auth-modal" id="login-modal">
        <div class="auth-form">
            <h3>Login to Your Account</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required>
                </div>
                <button type="submit" class="btn" name="login" style="width: 100%;">Login</button>
            </form>
            <div class="auth-switch">
                Don't have an account? <a href="#" id="switch-to-register">Register here</a>
            </div>
            <button class="close-auth" style="background: none; border: none; position: absolute; top: 10px; right: 15px; font-size: 20px; cursor: pointer;">&times;</button>
        </div>
    </div>

    <!-- Register Modal -->
    <div class="auth-modal" id="register-modal">
        <div class="auth-form">
            <h3>Create New Account</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="register-username">Username</label>
                    <input type="text" id="register-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="register-email">Email</label>
                    <input type="email" id="register-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="register-password">Password</label>
                    <input type="password" id="register-password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="register-fullname">Full Name</label>
                    <input type="text" id="register-fullname" name="full_name" required>
                </div>
                <button type="submit" class="btn" name="register" style="width: 100%;">Register</button>
            </form>
            <div class="auth-switch">
                Already have an account? <a href="#" id="switch-to-login">Login here</a>
            </div>
            <button class="close-auth" style="background: none; border: none; position: absolute; top: 10px; right: 15px; font-size: 20px; cursor: pointer;">&times;</button>
        </div>
    </div>

    <!-- Competition Participation Modal -->
    <div class="competition-modal" id="competition-modal">
        <div class="competition-form">
            <h3>Participate in Competition</h3>
            <form id="competition-form" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="participant-name">Full Name</label>
                    <input type="text" id="participant-name" name="participant_name" required>
                </div>
                <div class="form-group">
                    <label for="participant-email">Email</label>
                    <input type="email" id="participant-email" name="participant_email" required>
                </div>
                <div class="form-group">
                    <label for="submission-title">Submission Title</label>
                    <input type="text" id="submission-title" name="submission_title" required>
                </div>
                <div class="form-group">
                    <label for="submission-content">Submission Content</label>
                    <textarea id="submission-content" name="submission_content" rows="6" required></textarea>
                </div>
                <div class="form-group">
                    <label for="submission-file">Upload Document (PDF/DOC)</label>
                    <input type="file" id="submission-file" name="submission_file" accept=".pdf,.doc,.docx" required>
                </div>
                <input type="hidden" id="competition-id" name="competition_id">
                <button type="submit" class="btn" style="width: 100%;">Submit Entry</button>
            </form>
            <button class="close-competition" style="background: none; border: none; position: absolute; top: 10px; right: 15px; font-size: 20px; cursor: pointer;">&times;</button>
        </div>
    </div>

    <!-- Main Content -->
    <main>
        <!-- Hero Section -->
        <section class="hero" id="home">
            <div class="container">
                <h1>Discover Classic Literature</h1>
                <p>Explore thousands of premium eBooks from the world's great authors. Read on any device, anytime, anywhere.</p>
                <?php if(isLoggedIn()): ?>
                    <a href="#featured" class="btn">Browse Collection</a>
                <?php else: ?>
                    <a href="#" class="btn" id="hero-register">Get Started</a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats">
            <div class="container stats-container">
                <div class="stat-item">
                    <div class="stat-number">60,000+</div>
                    <div class="stat-label">Premium eBooks</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">100+</div>
                    <div class="stat-label">Languages</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">5M+</div>
                    <div class="stat-label">Downloads</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">Since 1971</div>
                    <div class="stat-label">Digital Preservation</div>
                </div>
            </div>
        </section>

        <!-- Featured Books -->
        <section class="featured-books" id="featured">
            <div class="container">
                <h2 class="section-title">Featured Books</h2>
                <div class="books-grid" id="books-grid">
                    <?php foreach($featured_books as $book): ?>
                        <div class="book-card">
                            <?php if($book['is_free']): ?>
                                <div class="book-badge">Free</div>
                            <?php endif; ?>
                            <div class="book-image">
                                <img src="<?php echo $book['cover_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                            </div>
                            <div class="book-info">
                                <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                                <p class="book-price">
                                    <?php if($book['is_free']): ?>
                                        FREE
                                    <?php else: ?>
                                        $<?php echo number_format($book['price'], 2); ?>
                                    <?php endif; ?>
                                </p>
                                <div class="book-actions">
                                    <?php if(isLoggedIn()): ?>
                                        <button class="add-to-cart" 
                                                data-id="<?php echo $book['id']; ?>" 
                                                data-title="<?php echo htmlspecialchars($book['title']); ?>" 
                                                data-price="<?php echo $book['price']; ?>" 
                                                data-image="<?php echo $book['cover_image']; ?>">
                                            <i class="fas fa-shopping-cart"></i> Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button class="add-to-cart" onclick="showLoginModal()">
                                            <i class="fas fa-shopping-cart"></i> Login to Purchase
                                        </button>
                                    <?php endif; ?>
                                    <button class="preview-btn"><i class="fas fa-eye"></i> Preview</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Browse Section -->
        <section class="browse" id="browse">
            <div class="container">
                <h2 class="section-title">Browse Categories</h2>
                <div class="categories-grid">
                    <?php foreach($categories as $category): ?>
                        <div class="category-card" data-category="<?php echo $category['id']; ?>">
                            <div class="category-icon">
                                <i class="<?php echo $category['icon']; ?>"></i>
                            </div>
                            <div class="category-title"><?php echo htmlspecialchars($category['name']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Category Books Section -->
        <section class="category-books" id="category-books">
            <div class="container">
                <div class="category-header">
                    <h2 class="section-title" id="category-title">Category Books</h2>
                    <button class="back-to-categories" id="back-to-categories">Back to Categories</button>
                </div>
                <div class="books-grid" id="category-books-grid">
                    <!-- Category books will be dynamically added here -->
                </div>
            </div>
        </section>

        <!-- Competitions Section -->
        <section class="featured-books" id="competitions">
            <div class="container">
                <h2 class="section-title">Current Competitions</h2>
                <div class="books-grid">
                    <?php foreach($competitions as $competition): ?>
                        <div class="book-card">
                            <div class="book-info">
                                <h3 class="book-title"><?php echo htmlspecialchars($competition['title']); ?></h3>
                                <p class="book-author"><?php echo ucfirst($competition['type']); ?> Competition</p>
                                <p class="book-price">
                                    <?php echo date('M d, Y', strtotime($competition['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($competition['end_date'])); ?>
                                </p>
                                <div class="book-actions">
                                    <?php if(isLoggedIn()): ?>
                                        <button class="add-to-cart participate-btn" data-competition-id="<?php echo $competition['id']; ?>">
                                            <i class="fas fa-trophy"></i> Participate
                                        </button>
                                    <?php else: ?>
                                        <button class="add-to-cart" onclick="showLoginModal()">
                                            <i class="fas fa-trophy"></i> Login to Participate
                                        </button>
                                    <?php endif; ?>
                                    <button class="preview-btn"><i class="fas fa-info-circle"></i> Details</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Recent Winners -->
        <section class="featured-books">
            <div class="container">
                <h2 class="section-title">Recent Winners</h2>
                <div class="books-grid">
                    <?php if(count($winners) > 0): ?>
                        <?php foreach($winners as $winner): ?>
                            <div class="book-card">
                                <div class="book-info">
                                    <h3 class="book-title"><?php echo htmlspecialchars($winner['full_name']); ?></h3>
                                    <p class="book-author"><?php echo htmlspecialchars($winner['competition_title']); ?></p>
                                    <p class="book-price">Winner</p>
                                    <div class="book-actions">
                                        <button class="preview-btn">
                                            <i class="fas fa-trophy"></i> View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-winners">
                            <p>No winners announced yet. Be the first to win our competitions!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Dealers Section -->
        <section class="dealers" id="dealers">
            <div class="container">
                <h2 class="section-title">Our Book Dealers</h2>
                <div class="dealers-grid">
                    <?php foreach($dealers as $dealer): ?>
                        <div class="dealer-card">
                            <div class="dealer-icon">
                                <i class="fas fa-store"></i>
                            </div>
                            <h3 class="dealer-name"><?php echo htmlspecialchars($dealer['name']); ?></h3>
                            <p class="dealer-city"><?php echo htmlspecialchars($dealer['city']); ?></p>
                            <p class="dealer-email"><?php echo htmlspecialchars($dealer['email']); ?></p>
                            <p class="dealer-phone"><?php echo htmlspecialchars($dealer['phone']); ?></p>
                            <p class="dealer-address"><?php echo htmlspecialchars($dealer['address']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section class="about" id="about">
            <div class="container">
                <h2 class="section-title">About E-Book Emporium</h2>
                <div class="about-container">
                    <div class="about-image">
                        <img src="https://images.unsplash.com/photo-1481627834876-b7833e8f5570?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Library">
                    </div>
                    <div class="about-content">
                        <h3>Preserving Literary Heritage</h3>
                        <p>E-Book Emporium is a digital library offering over 60,000 premium eBooks. Our mission is to encourage the creation and distribution of eBooks, preserving our literary heritage for future generations.</p>
                        <p>Founded in 2023, we continue the tradition of making literature accessible to everyone, regardless of their location or financial situation. All our eBooks are carefully curated and available for purchase.</p>
                        <p>We focus particularly on classic works and contemporary literature, ensuring these important texts remain available to readers worldwide.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section class="about" id="contact" style="background-color: var(--primary);">
            <div class="container">
                <h2 class="section-title">Contact Us</h2>
                <div class="about-container">
                    <div class="about-content">
                        <h3>Get In Touch</h3>
                        <p>Have questions about our eBook collection or need assistance with your account? Our support team is here to help.</p>
                        <p>You can reach us via email at support@ebookemporium.com or call us at +1 (555) 123-4567 during business hours.</p>
                        <p>We value your feedback and are always looking for ways to improve our service and expand our collection.</p>
                    </div>
                    <div class="about-image">
                        <img src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Contact Us">
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>E-Book Emporium</h3>
                    <p>Your digital library for premium eBooks. Read anywhere, anytime.</p>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#featured">Featured Books</a></li>
                        <li><a href="#browse">Browse Categories</a></li>
                        <li><a href="#about">About Us</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Categories</h3>
                    <ul>
                        <?php foreach($categories as $category): ?>
                            <li><a href="#" data-category="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Connect With Us</h3>
                    <ul>
                        <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                        <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                        <li><a href="#"><i class="fab fa-goodreads"></i> Goodreads</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2023 E-Book Emporium. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Cart Modal -->
    <div class="cart-modal" id="cart-modal">
        <div class="cart-header">
            <h3>Your Cart</h3>
            <button class="close-cart" id="close-cart">&times;</button>
        </div>
        <div class="cart-items" id="cart-items">
            <?php if(empty($cart)): ?>
                <div class="empty-cart">
                    <p>Your cart is empty</p>
                    <p>Browse our collection to add items</p>
                </div>
            <?php else: ?>
                <?php 
                $total = 0;
                foreach($cart as $item): 
                    $itemTotal = $item['price'] * $item['quantity'];
                    $total += $itemTotal;
                ?>
                    <div class="cart-item">
                        <div class="cart-item-image">
                            <img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                        </div>
                        <div class="cart-item-details">
                            <div class="cart-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div class="cart-item-price">$<?php echo number_format($item['price'], 2); ?></div>
                            <div class="cart-item-actions">
                                <button class="quantity-btn decrease" data-id="<?php echo $item['id']; ?>">-</button>
                                <span><?php echo $item['quantity']; ?></span>
                                <button class="quantity-btn increase" data-id="<?php echo $item['id']; ?>">+</button>
                                <button class="remove-item" data-id="<?php echo $item['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if(!empty($cart)): ?>
            <div class="cart-total" id="cart-total">Total: $<?php echo number_format($total, 2); ?></div>
            <button class="checkout-btn" id="checkout-btn">Proceed to Checkout</button>
        <?php endif; ?>
    </div>

    <!-- Added to Cart Notification -->
    <div class="cart-notification" id="cart-notification">
        <i class="fas fa-check-circle"></i>
        <span>Item added to cart!</span>
    </div>

    <script>
        // DOM Elements
        const headerToggler = document.getElementById('header-toggler');
        const headerCollapse = document.getElementById('header-collapse');
        const themeToggle = document.getElementById('theme-toggle');
        const searchInput = document.getElementById('search-input');
        const searchButton = document.getElementById('search-button');
        const searchResults = document.getElementById('search-results');
        const mobileSearchToggle = document.getElementById('mobile-search-toggle');
        const mobileSearchPanel = document.getElementById('mobile-search-panel');
        const mobileSearchInput = document.getElementById('mobile-search-input');
        const mobileSearchButton = document.getElementById('mobile-search-button');
        const closeMobileSearch = document.getElementById('close-mobile-search');
        const cartIcon = document.getElementById('cart-icon');
        const cartModal = document.getElementById('cart-modal');
        const closeCart = document.getElementById('close-cart');
        const cartItems = document.getElementById('cart-items');
        const cartTotal = document.getElementById('cart-total');
        const checkoutBtn = document.getElementById('checkout-btn');
        const booksGrid = document.getElementById('books-grid');
        const categoryBooksSection = document.getElementById('category-books');
        const categoryBooksGrid = document.getElementById('category-books-grid');
        const categoryTitle = document.getElementById('category-title');
        const backToCategories = document.getElementById('back-to-categories');
        const categoryCards = document.querySelectorAll('.category-card');
        const footerCategoryLinks = document.querySelectorAll('.footer-column a[data-category]');
        const cartNotification = document.getElementById('cart-notification');
        
        // Auth elements
        const loginModal = document.getElementById('login-modal');
        const registerModal = document.getElementById('register-modal');
        const loginLink = document.getElementById('login-link');
        const registerLink = document.getElementById('register-link');
        const switchToRegister = document.getElementById('switch-to-register');
        const switchToLogin = document.getElementById('switch-to-login');
        const closeAuthButtons = document.querySelectorAll('.close-auth');
        const heroRegister = document.getElementById('hero-register');
        
        // User dropdown elements
        const userMenuToggle = document.getElementById('user-menu-toggle');
        const userDropdownMenu = document.getElementById('user-dropdown-menu');
        
        // Competition elements
        const competitionModal = document.getElementById('competition-modal');
        const competitionForm = document.getElementById('competition-form');
        const participateButtons = document.querySelectorAll('.participate-btn');
        const closeCompetitionButtons = document.querySelectorAll('.close-competition');

        // Book data from PHP
        const books = <?php echo json_encode($all_books); ?>;
        const categories = <?php echo json_encode($categories); ?>;

        // Cart state from PHP session
        let cart = <?php echo json_encode($cart); ?>;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Update cart UI
            updateCartUI();

            // Toggle header collapse on mobile
            if (headerToggler) {
                headerToggler.addEventListener('click', () => {
                    headerCollapse.classList.toggle('show');
                });
            }

            // Close header when clicking outside
            document.addEventListener('click', (e) => {
                if (!headerCollapse.contains(e.target) && e.target !== headerToggler && !headerToggler.contains(e.target)) {
                    headerCollapse.classList.remove('show');
                }
                
                // Close user dropdown when clicking outside
                if (userMenuToggle && userDropdownMenu) {
                    if (!userDropdownMenu.contains(e.target) && e.target !== userMenuToggle && !userMenuToggle.contains(e.target)) {
                        userDropdownMenu.classList.remove('show');
                    }
                }
            });

            // Theme toggle functionality
            themeToggle.addEventListener('click', () => {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                if (currentTheme === 'dark') {
                    document.documentElement.removeAttribute('data-theme');
                    themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                    localStorage.setItem('theme', 'light');
                } else {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                    localStorage.setItem('theme', 'dark');
                }
            });

            // Check for saved theme preference
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            }

            // Search functionality
            if (searchButton) searchButton.addEventListener('click', performSearch);
            if (searchInput) searchInput.addEventListener('input', performSearch);
            
            // Mobile search functionality
            if (mobileSearchButton) mobileSearchButton.addEventListener('click', performMobileSearch);
            if (mobileSearchInput) mobileSearchInput.addEventListener('input', performMobileSearch);

            // Cart functionality
            if (cartIcon) {
                cartIcon.addEventListener('click', openCart);
            }
            if (closeCart) closeCart.addEventListener('click', closeCartModal);
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', checkout);
            }

            // Mobile search toggle
            if (mobileSearchToggle) {
                mobileSearchToggle.addEventListener('click', function() {
                    mobileSearchPanel.classList.add('active');
                    mobileSearchInput.focus();
                });
            }
            
            if (closeMobileSearch) {
                closeMobileSearch.addEventListener('click', function() {
                    mobileSearchPanel.classList.remove('active');
                });
            }
            
            // Close mobile search when clicking outside
            document.addEventListener('click', function(e) {
                if (!mobileSearchPanel.contains(e.target) && e.target !== mobileSearchToggle && !mobileSearchToggle.contains(e.target)) {
                    mobileSearchPanel.classList.remove('active');
                }
            });

            // Close cart when clicking outside
            document.addEventListener('click', function(e) {
                if (!cartModal.contains(e.target) && e.target !== cartIcon && !cartIcon.contains(e.target)) {
                    closeCartModal();
                }
            });

            // Category card functionality
            categoryCards.forEach(card => {
                card.addEventListener('click', function() {
                    const categoryId = this.getAttribute('data-category');
                    showCategoryBooks(categoryId);
                });
            });

            // Footer category links
            footerCategoryLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const categoryId = this.getAttribute('data-category');
                    showCategoryBooks(categoryId);
                });
            });

            // Back to categories button
            backToCategories.addEventListener('click', function() {
                hideCategoryBooks();
            });

            // Auth functionality
            if (loginLink) loginLink.addEventListener('click', showLoginModal);
            if (registerLink) registerLink.addEventListener('click', showRegisterModal);
            if (switchToRegister) switchToRegister.addEventListener('click', switchToRegisterModal);
            if (switchToLogin) switchToLogin.addEventListener('click', switchToLoginModal);
            if (heroRegister) heroRegister.addEventListener('click', showRegisterModal);
            
            closeAuthButtons.forEach(button => {
                button.addEventListener('click', closeAuthModals);
            });
            
            // User dropdown functionality
            if (userMenuToggle) {
                userMenuToggle.addEventListener('click', function() {
                    userDropdownMenu.classList.toggle('show');
                });
            }

            // Add to cart buttons
            document.querySelectorAll('.add-to-cart[data-id]').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const title = this.getAttribute('data-title');
                    const price = parseFloat(this.getAttribute('data-price'));
                    const image = this.getAttribute('data-image');
                    
                    addToCart(id, title, price, image);
                });
            });

            // Cart item actions
            document.querySelectorAll('.quantity-btn.increase').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    increaseQuantity(id);
                });
            });

            document.querySelectorAll('.quantity-btn.decrease').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    decreaseQuantity(id);
                });
            });

            document.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    removeFromCart(id);
                });
            });

            // Competition participation buttons
            participateButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const competitionId = this.getAttribute('data-competition-id');
                    showCompetitionModal(competitionId);
                });
            });
            
            // Close competition modal
            closeCompetitionButtons.forEach(button => {
                button.addEventListener('click', closeCompetitionModal);
            });
            
            // Competition form submission
            if (competitionForm) {
                competitionForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitCompetitionEntry();
                });
            }

            // Smooth scrolling for navigation links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        targetElement.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });

        // Auth modal functions
        function showLoginModal() {
            loginModal.classList.add('active');
        }

        function showRegisterModal() {
            registerModal.classList.add('active');
        }

        function switchToRegisterModal(e) {
            e.preventDefault();
            loginModal.classList.remove('active');
            registerModal.classList.add('active');
        }

        function switchToLoginModal(e) {
            e.preventDefault();
            registerModal.classList.remove('active');
            loginModal.classList.add('active');
        }

        function closeAuthModals() {
            loginModal.classList.remove('active');
            registerModal.classList.remove('active');
        }
        
        // Competition modal functions
        function showCompetitionModal(competitionId) {
            document.getElementById('competition-id').value = competitionId;
            competitionModal.classList.add('active');
        }
        
        function closeCompetitionModal() {
            competitionModal.classList.remove('active');
        }
        
        function submitCompetitionEntry() {
            const formData = new FormData(competitionForm);
            
            // In a real application, you would send this to the server
            fetch('submit_competition.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Your competition entry has been submitted successfully!');
                    closeCompetitionModal();
                    competitionForm.reset();
                } else {
                    alert('Error submitting your entry. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error submitting your entry. Please try again.');
            });
        }

        // Show category books
        function showCategoryBooks(categoryId) {
            // Hide featured section
            document.querySelector('.featured-books').style.display = 'none';
            
            // Show category books section
            categoryBooksSection.classList.add('active');
            
            // Set category title
            const category = categories.find(c => c.id == categoryId);
            const categoryName = category ? category.name : 'Category';
            categoryTitle.textContent = `${categoryName} Books`;
            
            // Populate category books
            populateCategoryBooks(categoryId);
            
            // Scroll to category section
            categoryBooksSection.scrollIntoView({ behavior: 'smooth' });
        }

        // Hide category books
        function hideCategoryBooks() {
            // Show featured section
            document.querySelector('.featured-books').style.display = 'block';
            
            // Hide category books section
            categoryBooksSection.classList.remove('active');
            
            // Scroll to browse section
            document.getElementById('browse').scrollIntoView({ behavior: 'smooth' });
        }

        // Populate category books
        function populateCategoryBooks(categoryId) {
            categoryBooksGrid.innerHTML = '';
            const categoryBooks = books.filter(book => book.category_id == categoryId);
            
            if (categoryBooks.length === 0) {
                categoryBooksGrid.innerHTML = '<p>No books available in this category yet.</p>';
                return;
            }
            
            categoryBooks.forEach(book => {
                const bookCard = document.createElement('div');
                bookCard.className = 'book-card';
                bookCard.innerHTML = `
                    ${book.is_free ? '<div class="book-badge">Free</div>' : ''}
                    <div class="book-image">
                        <img src="${book.cover_image}" alt="${book.title}">
                    </div>
                    <div class="book-info">
                        <h3 class="book-title">${book.title}</h3>
                        <p class="book-author">${book.author}</p>
                        <p class="book-price">${book.is_free ? 'FREE' : '$' + parseFloat(book.price).toFixed(2)}</p>
                        <div class="book-actions">
                            <button class="add-to-cart" data-id="${book.id}" data-title="${book.title}" data-price="${book.price}" data-image="${book.cover_image}">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                            <button class="preview-btn"><i class="fas fa-eye"></i> Preview</button>
                        </div>
                    </div>
                `;
                
                // Add event listener to add to cart button
                const addToCartBtn = bookCard.querySelector('.add-to-cart');
                addToCartBtn.addEventListener('click', function() {
                    <?php if(isLoggedIn()): ?>
                        const id = this.getAttribute('data-id');
                        const title = this.getAttribute('data-title');
                        const price = parseFloat(this.getAttribute('data-price'));
                        const image = this.getAttribute('data-image');
                        
                        addToCart(id, title, price, image);
                    <?php else: ?>
                        showLoginModal();
                    <?php endif; ?>
                });
                
                categoryBooksGrid.appendChild(bookCard);
            });
        }

        // Search function
        function performSearch() {
            const query = searchInput.value.toLowerCase().trim();
            
            if (query === '') {
                searchResults.style.display = 'none';
                return;
            }
            
            const filteredBooks = books.filter(book => 
                book.title.toLowerCase().includes(query) || 
                book.author.toLowerCase().includes(query)
            );
            
            displaySearchResults(filteredBooks);
        }
        
        // Mobile search function
        function performMobileSearch() {
            const query = mobileSearchInput.value.toLowerCase().trim();
            
            if (query === '') {
                return;
            }
            
            const filteredBooks = books.filter(book => 
                book.title.toLowerCase().includes(query) || 
                book.author.toLowerCase().includes(query)
            );
            
            displaySearchResults(filteredBooks);
            mobileSearchPanel.classList.remove('active');
        }

        // Display search results
        function displaySearchResults(results) {
            searchResults.innerHTML = '';
            
            if (results.length === 0) {
                searchResults.innerHTML = '<div class="search-result-item">No books found</div>';
                searchResults.style.display = 'block';
                return;
            }
            
            results.forEach(book => {
                const resultItem = document.createElement('div');
                resultItem.className = 'search-result-item';
                resultItem.innerHTML = `
                    <img src="${book.cover_image}" alt="${book.title}">
                    <div class="search-result-info">
                        <div class="search-result-title">${book.title}</div>
                        <div class="search-result-author">${book.author}</div>
                    </div>
                    <div class="search-result-price">${book.is_free ? 'FREE' : '$' + parseFloat(book.price).toFixed(2)}</div>
                `;
                
                // Navigate to category instead of adding to cart
                resultItem.addEventListener('click', function() {
                    showCategoryBooks(book.category_id);
                    searchResults.style.display = 'none';
                    searchInput.value = '';
                    mobileSearchInput.value = '';
                });
                
                searchResults.appendChild(resultItem);
            });
            
            searchResults.style.display = 'block';
        }

        // Add to cart function
        function addToCart(id, title, price, image) {
            const existingItem = cart.find(item => item.id === id);
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({
                    id: id,
                    title: title,
                    price: price,
                    image: image,
                    quantity: 1
                });
            }
            
            updateCartUI();
            saveCartToServer();
            
            // Show notification
            showCartNotification();
            
            // Show confirmation on button
            const button = document.querySelector(`.add-to-cart[data-id="${id}"]`);
            if (button) {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Added';
                button.classList.add('added');
                
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.classList.remove('added');
                }, 1500);
            }
        }

        // Show cart notification
        function showCartNotification() {
            cartNotification.classList.add('show');
            
            setTimeout(() => {
                cartNotification.classList.remove('show');
            }, 3000);
        }

        // Update cart UI
        function updateCartUI() {
            // Update cart count
            const cartCount = document.querySelector('.cart-count');
            if (cartCount) {
                const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
                cartCount.textContent = totalItems;
            }
            
            // Update cart items
            cartItems.innerHTML = '';
            
            if (cart.length === 0) {
                cartItems.innerHTML = '<div class="empty-cart"><p>Your cart is empty</p><p>Browse our collection to add items</p></div>';
                if (cartTotal) cartTotal.style.display = 'none';
                if (checkoutBtn) checkoutBtn.style.display = 'none';
                return;
            }
            
            let total = 0;
            
            cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                total += itemTotal;
                
                const cartItem = document.createElement('div');
                cartItem.className = 'cart-item';
                cartItem.innerHTML = `
                    <div class="cart-item-image">
                        <img src="${item.image}" alt="${item.title}">
                    </div>
                    <div class="cart-item-details">
                        <div class="cart-item-title">${item.title}</div>
                        <div class="cart-item-price">$${parseFloat(item.price).toFixed(2)}</div>
                        <div class="cart-item-actions">
                            <button class="quantity-btn decrease" data-id="${item.id}">-</button>
                            <span>${item.quantity}</span>
                            <button class="quantity-btn increase" data-id="${item.id}">+</button>
                            <button class="remove-item" data-id="${item.id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                cartItems.appendChild(cartItem);
            });
            
            // Add event listeners to quantity buttons
            document.querySelectorAll('.decrease').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    decreaseQuantity(id);
                });
            });
            
            document.querySelectorAll('.increase').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    increaseQuantity(id);
                });
            });
            
            document.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    removeFromCart(id);
                });
            });
            
            if (cartTotal) {
                cartTotal.textContent = `Total: $${total.toFixed(2)}`;
                cartTotal.style.display = 'block';
            }
            if (checkoutBtn) {
                checkoutBtn.style.display = 'block';
            }
        }

        // Cart quantity functions
        function increaseQuantity(id) {
            const item = cart.find(item => item.id === id);
            if (item) {
                item.quantity += 1;
                updateCartUI();
                saveCartToServer();
            }
        }

        function decreaseQuantity(id) {
            const item = cart.find(item => item.id === id);
            if (item) {
                if (item.quantity > 1) {
                    item.quantity -= 1;
                } else {
                    removeFromCart(id);
                    return;
                }
                updateCartUI();
                saveCartToServer();
            }
        }

        function removeFromCart(id) {
            cart = cart.filter(item => item.id !== id);
            updateCartUI();
            saveCartToServer();
        }

        // Save cart to server
        function saveCartToServer() {
            // In a real application, you would send this to the server via AJAX
            // For now, we'll just update the session via page reload
            // This is a simplified version - in production, use AJAX
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'update_cart.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'cart_data';
            input.value = JSON.stringify(cart);
            form.appendChild(input);
            
            document.body.appendChild(form);
            // form.submit(); // Uncomment to actually submit in production
        }

        // Cart modal functions
        function openCart() {
            cartModal.classList.add('active');
        }

        function closeCartModal() {
            cartModal.classList.remove('active');
        }

        // Checkout function
        function checkout() {
            if (cart.length === 0) {
                alert('Your cart is empty!');
                return;
            }
            
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            alert(`Proceeding to checkout. Total: $${total.toFixed(2)}`);
            
            // In a real application, you would redirect to a checkout page
            // window.location.href = 'checkout.php';
            
            cart = [];
            updateCartUI();
            saveCartToServer();
            closeCartModal();
        }

        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchResults.contains(e.target) && e.target !== searchInput && e.target !== searchButton) {
                searchResults.style.display = 'none';
            }
        });
    </script>
</body>
</html>