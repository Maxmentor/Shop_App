<?php
session_start();
// Database Configuration
$host = 'localhost';
$dbname = 'stock_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $pdo->exec("USE $dbname");

    // Auto-create/Fix tables with all necessary columns
    $pdo->exec("CREATE TABLE IF NOT EXISTS shop_details (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), mobile VARCHAR(50), website VARCHAR(255), gst VARCHAR(50), address TEXT)");
    $pdo->exec("INSERT INTO shop_details (id, name, mobile, website, gst, address) VALUES (1, 'My Retail Shop', '9876543210', 'www.myshop.com', '29ABCDE1234F1Z5', 'Main Market, City') ON DUPLICATE KEY UPDATE id=id;");
    
    // Admin Table for dynamic Username & Password
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(255) UNIQUE, password VARCHAR(255))");
    $pdo->exec("INSERT IGNORE INTO admin (id, username, password) VALUES (1, 'admin', '1234')");

    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (id INT AUTO_INCREMENT PRIMARY KEY, sup_code VARCHAR(50) UNIQUE, name VARCHAR(255), mobile VARCHAR(50), gst VARCHAR(50), address TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) UNIQUE)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory (id INT AUTO_INCREMENT PRIMARY KEY, product_code VARCHAR(50) UNIQUE, product_name VARCHAR(255), category_id INT, supplier_code VARCHAR(50), quantity INT, unit VARCHAR(20), pieces_in_box INT DEFAULT 1, size VARCHAR(50), image VARCHAR(255), purchase_price DECIMAL(10,2), sale_price DECIMAL(10,2), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    
    // Permanent Purchase History table for fixed "TOTAL PURCHASE INVESTMENT"
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_code VARCHAR(50),
        total_purchase_amount DECIMAL(10,2),
        purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(50),
        customer_name VARCHAR(255),
        customer_mobile VARCHAR(50),
        customer_address TEXT,
        product_id INT,
        quantity_sold INT,
        unit_sold VARCHAR(20),
        total_amount DECIMAL(10,2),
        gst_amount DECIMAL(10,2) DEFAULT 0,
        gst_percent DECIMAL(5,2) DEFAULT 0,
        sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Table for All Bills with Multiple Images support
    $pdo->exec("CREATE TABLE IF NOT EXISTS all_bills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_name VARCHAR(255),
        gst_no VARCHAR(50),
        mobile VARCHAR(50),
        images TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Handle Login using database credentials
if (isset($_POST['login'])) {
    $input_user = $_POST['username'];
    $input_pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$input_user]);
    $admin_data = $stmt->fetch();

    if ($admin_data && $admin_data['password'] == $input_pass) {
        $_SESSION['admin'] = true;
        header("Location: index.php");
        exit();
    } else {
        $login_error = "Invalid Username or Password!";
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Actions
if (isset($_SESSION['admin'])) {
    if (isset($_POST['update_admin'])) {
        $new_user = trim($_POST['new_username']);
        $new_pass = trim($_POST['new_password']);
        if(!empty($new_user) && !empty($new_pass)) {
            $stmt = $pdo->prepare("UPDATE admin SET username=?, password=? WHERE id=1");
            $stmt->execute([$new_user, $new_pass]);
            $admin_msg = "Admin credentials updated successfully!";
        } else {
            $admin_error = "Username and Password cannot be empty!";
        }
    }
    if (isset($_POST['update_shop'])) {
        $stmt = $pdo->prepare("UPDATE shop_details SET name=?, mobile=?, website=?, gst=?, address=? WHERE id=1");
        $stmt->execute([$_POST['name'], $_POST['mobile'], $_POST['website'], $_POST['gst'], $_POST['address']]);
        header("Location: index.php?page=shop&msg=Updated Successfully");
        exit();
    }
    if (isset($_POST['add_supplier'])) {
        $sup_code = 'SUP' . rand(10000, 99999);
        $stmt = $pdo->prepare("INSERT INTO suppliers (sup_code, name, mobile, gst, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sup_code, $_POST['name'], $_POST['mobile'], $_POST['gst'], $_POST['address']]);
        header("Location: index.php?page=suppliers");
        exit();
    }
    if (isset($_GET['delete_sup'])) {
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id=?");
        $stmt->execute([$_GET['delete_sup']]);
        header("Location: index.php?page=suppliers");
        exit();
    }
    if (isset($_POST['add_category'])) {
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$_POST['name']]);
        header("Location: index.php?page=categories");
        exit();
    }
    if (isset($_GET['delete_cat'])) {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id=?");
        $stmt->execute([$_GET['delete_cat']]);
        header("Location: index.php?page=categories");
        exit();
    }
    if (isset($_POST['add_inventory'])) {
        $p_code = 'PRD' . rand(1000, 9999);
        $image = '';
        if (!empty($_FILES['image']['name'])) {
            $image = 'uploads/' . time() . '_' . $_FILES['image']['name'];
            @mkdir('uploads', 0777, true);
            move_uploaded_file($_FILES['image']['tmp_name'], $image);
        }
        
        $qty = $_POST['quantity'];
        $pur_price = $_POST['purchase_price'];
        $total_item_pur_cost = $qty * $pur_price;

        $stmt = $pdo->prepare("INSERT INTO inventory (product_code, product_name, category_id, supplier_code, quantity, unit, pieces_in_box, size, image, purchase_price, sale_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $p_code, $_POST['product_name'], $_POST['category_id'], $_POST['supplier_code'], 
            $qty, $_POST['unit'], $_POST['pieces_in_box'] ?? 1, 
            $_POST['size'], $image, $pur_price, $_POST['sale_price']
        ]);

        // Log permanent purchase investment
        $ph_stmt = $pdo->prepare("INSERT INTO purchase_history (product_code, total_purchase_amount) VALUES (?, ?)");
        $ph_stmt->execute([$p_code, $total_item_pur_cost]);

        header("Location: index.php?page=inventory");
        exit();
    }
    if (isset($_POST['edit_inventory'])) {
        $id = $_POST['id'];
        $image = $_POST['old_image'];
        if (!empty($_FILES['image']['name'])) {
            $image = 'uploads/' . time() . '_' . $_FILES['image']['name'];
            @mkdir('uploads', 0777, true);
            move_uploaded_file($_FILES['image']['tmp_name'], $image);
        }
        $stmt = $pdo->prepare("UPDATE inventory SET product_name=?, category_id=?, supplier_code=?, quantity=?, unit=?, pieces_in_box=?, size=?, image=?, purchase_price=?, sale_price=? WHERE id=?");
        $stmt->execute([
            $_POST['product_name'], $_POST['category_id'], $_POST['supplier_code'], 
            $_POST['quantity'], $_POST['unit'], $_POST['pieces_in_box'] ?? 1, 
            $_POST['size'], $image, $_POST['purchase_price'], $_POST['sale_price'], $id
        ]);
        header("Location: index.php?page=inventory");
        exit();
    }
    if (isset($_GET['delete_inv'])) {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id=?");
        $stmt->execute([$_GET['delete_inv']]);
        header("Location: index.php?page=inventory");
        exit();
    }
    // Handle All Bills Upload with Multiple Images
    if (isset($_POST['add_external_bill'])) {
        $bill_name = $_POST['bill_name'];
        $gst_no = $_POST['gst_no'];
        $mobile = $_POST['mobile'];
        $uploaded_images = [];

        if (!empty($_FILES['bill_images']['name'][0])) {
            @mkdir('uploads/bills', 0777, true);
            foreach ($_FILES['bill_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['bill_images']['error'][$key] == UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . rand(1000, 9999) . '_' . basename($_FILES['bill_images']['name'][$key]);
                    $target_path = 'uploads/bills/' . $file_name;
                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $uploaded_images[] = $target_path;
                    }
                }
            }
        }
        $images_string = implode(',', $uploaded_images);
        $stmt = $pdo->prepare("INSERT INTO all_bills (bill_name, gst_no, mobile, images) VALUES (?, ?, ?, ?)");
        $stmt->execute([$bill_name, $gst_no, $mobile, $images_string]);
        header("Location: index.php?page=all_bills_list");
        exit();
    }
    if (isset($_GET['delete_bill'])) {
        $stmt = $pdo->prepare("DELETE FROM all_bills WHERE id=?");
        $stmt->execute([$_GET['delete_bill']]);
        header("Location: index.php?page=all_bills_list");
        exit();
    }
    if (isset($_POST['process_sale'])) {
        $prod_id = $_POST['product_id'];
        $qty_sold = $_POST['quantity_sold'];
        
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id=?");
        $stmt->execute([$prod_id]);
        $prod = $stmt->fetch();
        
        if ($prod && $prod['quantity'] >= $qty_sold) {
            $new_qty = $prod['quantity'] - $qty_sold;
            $upd = $pdo->prepare("UPDATE inventory SET quantity=? WHERE id=?");
            $upd->execute([$new_qty, $prod_id]);
            
            $sub_total = $prod['sale_price'] * $qty_sold;
            $gst_percent = isset($_POST['apply_gst']) ? floatval($_POST['gst_percent']) : 0;
            $gst_amt = ($sub_total * $gst_percent) / 100;
            $total_amt = $sub_total + $gst_amt;
            
            $invoice_no = 'INV-' . date('Ymd') . '-' . rand(100, 999);
            
            $cust_name = ($_POST['cust_type'] == 'Custom') ? $_POST['cust_name'] : 'Regular Customer';
            $cust_mob = ($_POST['cust_type'] == 'Custom') ? $_POST['customer_mobile'] : 'N/A';
            $cust_addr = ($_POST['cust_type'] == 'Custom') ? $_POST['address'] : 'N/A';
            
            $ins = $pdo->prepare("INSERT INTO sales (invoice_no, customer_name, customer_mobile, customer_address, product_id, quantity_sold, unit_sold, total_amount, gst_amount, gst_percent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$invoice_no, $cust_name, $cust_mob, $cust_addr, $prod_id, $qty_sold, $prod['unit'], $total_amt, $gst_amt, $gst_percent]);
            
            header("Location: index.php?page=sales&invoice=" . $invoice_no);
            exit();
        } else {
            $sale_error = "Product is Out of Stock or Insufficient Quantity!";
        }
    }
    if (isset($_GET['delete_sale'])) {
        $stmt = $pdo->prepare("DELETE FROM sales WHERE id=?");
        $stmt->execute([$_GET['delete_sale']]);
        header("Location: index.php?page=all_sales");
        exit();
    }
}
$page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Stock & Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        :root { --sidebar-bg: #0f172a; --primary-color: #2563eb; }
        body { background: #f8fafc; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; color: #334155; display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden; }
        .sidebar { background: var(--sidebar-bg); min-height: 100vh; color: #fff; box-shadow: 4px 0 10px rgba(0,0,0,0.05); transition: all 0.3s ease; }
        .sidebar a { color: #94a3b8; text-decoration: none; padding: 10px 16px; display: block; border-radius: 8px; margin: 4px 8px; font-weight: 500; font-size: 0.95rem; transition: all 0.2s; }
        .sidebar a:hover, .sidebar a.active { background: var(--primary-color); color: #fff; transform: translateX(4px); }
        .card-box { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #fff; transition: transform 0.2s; }
        .table th { background: #f1f5f9; font-weight: 600; color: #475569; font-size: 0.9rem; }
        .table td { font-size: 0.9rem; vertical-align: middle; }
        .product-img { cursor: pointer; border-radius: 6px; object-fit: cover; transition: opacity 0.2s; }
        .product-img:hover { opacity: 0.8; }
        .main-footer { background: #ffffff; border-top: 1px solid #e2e8f0; font-size: 13px; margin-top: auto; }
        
        @media (max-width: 991.98px) {
            .sidebar { min-height: auto; width: 100%; position: relative; }
            .mobile-nav-toggle { display: block !important; }
            .sidebar-menu-wrap { display: none; }
            .sidebar-menu-wrap.show { display: block; padding-bottom: 15px; }
            h2 { font-size: 1.5rem; }
            .table-responsive { overflow-x: auto; }
        }
        @media (min-width: 992px) {
            .mobile-nav-toggle { display: none !important; }
            .sidebar-menu-wrap { display: block !important; }
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; color: #000; }
            .card-box { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['admin'])): ?>
    <div class="container d-flex justify-content-center align-items-center min-vh-100 flex-grow-1 px-3">
        <div class="card card-box p-4 p-md-5 w-100" style="max-width: 420px;">
            <div class="text-center mb-4">
                <div class="bg-primary text-white rounded-circle d-inline-flex p-3 mb-2 shadow-sm">
                    <i class="fas fa-cubes fa-2x"></i>
                </div>
                <h3 class="fw-bold">Stock Manager</h3>
                <p class="text-muted small">Sign in to access your dashboard</p>
            </div>
            <?php if(isset($login_error)) echo "<div class='alert alert-danger py-2 small'>$login_error</div>"; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="admin" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="1234" required>
                    </div>
                </div>
                <button type="submit" name="login" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">Login Securely</button>
            </form>
        </div>
    </div>
    <footer class="text-center py-3 text-muted no-print main-footer">
        Developed By <a href="https://maxtech.great-site.net" target="_blank" class="text-decoration-none fw-bold text-primary">MaxMentor</a> | 
        <a href="https://github.com/maxmentor/" target="_blank" class="text-decoration-none text-dark"><i class="fab fa-github"></i> GitHub</a>
    </footer>
<?php else: ?>

    <div class="container-fluid flex-grow-1 p-0">
        <div class="row g-0">
            <div class="col-lg-2 px-0 sidebar no-print">
                <div class="p-3 d-flex justify-content-between align-items-center border-bottom border-secondary border-opacity-25">
                    <h5 class="fw-bold text-white m-0 fs-6"><i class="fas fa-box-archive text-primary me-2"></i>STOCK APP</h5>
                    <button class="btn btn-dark btn-sm mobile-nav-toggle text-white border-0" onclick="toggleMobileMenu()"><i class="fas fa-bars fa-lg"></i></button>
                </div>
                <nav class="mt-2 sidebar-menu-wrap">
                    <a href="index.php?page=dashboard" class="<?php if($page=='dashboard') echo 'active'; ?>"><i class="fas fa-chart-line me-2"></i> Dashboard</a>
                    <a href="index.php?page=shop" class="<?php if($page=='shop') echo 'active'; ?>"><i class="fas fa-store me-2"></i> Shop Details</a>
                    <a href="index.php?page=admin_manager" class="<?php if($page=='admin_manager') echo 'active'; ?>"><i class="fas fa-user-shield me-2"></i> Admin Manager</a>
                    <a href="index.php?page=suppliers" class="<?php if($page=='suppliers') echo 'active'; ?>"><i class="fas fa-truck-field me-2"></i> Suppliers</a>
                    <a href="index.php?page=categories" class="<?php if($page=='categories') echo 'active'; ?>"><i class="fas fa-tags me-2"></i> Categories</a>
                    <a href="index.php?page=inventory" class="<?php if($page=='inventory') echo 'active'; ?>"><i class="fas fa-boxes-stacked me-2"></i> Inventory</a>
                    <a href="index.php?page=all_bills_list" class="<?php if($page=='all_bills_list' || $page=='add_all_bill') echo 'active'; ?>"><i class="fas fa-folder-open me-2"></i> All Bills</a>
                    <a href="index.php?page=sales" class="<?php if($page=='sales') echo 'active'; ?>"><i class="fas fa-cash-register me-2"></i> New Sale / Billing</a>
                    <a href="index.php?page=all_sales" class="<?php if($page=='all_sales') echo 'active'; ?>"><i class="fas fa-file-invoice-dollar me-2"></i> All Sales Report</a>
                    <a href="index.php?page=out_of_stock" class="<?php if($page=='out_of_stock') echo 'active'; ?>"><i class="fas fa-triangle-exclamation me-2"></i> Out of Stock</a>
                    <a href="index.php?logout=true" class="text-danger mt-3"><i class="fas fa-right-from-bracket me-2"></i> Logout</a>
                </nav>
            </div>

            <script>
            function toggleMobileMenu() {
                var menu = document.querySelector('.sidebar-menu-wrap');
                menu.classList.toggle('show');
            }
            </script>

            <div class="col-lg-10 px-3 px-md-4 py-4 d-flex flex-column justify-content-between" style="min-height: 100vh;">
                <div>
                <?php 
                if ($page == 'dashboard'):
                    $filter_type = $_GET['filter_type'] ?? 'all';
                    $filter_val = $_GET['filter_val'] ?? '';

                    $date_cond = "";
                    if($filter_type == 'day' && !empty($filter_val)) {
                        $date_cond = " AND DATE(s.sale_date) = '$filter_val'";
                    } elseif($filter_type == 'month' && !empty($filter_val)) {
                        $date_cond = " AND DATE_FORMAT(s.sale_date, '%Y-%m') = '$filter_val'";
                    } elseif($filter_type == 'year' && !empty($filter_val)) {
                        $date_cond = " AND YEAR(s.sale_date) = '$filter_val'";
                    }

                    $total_purchase_investment = $pdo->query("SELECT SUM(total_purchase_amount) as total_pi FROM purchase_history")->fetch()['total_pi'] ?? 0;
                    $inv_summary = $pdo->query("SELECT SUM(quantity * purchase_price) as total_inv FROM inventory")->fetch();
                    
                    $sale_query = "SELECT SUM(s.total_amount) as total_sale, COUNT(s.id) as total_orders FROM sales s WHERE 1=1 $date_cond";
                    $sale_summary = $pdo->query($sale_query)->fetch();
                    $total_sale = $sale_summary['total_sale'] ?? 0;

                    $profit_query = "SELECT SUM(s.quantity_sold * (i.sale_price - i.purchase_price)) as net_profit FROM sales s JOIN inventory i ON s.product_id = i.id WHERE 1=1 $date_cond";
                    $profit_summary = $pdo->query($profit_query)->fetch();
                    $net_profit = $profit_summary['net_profit'] ?? 0;

                    $total_prods = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
                    $out_stock_count = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= 0")->fetchColumn();
                ?>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
                        <h2 class="fs-4 fs-md-3 m-0">Dashboard Overview & Profit Analysis</h2>
                        <span class="text-muted small"><i class="fas fa-calendar-alt me-1"></i> <?= date('d M Y'); ?></span>
                    </div>

                    <div class="card card-box p-3 mb-4">
                        <form method="GET" class="row g-2 align-items-end">
                            <input type="hidden" name="page" value="dashboard">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Filter By Period</label>
                                <select name="filter_type" class="form-control" onchange="toggleFilterInput(this.value)">
                                    <option value="all" <?= $filter_type=='all'?'selected':''; ?>>All Time</option>
                                    <option value="day" <?= $filter_type=='day'?'selected':''; ?>>Specific Date (Day)</option>
                                    <option value="month" <?= $filter_type=='month'?'selected':''; ?>>Month</option>
                                    <option value="year" <?= $filter_type=='year'?'selected':''; ?>>Year</option>
                                </select>
                            </div>
                            <div class="col-md-4" id="inputContainer" style="<?= $filter_type=='all'?'display:none;':''; ?>">
                                <label class="form-label small fw-bold">Select Value</label>
                                <?php if($filter_type=='day'): ?>
                                    <input type="date" name="filter_val" class="form-control" value="<?= $filter_val; ?>">
                                <?php elseif($filter_type=='month'): ?>
                                    <input type="month" name="filter_val" class="form-control" value="<?= $filter_val; ?>">
                                <?php elseif($filter_type=='year'): ?>
                                    <input type="number" name="filter_val" class="form-control" placeholder="e.g. 2026" value="<?= $filter_val; ?>">
                                <?php else: ?>
                                    <input type="text" name="filter_val" class="form-control" value="<?= $filter_val; ?>">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                            </div>
                            <div class="col-md-2">
                                <a href="index.php?page=dashboard" class="btn btn-outline-secondary w-100">Reset</a>
                            </div>
                        </form>
                    </div>

                    <script>
                    function toggleFilterInput(val) {
                        if(val === 'all') {
                            document.getElementById('inputContainer').style.display = 'none';
                        } else {
                            document.getElementById('inputContainer').style.display = 'block';
                        }
                    }
                    </script>

                    <div class="row g-3">
                        <div class="col-sm-6 col-xl-3">
                            <div class="card card-box p-3 border-start border-secondary border-4">
                                <span class="text-muted small fw-bold">TOTAL PURCHASE INVESTMENT</span>
                                <h4 class="fw-bold mt-1 text-secondary fs-5">₹<?= number_format($total_purchase_investment, 2); ?></h4>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card card-box p-3 border-start border-primary border-4">
                                <span class="text-muted small fw-bold">TOTAL INVENTORY VALUE</span>
                                <h4 class="fw-bold mt-1 text-primary fs-5">₹<?= number_format($inv_summary['total_inv'] ?? 0, 2); ?></h4>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card card-box p-3 border-start border-success border-4">
                                <span class="text-muted small fw-bold">TOTAL SALE INCOME</span>
                                <h4 class="fw-bold mt-1 text-success fs-5">₹<?= number_format($total_sale, 2); ?></h4>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card card-box p-3 border-start border-warning border-4">
                                <span class="text-muted small fw-bold">NET PROFIT</span>
                                <h4 class="fw-bold mt-1 <?= $net_profit >= 0 ? 'text-success' : 'text-danger'; ?> fs-5">₹<?= number_format($net_profit, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-sm-6 col-xl-3">
                            <div class="card card-box p-3 border-start border-danger border-4">
                                <span class="text-muted small fw-bold">OUT OF STOCK</span>
                                <h4 class="fw-bold mt-1 text-danger fs-5"><?= $out_stock_count; ?></h4>
                            </div>
                        </div>
                    </div>

                <?php 
                elseif ($page == 'shop'):
                    $shop = $pdo->query("SELECT * FROM shop_details WHERE id=1")->fetch();
                ?>
                    <h2 class="fs-4">Shop Settings</h2>
                    <?php if(isset($_GET['msg'])) echo "<div class='alert alert-success mt-3'>{$_GET['msg']}</div>"; ?>
                    <div class="card card-box p-3 p-md-4 mt-3 col-12 col-md-8">
                        <form method="POST">
                            <div class="mb-3"><label class="form-label fw-bold small">Shop Name</label><input type="text" name="name" class="form-control" value="<?= $shop['name']; ?>" required></div>
                            <div class="mb-3"><label class="form-label fw-bold small">Mobile Number</label><input type="text" name="mobile" class="form-control" value="<?= $shop['mobile']; ?>" required></div>
                            <div class="mb-3"><label class="form-label fw-bold small">Website</label><input type="text" name="website" class="form-control" value="<?= $shop['website']; ?>"></div>
                            <div class="mb-3"><label class="form-label fw-bold small">GST Number</label><input type="text" name="gst" class="form-control" value="<?= $shop['gst']; ?>"></div>
                            <div class="mb-3"><label class="form-label fw-bold small">Address</label><textarea name="address" class="form-control" rows="3"><?= $shop['address']; ?></textarea></div>
                            <button type="submit" name="update_shop" class="btn btn-primary px-4 fw-bold w-100 w-md-auto">Update Details</button>
                        </form>
                    </div>

                <?php 
                elseif ($page == 'admin_manager'):
                    $admin_info = $pdo->query("SELECT * FROM admin WHERE id=1")->fetch();
                ?>
                    <h2 class="fs-4">Admin Credentials Manager</h2>
                    <?php 
                    if(isset($admin_msg)) echo "<div class='alert alert-success mt-3'>$admin_msg</div>";
                    if(isset($admin_error)) echo "<div class='alert alert-danger mt-3'>$admin_error</div>";
                    ?>
                    <div class="card card-box p-3 p-md-4 mt-3 col-12 col-md-6">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold small">New Username</label>
                                <input type="text" name="new_username" class="form-control" value="<?= $admin_info['username'] ?? 'admin'; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">New Password</label>
                                <input type="text" name="new_password" class="form-control" value="<?= $admin_info['password'] ?? '1234'; ?>" required>
                            </div>
                            <button type="submit" name="update_admin" class="btn btn-primary px-4 fw-bold w-100"><i class="fas fa-save me-1"></i> Update Credentials</button>
                        </form>
                    </div>

                <?php 
                elseif ($page == 'suppliers'):
                    $search = $_GET['search'] ?? '';
                    $sup_code_items = $_GET['sup_code_items'] ?? '';
                ?>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
                        <h2 class="fs-4 m-0">Supplier Management</h2>
                        <form method="GET" class="d-flex">
                            <input type="hidden" name="page" value="suppliers">
                            <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Search Supplier..." value="<?= $search; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
                        </form>
                    </div>

                    <?php if(!empty($sup_code_items)): 
                        $s_info = $pdo->prepare("SELECT * FROM suppliers WHERE sup_code=?");
                        $s_info->execute([$sup_code_items]);
                        $sup_data = $s_info->fetch();
                    ?>
                        <div class="card card-box p-3 p-md-4 mb-4 bg-light border">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="text-primary mb-0 fs-6"><i class="fas fa-boxes me-2"></i>Items from Supplier: <?= $sup_data['name'] ?? $sup_code_items; ?></h5>
                                <a href="index.php?page=suppliers" class="btn btn-sm btn-secondary">Back</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle bg-white">
                                    <thead><tr><th>Code</th><th>Product Name</th><th>Stock</th><th>Purchase Price</th><th>Sale Price</th></tr></thead>
                                    <tbody>
                                        <?php 
                                        $s_prods = $pdo->prepare("SELECT * FROM inventory WHERE supplier_code=?");
                                        $s_prods->execute([$sup_code_items]);
                                        while($sp = $s_prods->fetch()):
                                        ?>
                                        <tr>
                                            <td><code><?= $sp['product_code']; ?></code></td>
                                            <td><?= $sp['product_name']; ?></td>
                                            <td><span class="badge bg-success"><?= $sp['quantity'] . ' ' . $sp['unit']; ?></span></td>
                                            <td>₹<?= $sp['purchase_price']; ?></td>
                                            <td>₹<?= $sp['sale_price']; ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3 mt-1">
                        <div class="col-12 col-lg-4">
                            <div class="card card-box p-3">
                                <h5 class="fw-bold mb-3 fs-6">Add Supplier</h5>
                                <form method="POST">
                                    <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control" required></div>
                                    <div class="mb-2"><label class="form-label small">Mobile</label><input type="text" name="mobile" class="form-control" required></div>
                                    <div class="mb-2"><label class="form-label small">GST</label><input type="text" name="gst" class="form-control"></div>
                                    <div class="mb-2"><label class="form-label small">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                                    <button type="submit" name="add_supplier" class="btn btn-success w-100 mt-2">Add Supplier</button>
                                </form>
                            </div>
                        </div>
                        <div class="col-12 col-lg-8">
                            <div class="card card-box p-3">
                                <h5 class="fw-bold mb-3 fs-6">Supplier List</h5>
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead><tr><th>Code</th><th>Name</th><th>Mobile</th><th>Actions</th></tr></thead>
                                        <tbody>
                                            <?php 
                                            $query = "SELECT * FROM suppliers WHERE name LIKE ?";
                                            $stmt = $pdo->prepare($query);
                                            $stmt->execute(["%$search%"]);
                                            while($s = $stmt->fetch()):
                                            ?>
                                            <tr>
                                                <td><span class="badge bg-secondary"><?= $s['sup_code']; ?></span></td>
                                                <td><?= $s['name']; ?><br><small class="text-muted"><?= $s['gst']; ?></small></td>
                                                <td><?= $s['mobile']; ?></td>
                                                <td>
                                                    <a href="index.php?page=suppliers&sup_code_items=<?= $s['sup_code']; ?>" class="btn btn-sm btn-info text-white me-1" title="View all items"><i class="fas fa-box-open"></i></a>
                                                    <a href="index.php?page=suppliers&delete_sup=<?= $s['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete supplier?')"><i class="fas fa-trash"></i></a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php 
                elseif ($page == 'categories'):
                    $search = $_GET['search'] ?? '';
                ?>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
                        <h2 class="fs-4 m-0">Category Management</h2>
                        <form method="GET" class="d-flex">
                            <input type="hidden" name="page" value="categories">
                            <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Search Category..." value="<?= $search; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
                        </form>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-12 col-lg-4">
                            <div class="card card-box p-3">
                                <h5 class="fw-bold mb-3 fs-6">Add Category</h5>
                                <form method="POST">
                                    <div class="mb-2"><label class="form-label small">Category Name</label><input type="text" name="name" class="form-control" required></div>
                                    <button type="submit" name="add_category" class="btn btn-primary w-100 mt-2">Add Category</button>
                                </form>
                            </div>
                        </div>
                        <div class="col-12 col-lg-8">
                            <div class="card card-box p-3">
                                <h5 class="fw-bold mb-3 fs-6">Category List</h5>
                                <ul class="list-group list-group-flush">
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT * FROM categories WHERE name LIKE ?");
                                    $stmt->execute(["%$search%"]);
                                    while($c = $stmt->fetch()):
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-1">
                                        <span class="fw-semibold"><?= $c['name']; ?></span>
                                        <div>
                                            <a href="index.php?page=category_products&cat_id=<?= $c['id']; ?>" class="btn btn-sm btn-info text-white me-1">View</a>
                                            <a href="index.php?page=categories&delete_cat=<?= $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete category?')"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </li>
                                    <?php endwhile; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                <?php 
                elseif ($page == 'category_products'):
                    $cat_id = $_GET['cat_id'];
                    $c_name = $pdo->prepare("SELECT name FROM categories WHERE id=?");
                    $c_name->execute([$cat_id]);
                    $cat_title = $c_name->fetchColumn();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="fs-5">Products in: <span class="text-primary"><?= $cat_title; ?></span></h2>
                        <a href="index.php?page=categories" class="btn btn-sm btn-secondary">Back</a>
                    </div>
                    <div class="card card-box p-3">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead><tr><th>Image</th><th>Code</th><th>Name</th><th>Stock</th><th>Price</th></tr></thead>
                                <tbody>
                                    <?php
                                    $prods = $pdo->prepare("SELECT * FROM inventory WHERE category_id=?");
                                    $prods->execute([$cat_id]);
                                    while($p = $prods->fetch()):
                                    ?>
                                    <tr>
                                        <td><img src="<?= $p['image'] ?: 'https://via.placeholder.com/40'; ?>" width="40" height="40" class="product-img" onclick="showFullImg('<?= $p['image'] ?: 'https://via.placeholder.com/400'; ?>')"></td>
                                        <td><code><?= $p['product_code']; ?></code></td>
                                        <td><?= $p['product_name']; ?></td>
                                        <td><?= $p['quantity'] . ' ' . $p['unit']; ?></td>
                                        <td>₹<?= $p['sale_price']; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php 
                elseif ($page == 'all_bills_list'):
                    $search = $_GET['search'] ?? '';
                ?>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
                        <h2 class="fs-4 m-0">All External Bills</h2>
                        <a href="index.php?page=add_all_bill" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Upload New Bill</a>
                    </div>

                    <div class="card card-box p-3 mb-3">
                        <form method="GET" class="row g-2">
                            <input type="hidden" name="page" value="all_bills_list">
                            <div class="col-10 col-md-6">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by Name, GST or Mobile..." value="<?= $search; ?>">
                            </div>
                            <div class="col-2 col-md-2">
                                <button type="submit" class="btn btn-secondary btn-sm w-100">Search</button>
                            </div>
                        </form>
                    </div>

                    <div class="card card-box p-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Bill Name</th>
                                        <th>GST No</th>
                                        <th>Mobile</th>
                                        <th>Images</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT * FROM all_bills WHERE bill_name LIKE ? OR gst_no LIKE ? OR mobile LIKE ? ORDER BY id DESC");
                                    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
                                    $bills = $stmt->fetchAll();

                                    if(count($bills) > 0) {
                                        foreach($bills as $b) {
                                            $imgs = explode(',', $b['images']);
                                            echo "<tr>";
                                            echo "<td>#{$b['id']}</td>";
                                            echo "<td><small>{$b['created_at']}</small></td>";
                                            echo "<td class='fw-bold text-primary'>{$b['bill_name']}</td>";
                                            echo "<td><code>" . ($b['gst_no'] ?: 'N/A') . "</code></td>";
                                            echo "<td>" . ($b['mobile'] ?: 'N/A') . "</td>";
                                            echo "<td>";
                                            foreach($imgs as $img) {
                                                if(!empty($img)) {
                                                    echo "<a href='$img' target='_blank' download class='me-1 mb-1 d-inline-block'>";
                                                    echo "<img src='$img' width='30' height='30' class='rounded border shadow-sm' style='object-fit: cover;'>";
                                                    echo "</a>";
                                                }
                                            }
                                            echo "</td>";
                                            echo "<td>";
                                            echo "<button class='btn btn-sm btn-info text-white me-1 mb-1' onclick='viewBillImgs(" . json_encode($imgs) . ")'><i class='fas fa-eye'></i></button>";
                                            echo "<a href='index.php?page=all_bills_list&delete_bill={$b['id']}' class='btn btn-sm btn-outline-danger mb-1' onclick='return confirm(\"Delete this bill?\")'><i class='fas fa-trash'></i></a>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='text-center py-4 text-muted'>No bills uploaded yet.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Modal for viewing multiple images with download option -->
                    <div class="modal fade" id="viewBillModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content bg-white shadow-lg border-0 p-3">
                                <div class="modal-header">
                                    <h5 class="fw-bold fs-6">Bill Images Preview & Download</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <div id="modalImagesContainer" class="d-flex flex-wrap justify-content-center gap-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    function viewBillImgs(images) {
                        let container = document.getElementById('modalImagesContainer');
                        container.innerHTML = '';
                        images.forEach(imgUrl => {
                            if(imgUrl.trim() !== '') {
                                let div = document.createElement('div');
                                div.className = 'text-center border p-2 rounded shadow-sm bg-light w-100';
                                div.innerHTML = `
                                    <img src="${imgUrl}" class="img-fluid rounded mb-2" style="max-height: 250px; display: block; margin: 0 auto;"><br>
                                    <a href="${imgUrl}" download class="btn btn-sm btn-success"><i class="fas fa-download me-1"></i> Download Image</a>
                                `;
                                container.appendChild(div);
                            }
                        });
                        var myModal = new bootstrap.Modal(document.getElementById('viewBillModal'));
                        myModal.show();
                    }
                    </script>

                <?php 
                elseif ($page == 'add_all_bill'):
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="fs-4">Upload New Bill</h2>
                        <a href="index.php?page=all_bills_list" class="btn btn-secondary btn-sm">Back</a>
                    </div>
                    <div class="card card-box p-3 p-md-4 col-12 col-md-8 mx-auto">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Bill Name / Description</label>
                                <input type="text" name="bill_name" class="form-control" placeholder="e.g. Supplier Purchase Bill" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">GST Number (Optional)</label>
                                <input type="text" name="gst_no" class="form-control" placeholder="Enter GST Number">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Mobile Number (Optional)</label>
                                <input type="text" name="mobile" class="form-control" placeholder="Enter Mobile Number">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Upload Multiple Bill Images</label>
                                <input type="file" name="bill_images[]" class="form-control" multiple required>
                                <small class="text-muted" style="font-size: 0.8rem;">You can select multiple images at once.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Date & Time</label>
                                <input type="text" class="form-control bg-light" value="<?= date('Y-m-d H:i:s'); ?> (Automatic)" disabled>
                            </div>
                            <button type="submit" name="add_external_bill" class="btn btn-primary w-100 py-2 fw-bold">Save Bill & Upload</button>
                        </form>
                    </div>

                <?php 
                elseif ($page == 'inventory'):
                    $search = $_GET['search'] ?? '';

                    $t_query = "SELECT SUM(quantity * purchase_price) as total_pur_val, SUM(quantity * sale_price) as total_sale_val, SUM(quantity) as total_qty FROM inventory WHERE product_name LIKE ? OR product_code LIKE ?";
                    $t_stmt = $pdo->prepare($t_query);
                    $t_stmt->execute(["%$search%", "%$search%"]);
                    $inv_totals = $t_stmt->fetch();
                ?>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
                        <h2 class="fs-4 m-0">Inventory Management</h2>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addInvModal"><i class="fas fa-plus me-1"></i> Add Product</button>
                    </div>

                    <div class="card card-box p-3 mb-3">
                        <form method="GET" class="row g-2">
                            <input type="hidden" name="page" value="inventory">
                            <div class="col-9 col-md-10">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by Product Name or Code..." value="<?= $search; ?>">
                            </div>
                            <div class="col-3 col-md-2">
                                <button type="submit" class="btn btn-secondary btn-sm w-100">Filter</button>
                            </div>
                        </form>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <div class="card card-box p-3 bg-light border">
                                <span class="text-muted small fw-bold" style="font-size: 0.75rem;">TOTAL STOCK QTY</span>
                                <h6 class="fw-bold m-0 text-dark"><?= number_format($inv_totals['total_qty'] ?? 0); ?> Units</h6>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="card card-box p-3 bg-light border">
                                <span class="text-muted small fw-bold" style="font-size: 0.75rem;">PURCHASE VALUE</span>
                                <h6 class="fw-bold m-0 text-primary">₹<?= number_format($inv_totals['total_pur_val'] ?? 0, 2); ?></h6>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="card card-box p-3 bg-light border">
                                <span class="text-muted small fw-bold" style="font-size: 0.75rem;">SALE VALUE</span>
                                <h6 class="fw-bold m-0 text-success">₹<?= number_format($inv_totals['total_sale_val'] ?? 0, 2); ?></h6>
                            </div>
                        </div>
                    </div>

                    <div class="card card-box p-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Code / Name</th>
                                        <th>Category</th>
                                        <th>Supplier</th>
                                        <th>Stock</th>
                                        <th>Price (Pur/Sale)</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $query = "SELECT i.*, c.name as cat_name FROM inventory i LEFT JOIN categories c ON i.category_id=c.id WHERE i.product_name LIKE ? OR i.product_code LIKE ? ORDER BY i.id DESC";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute(["%$search%", "%$search%"]);
                                    $inventory_items = $stmt->fetchAll();

                                    foreach($inventory_items as $item):
                                    ?>
                                    <tr>
                                        <td><img src="<?= $item['image'] ?: 'https://via.placeholder.com/40'; ?>" width="40" height="40" class="product-img shadow-sm" onclick="showFullImg('<?= $item['image'] ?: 'https://via.placeholder.com/400'; ?>')"></td>
                                        <td><strong class="text-primary"><?= $item['product_code']; ?></strong><br><span class="fw-semibold"><?= $item['product_name']; ?></span></td>
                                        <td><?= $item['cat_name']; ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= $item['supplier_code']; ?></span></td>
                                        <td>
                                            <?php if($item['quantity'] <= 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?= $item['quantity'] . ' ' . $item['unit']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>₹<?= $item['purchase_price']; ?> / <b class="text-success">₹<?= $item['sale_price']; ?></b></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary mb-1" data-bs-toggle="modal" data-bs-target="#editInvModal<?= $item['id']; ?>"><i class="fas fa-edit"></i></button>
                                            <a href="index.php?page=inventory&delete_inv=<?= $item['id']; ?>" class="btn btn-sm btn-outline-danger mb-1" onclick="return confirm('Delete product?')"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php foreach($inventory_items as $item): ?>
                    <div class="modal fade" id="editInvModal<?= $item['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" enctype="multipart/form-data" class="modal-content bg-white shadow-lg border-0">
                                <input type="hidden" name="id" value="<?= $item['id']; ?>">
                                <input type="hidden" name="old_image" value="<?= $item['image']; ?>">
                                <div class="modal-header"><h5 class="fw-bold fs-6">Edit Product: <?= $item['product_code']; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body text-start">
                                    <div class="mb-2"><label class="form-label small">Product Name</label><input type="text" name="product_name" class="form-control" value="<?= $item['product_name']; ?>" required></div>
                                    <div class="mb-2">
                                        <label class="form-label small">Category</label>
                                        <select name="category_id" class="form-control select2-modal" required>
                                            <option value="">Type to search category...</option>
                                            <?php 
                                            $cats = $pdo->query("SELECT * FROM categories")->fetchAll();
                                            foreach($cats as $c) {
                                                $sel = ($c['id'] == $item['category_id']) ? 'selected' : '';
                                                echo "<option value='{$c['id']}' $sel>{$c['name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Supplier Code</label>
                                        <select name="supplier_code" class="form-control select2-modal" required>
                                            <option value="">Type to search supplier...</option>
                                            <?php 
                                            $sups = $pdo->query("SELECT * FROM suppliers")->fetchAll();
                                            foreach($sups as $s) {
                                                $sel = ($s['sup_code'] == $item['supplier_code']) ? 'selected' : '';
                                                echo "<option value='{$s['sup_code']}' $sel>[{$s['sup_code']}] {$s['name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-6 mb-2"><label class="form-label small">Quantity</label><input type="number" name="quantity" class="form-control" value="<?= $item['quantity']; ?>" required></div>
                                        <div class="col-6 mb-2"><label class="form-label small">Unit</label>
                                            <select name="unit" class="form-control">
                                                <option value="piece" <?= $item['unit']=='piece'?'selected':''; ?>>Piece</option>
                                                <option value="box" <?= $item['unit']=='box'?'selected':''; ?>>Box</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-2"><label class="form-label small">Size</label>
                                        <select name="size" class="form-control">
                                            <?php foreach(['Regular', 'Small', 'Medium', 'Large', 'Extra Large'] as $sz): ?>
                                                <option value="<?= $sz; ?>" <?= $item['size']==$sz?'selected':''; ?>><?= $sz; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-6 mb-2"><label class="form-label small">Purchase Price</label><input type="number" step="0.01" name="purchase_price" class="form-control" value="<?= $item['purchase_price']; ?>" required></div>
                                        <div class="col-6 mb-2"><label class="form-label small">Sale Price</label><input type="number" step="0.01" name="sale_price" class="form-control" value="<?= $item['sale_price']; ?>" required></div>
                                    </div>
                                    <div class="mb-2"><label class="form-label small">New Image (Optional)</label><input type="file" name="image" class="form-control"></div>
                                </div>
                                <div class="modal-footer"><button type="submit" name="edit_inventory" class="btn btn-primary btn-sm">Save Changes</button></div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="modal fade" id="addInvModal" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" enctype="multipart/form-data" class="modal-content bg-white shadow-lg border-0">
                                <div class="modal-header"><h5 class="fw-bold fs-6">Add Inventory Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body text-start">
                                    <div class="mb-2"><label class="form-label small">Product Name</label><input type="text" name="product_name" class="form-control" required></div>
                                    <div class="mb-2">
                                        <label class="form-label small">Category</label>
                                        <select name="category_id" class="form-control select2-modal" required>
                                            <option value="">Type to search category...</option>
                                            <?php 
                                            $cats = $pdo->query("SELECT * FROM categories")->fetchAll();
                                            foreach($cats as $c) {
                                                echo "<option value='{$c['id']}'>{$c['name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Supplier Code</label>
                                        <select name="supplier_code" class="form-control select2-modal" required>
                                            <option value="">Type to search supplier...</option>
                                            <?php 
                                            $sups = $pdo->query("SELECT * FROM suppliers")->fetchAll();
                                            foreach($sups as $s) {
                                                echo "<option value='{$s['sup_code']}'>[{$s['sup_code']}] {$s['name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-6 mb-2"><label class="form-label small">Quantity</label><input type="number" name="quantity" class="form-control" required></div>
                                        <div class="col-6 mb-2"><label class="form-label small">Unit</label>
                                            <select name="unit" id="unitSelect" class="form-control" onchange="toggleBoxField(this.value)">
                                                <option value="piece">Piece</option>
                                                <option value="box">Box</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-2" id="boxField" style="display:none;"><label class="form-label small">Pieces in Box</label><input type="number" name="pieces_in_box" class="form-control" value="1"></div>
                                    <div class="mb-2"><label class="form-label small">Size</label>
                                        <select name="size" class="form-control">
                                            <option value="Regular">Regular</option>
                                            <option value="Small">Small</option>
                                            <option value="Medium">Medium</option>
                                            <option value="Large">Large</option>
                                            <option value="Extra Large">Extra Large</option>
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-6 mb-2"><label class="form-label small">Purchase Price</label><input type="number" step="0.01" name="purchase_price" class="form-control" required></div>
                                        <div class="col-6 mb-2"><label class="form-label small">Sale Price</label><input type="number" step="0.01" name="sale_price" class="form-control" required></div>
                                    </div>
                                    <div class="mb-2"><label class="form-label small">Product Image</label><input type="file" name="image" class="form-control"></div>
                                </div>
                                <div class="modal-footer"><button type="submit" name="add_inventory" class="btn btn-primary btn-sm">Save Product</button></div>
                            </form>
                        </div>
                    </div>

                    <script>
                    function toggleBoxField(val) {
                        document.getElementById('boxField').style.display = (val === 'box') ? 'block' : 'none';
                    }
                    </script>

                <?php 
                elseif ($page == 'sales'):
                    if(isset($_GET['invoice'])):
                        $inv_no = $_GET['invoice'];
                        $sale = $pdo->prepare("SELECT s.*, i.product_name, i.product_code, sh.* FROM sales s JOIN inventory i ON s.product_id=i.id JOIN shop_details sh WHERE s.invoice_no=?");
                        $sale->execute([$inv_no]);
                        $bill = $sale->fetch();
                ?>
                    <div class="card card-box p-3 p-md-5 col-12 col-md-8 mx-auto shadow-lg" id="billContainer">
                        <div class="text-center border-bottom pb-4 mb-4">
                            <h3 class="fw-bold text-uppercase text-primary mb-1 fs-4"><?= $bill['name']; ?></h3>
                            <p class="text-muted mb-1 small"><?= $bill['address']; ?></p>
                            <p class="text-muted mb-1 small">Mobile: <?= $bill['mobile']; ?> | Website: <?= $bill['website']; ?></p>
                            <p class="fw-bold m-0 small">GSTIN: <?= $bill['gst']; ?></p>
                        </div>
                        <div class="row mb-4">
                            <div class="col-6">
                                <h6 class="text-muted small fw-bold">INVOICE DETAILS</h6>
                                <p class="mb-1 small"><b>Invoice No:</b> <?= $bill['invoice_no']; ?></p>
                                <p class="mb-0 small"><b>Date:</b> <?= $bill['sale_date']; ?></p>
                            </div>
                            <div class="col-6 text-end">
                                <h6 class="text-muted small fw-bold">CUSTOMER DETAILS</h6>
                                <p class="mb-1 small"><b>Name:</b> <?= $bill['customer_name']; ?></p>
                                <p class="mb-1 small"><b>Mobile:</b> <?= $bill['customer_mobile']; ?></p>
                                <p class="mb-0 small"><b>Address:</b> <?= $bill['customer_address']; ?></p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light"><tr><th>Item & Code</th><th>Qty</th><th>Unit</th><th>Amount</th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td><b><?= $bill['product_code']; ?></b> - <?= $bill['product_name']; ?></td>
                                        <td><?= $bill['quantity_sold']; ?></td>
                                        <td><?= $bill['unit_sold']; ?></td>
                                        <td>₹<?= number_format($bill['total_amount'] - $bill['gst_amount'], 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="row justify-content-end">
                            <div class="col-12 col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr><td>Subtotal:</td><td class="text-end">₹<?= number_format($bill['total_amount'] - $bill['gst_amount'], 2); ?></td></tr>
                                    <tr><td>GST (<?= $bill['gst_percent']; ?>%):</td><td class="text-end">₹<?= number_format($bill['gst_amount'], 2); ?></td></tr>
                                    <tr class="border-top fw-bold fs-6"><td>Grand Total:</td><td class="text-end text-primary">₹<?= number_format($bill['total_amount'], 2); ?></td></tr>
                                </table>
                            </div>
                        </div>
                        <div class="text-center mt-4 pt-3 border-top text-muted small">
                            <p class="mb-1">Thank you for your business! Please visit again.</p>
                        </div>
                        <div class="mt-4 text-center no-print d-flex flex-column flex-sm-row justify-content-center gap-2">
                            <button onclick="window.print()" class="btn btn-success px-4 fw-bold btn-sm"><i class="fas fa-print me-1"></i> Print / Download</button>
                            <a href="index.php?page=sales" class="btn btn-outline-primary px-4 fw-bold btn-sm">New Sale</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <h2 class="fs-4">New Sale Counter</h2>
                    <?php if(isset($sale_error)) echo "<div class='alert alert-danger mt-3'>$sale_error</div>"; ?>
                    <div class="card card-box p-3 p-md-4 col-12 col-md-8 mt-3">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Customer Type</label>
                                <select name="cust_type" id="custType" class="form-control" onchange="toggleCustomer(this.value)">
                                    <option value="Regular">Regular Customer</option>
                                    <option value="Custom">Customized Customer</option>
                                </select>
                            </div>
                            <div id="customCustFields" style="display:none;" class="p-3 bg-light rounded mb-3 border">
                                <div class="mb-2"><label class="form-label small">Customer Name</label><input type="text" name="cust_name" class="form-control"></div>
                                <div class="mb-2"><label class="form-label small">Mobile</label><input type="text" name="customer_mobile" class="form-control"></div>
                                <div class="mb-2"><label class="form-label small">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Search & Select Product</label>
                                <select name="product_id" id="productSelect" class="form-control select2" required>
                                    <option value="">Type to search product...</option>
                                    <?php 
                                    $prods = $pdo->query("SELECT * FROM inventory WHERE quantity > 0")->fetchAll();
                                    foreach($prods as $p) {
                                        echo "<option value='{$p['id']}'>[Code: {$p['product_code']}] {$p['product_name']} | Stock: {$p['quantity']} {$p['unit']} | Price: ₹{$p['sale_price']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Quantity Sold</label>
                                <input type="number" name="quantity_sold" class="form-control" value="1" min="1" required>
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" name="apply_gst" class="form-check-input" id="gstCheck" onchange="toggleGstInput(this.checked)">
                                <label class="form-check-label fw-bold small" for="gstCheck">Apply GST % Optional</label>
                            </div>
                            <div class="mb-3" id="gstPercentField" style="display:none;">
                                <label class="form-label small">Enter GST Percentage (e.g., 5, 12, 18)</label>
                                <input type="number" step="0.01" name="gst_percent" class="form-control" value="18">
                            </div>
                            <button type="submit" name="process_sale" class="btn btn-success w-100 py-2 fw-bold shadow-sm">Complete Sale & Generate Bill</button>
                        </form>
                    </div>
                    <script>
                    function toggleCustomer(val) {
                        document.getElementById('customCustFields').style.display = (val === 'Custom') ? 'block' : 'none';
                    }
                    function toggleGstInput(isChecked) {
                        document.getElementById('gstPercentField').style.display = isChecked ? 'block' : 'none';
                    }
                    </script>
                    <?php endif; ?>

                <?php 
                elseif ($page == 'all_sales'):
                    $search = $_GET['search'] ?? '';
                    $date_filter = $_GET['date_filter'] ?? '';

                    $ts_query = "SELECT SUM(s.total_amount) as total_sales_amt, SUM(s.quantity_sold) as total_qty_sold FROM sales s JOIN inventory i ON s.product_id=i.id WHERE (s.invoice_no LIKE ? OR s.customer_name LIKE ?)";
                    if($date_filter) $ts_query .= " AND DATE(s.sale_date) = '$date_filter'";
                    $ts_stmt = $pdo->prepare($ts_query);
                    $ts_stmt->execute(["%$search%", "%$search%"]);
                    $sales_totals = $ts_stmt->fetch();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="fs-4">All Sales History</h2>
                    </div>
                    <div class="card card-box p-3 mb-3">
                        <form method="GET" class="row g-2">
                            <input type="hidden" name="page" value="all_sales">
                            <div class="col-12 col-md-5">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Customer or Invoice..." value="<?= $search; ?>">
                            </div>
                            <div class="col-8 col-md-4">
                                <input type="date" name="date_filter" class="form-control form-control-sm" value="<?= $date_filter; ?>">
                            </div>
                            <div class="col-4 col-md-3">
                                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i> Filter</button>
                            </div>
                        </form>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <div class="card card-box p-3 bg-light border">
                                <span class="text-muted small fw-bold" style="font-size: 0.75rem;">UNITS SOLD</span>
                                <h6 class="fw-bold m-0 text-dark"><?= number_format($sales_totals['total_qty_sold'] ?? 0); ?> Units</h6>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="card card-box p-3 bg-light border">
                                <span class="text-muted small fw-bold" style="font-size: 0.75rem;">SALES INCOME</span>
                                <h6 class="fw-bold m-0 text-success">₹<?= number_format($sales_totals['total_sales_amt'] ?? 0, 2); ?></h6>
                            </div>
                        </div>
                    </div>

                    <div class="card card-box p-3">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead><tr><th>Invoice</th><th>Customer</th><th>Product</th><th>Qty</th><th>Amount</th><th>Date</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $query = "SELECT s.*, i.product_name FROM sales s JOIN inventory i ON s.product_id=i.id WHERE (s.invoice_no LIKE ? OR s.customer_name LIKE ?)";
                                    if($date_filter) $query .= " AND DATE(s.sale_date) = '$date_filter'";
                                    $query .= " ORDER BY s.id DESC";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute(["%$search%", "%$search%"]);
                                    while($sl = $stmt->fetch()):
                                    ?>
                                    <tr>
                                        <td><a href="index.php?page=sales&invoice=<?= $sl['invoice_no']; ?>" class="fw-bold text-decoration-none"><?= $sl['invoice_no']; ?></a></td>
                                        <td><?= $sl['customer_name']; ?><br><small class="text-muted"><?= $sl['customer_mobile']; ?></small></td>
                                        <td><?= $sl['product_name']; ?></td>
                                        <td><?= $sl['quantity_sold'] . ' ' . $sl['unit_sold']; ?></td>
                                        <td class="fw-bold text-success">₹<?= number_format($sl['total_amount'], 2); ?></td>
                                        <td><small><?= $sl['sale_date']; ?></small></td>
                                        <td>
                                            <a href="index.php?page=sales&invoice=<?= $sl['invoice_no']; ?>" class="btn btn-sm btn-outline-secondary mb-1" title="View"><i class="fas fa-eye"></i></a>
                                            <a href="index.php?page=all_sales&delete_sale=<?= $sl['id']; ?>" class="btn btn-sm btn-outline-danger mb-1" onclick="return confirm('Delete sale?')"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php 
                elseif ($page == 'out_of_stock'):
                    $sel_cat = $_GET['cat_filter'] ?? '';
                ?>
                    <h2 class="fs-4">Out of Stock Products</h2>
                    <div class="card card-box p-3 mt-3">
                        <form method="GET" class="row g-3 mb-3">
                            <input type="hidden" name="page" value="out_of_stock">
                            <div class="col-12 col-md-4">
                                <select name="cat_filter" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <option value="">Filter by Category</option>
                                    <?php 
                                    $cats = $pdo->query("SELECT * FROM categories")->fetchAll();
                                    foreach($cats as $c) {
                                        $sel = ($sel_cat == $c['id']) ? 'selected' : '';
                                        echo "<option value='{$c['id']}' $sel>{$c['name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead><tr><th>Code</th><th>Product Name</th><th>Category</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $query = "SELECT i.*, c.name as cat_name FROM inventory i LEFT JOIN categories c ON i.category_id=c.id WHERE i.quantity <= 0";
                                    if($sel_cat) $query .= " AND i.category_id = " . intval($sel_cat);
                                    $oos = $pdo->query($query)->fetchAll();
                                    if(count($oos) > 0) {
                                        foreach($oos as $o) {
                                            echo "<tr><td><code>{$o['product_code']}</code></td><td>{$o['product_name']}</td><td>{$o['cat_name']}</td><td><span class='badge bg-danger'>Out of Stock</span></td></tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center py-4 text-muted'>No out of stock products found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                </div>

                <footer class="text-center py-3 text-muted no-print main-footer mt-5">
                    Developed By <a href="https://maxtech.great-site.net" target="_blank" class="text-decoration-none fw-bold text-primary">MaxMentor</a> | 
                    <a href="https://github.com/maxmentor/" target="_blank" class="text-decoration-none text-dark"><i class="fab fa-github"></i> GitHub</a>
                </footer>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fullImgModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-transparent border-0 text-center">
                <div class="modal-body position-relative">
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                    <img id="fullImageDisplay" src="" class="img-fluid rounded shadow-lg" style="max-height: 80vh;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        $('.modal').on('shown.bs.modal', function () {
            $(this).find('.select2-modal').select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $(this)
            });
        });
    });

    function showFullImg(src) {
        document.getElementById('fullImageDisplay').src = src;
        var myModal = new bootstrap.Modal(document.getElementById('fullImgModal'));
        myModal.show();
    }
    </script>
<?php endif; ?>
</body>
</html>
