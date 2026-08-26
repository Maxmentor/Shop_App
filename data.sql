

-- 1. Shop Details
CREATE TABLE IF NOT EXISTS shop_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    mobile VARCHAR(50),
    website VARCHAR(255),
    gst VARCHAR(50),
    address TEXT
);
INSERT INTO shop_details (id, name, mobile, website, gst, address) VALUES 
(1, 'My Retail Shop', '9876543210', 'www.myshop.com', '29ABCDE1234F1Z5', 'Main Market, City')
ON DUPLICATE KEY UPDATE id=id;

-- 2. Suppliers
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sup_code VARCHAR(50) UNIQUE,
    name VARCHAR(255),
    mobile VARCHAR(50),
    gst VARCHAR(50),
    address TEXT
);

-- 3. Categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) UNIQUE
);

-- 4. Inventory / Products
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(50) UNIQUE,
    product_name VARCHAR(255),
    category_id INT,
    supplier_code VARCHAR(50),
    quantity INT,
    unit VARCHAR(20),
    pieces_in_box INT DEFAULT 1,
    size VARCHAR(50),
    image VARCHAR(255),
    purchase_price DECIMAL(10,2),
    sale_price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- 5. Sales (Updated with gst_percent column)
CREATE TABLE IF NOT EXISTS sales (
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
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES inventory(id) ON DELETE CASCADE
);