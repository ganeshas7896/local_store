<?php
$localSettings = [];
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $loadedSettings = require $localConfigPath;
    if (!is_array($loadedSettings)) {
        error_log('LocalStore config.local.php must return an array.');
        http_response_code(500);
        exit('The site is not configured correctly. Please contact the administrator.');
    }
    $localSettings = $loadedSettings;
}

function setting($name, $default = '')
{
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return $value;
    }
    global $localSettings;
    return isset($localSettings[$name]) && is_string($localSettings[$name])
        ? $localSettings[$name]
        : $default;
}

$appEnvironment = strtolower(trim(setting('APP_ENV')));
if (!extension_loaded('mysqli') || !extension_loaded('mysqlnd')) {
    error_log('LocalStore requires PHP mysqli with mysqlnd enabled.');
    http_response_code(500);
    exit('The server is missing the PHP MySQL extension. Please contact the administrator.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off';
$secureCookie = $https || $appEnvironment === 'production';
if ($appEnvironment === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $secureCookie,
    'samesite' => 'Lax',
]);
session_start();

$dbHost = setting('LOCALSTORE_DB_HOST', $appEnvironment === 'production' ? '' : 'sql207.infinityfree.com');
$dbName = setting('LOCALSTORE_DB_NAME', $appEnvironment === 'production' ? '' : 'if0_43145400_localstore');
$dbUser = setting('LOCALSTORE_DB_USER', $appEnvironment === 'production' ? '' : 'if0_43145400');
$dbPass = setting('LOCALSTORE_DB_PASS', 'localstore1234');
if (
    $dbHost === ''
    || $dbName === ''
    || $dbUser === ''
    || ($appEnvironment === 'production' && (strtolower($dbUser) === 'root' || $dbPass === ''))
) {
    error_log('LocalStore database configuration is incomplete or unsafe for the current environment.');
    http_response_code(500);
    exit('The site is not configured correctly. Please contact the administrator.');
}

try {
    $db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $db->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('LocalStore database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The site is temporarily unavailable. Please try again later.');
}

const RADIUS = 5;
const CATS = [
    'Grocery & Daily Needs' => ['Fresh Mart', 'Daily Needs Store', 'Green Basket', 'Smart Grocery', 'Family Supermarket', 'Organic Store', 'Rice & Grains Shop', 'Fruits & Vegetables Shop', 'Spice Store', 'Wholesale Grocery'],
    'Food & Drinks' => ['Fast Food Shop', 'Restaurant', 'Tiffin Center', 'Bakery', 'Cake Shop', 'Sweet Shop', 'Tea & Coffee Shop', 'Juice Shop', 'Ice Cream Shop', 'Pizza Shop', 'Biryani Shop', 'Cloud Kitchen'],
    'Fashion' => ["Men's Clothing", "Women's Clothing", 'Kids Wear', 'Saree Shop', 'Fashion Boutique', 'Footwear Shop', 'Bag Shop', 'Watch Shop', 'Jewellery Shop', 'Sportswear Shop'],
    'Health' => ['Medical Store', 'Pharmacy', 'Health & Wellness Store', 'Surgical Supplies', 'Optical Shop', 'Baby Care Store', 'Personal Care Shop'],
    'Electronics' => ['Mobile Phone Shop', 'Electronics Store', 'Computer Shop', 'Laptop Store', 'Mobile Accessories', 'Home Appliances', 'Electrical Supplies', 'Repair Service Shop'],
    'Home' => ['Furniture Store', 'Kitchenware Shop', 'Home Decor Store', 'Hardware Store', 'Paint Shop', 'Lighting Store', 'Plastic Goods Shop', 'Mattress Shop', 'Home Essentials'],
    'Beauty' => ['Cosmetics Shop', 'Beauty Products Store', 'Salon', 'Barber Shop', 'Perfume Shop', 'Skin Care Store', 'Hair Care Shop'],
    'Books & Gifts' => ['Book Store', 'Stationery Shop', 'School Supplies', 'Office Supplies', 'Printing Shop', 'Gift Shop', 'Toy Shop', 'Art Supplies'],
    'Automobile' => ['Bike Spare Parts', 'Car Accessories', 'Tyre Shop', 'Automobile Store', 'Bike Repair Shop', 'Car Repair Shop', 'Battery Shop', 'Petrol & Lubricants Store'],
    'Others' => ['Pet Shop', 'Flower Shop', 'Sports Shop', 'Pooja Items Store', 'Agricultural Supplies', 'Water Can Delivery', 'Laundry Service', 'Tailoring Shop', 'Meat & Fish Shop', 'Dairy Products Store', 'Ice & Water Supplier'],
];

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function input_string($value)
{
    return is_string($value) ? $value : '';
}

function text_length($value)
{
    $length = preg_match_all('/./us', (string)$value);
    return $length === false ? PHP_INT_MAX : $length;
}

function money($amount)
{
    $formatted = number_format((float)$amount, 2, '.', '');
    [$whole, $decimal] = explode('.', $formatted, 2);
    if (strlen($whole) > 3) {
        $lastThree = substr($whole, -3);
        $prefix = substr($whole, 0, -3);
        $prefix = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $prefix);
        $whole = $prefix . ',' . $lastThree;
    }
    return $whole . '.' . $decimal;
}

function csrf_field()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function verify_csrf()
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Your session has expired or the request could not be verified. Reload the page and try again.');
    }
}

function me()
{
    return $_SESSION['u'] ?? null;
}

function cart_count()
{
    $items = $_SESSION['cart']['items'] ?? [];
    if (!is_array($items)) {
        return 0;
    }
    $count = 0;
    foreach ($items as $quantity) {
        if (is_scalar($quantity) && is_numeric($quantity)) {
            $count += max(0, min(99, (int)$quantity));
        }
    }
    return $count;
}

function need($role = null)
{
    $user = me();
    if (!$user || ($role !== null && $user['role'] !== $role)) {
        header('Location: login.php' . ($role === 'owner' ? '?role=owner' : ($role === 'customer' ? '?role=customer' : '')));
        exit;
    }
    return $user;
}

function q($sql, $params = [])
{
    global $db;
    $statement = $db->prepare($sql);
    if ($params) {
        $statement->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $statement->execute();
    $result = $statement->get_result();
    if ($result === false) {
        $id = $db->insert_id;
        $statement->close();
        return $id;
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    $statement->close();
    return $rows;
}

function img($path)
{
    if (!$path) {
        return 'logo.png';
    }
    if (preg_match('~^https://~i', $path)) {
        return e($path);
    }
    $filename = basename($path);
    if (preg_match('~^upload_[a-f0-9]{32}\.(?:jpg|png|webp|gif)$~i', $filename)) {
        return rawurlencode($filename);
    }
    return 'logo.png';
}

function saveimg($url)
{
    if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            exit('The image upload failed. Check the host upload limit and try a smaller image.');
        }
        if ($_FILES['file']['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            exit('Image uploads are limited to 5 MB.');
        }

        $temporaryFile = $_FILES['file']['tmp_name'];
        $imageInfo = @getimagesize($temporaryFile);
        $mime = $imageInfo['mime'] ?? '';
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $fileInfo->file($temporaryFile);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!$imageInfo || !isset($extensions[$mime]) || $detectedMime !== $mime
            || $imageInfo[0] < 1 || $imageInfo[1] < 1
            || $imageInfo[0] > 20000 || $imageInfo[1] > 20000
            || $imageInfo[0] * $imageInfo[1] > 40000000) {
            http_response_code(400);
            exit('Please upload a valid JPG, PNG, WebP, or GIF image no larger than 40 megapixels.');
        }

        $filename = 'upload_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($temporaryFile, __DIR__ . '/' . $filename)) {
            error_log('LocalStore could not move an uploaded image.');
            http_response_code(500);
            exit('The image could not be saved. Please try again.');
        }
        return $filename;
    }

    $url = trim(input_string($url));
    if ($url === '') {
        return '';
    }
    $parts = parse_url($url);
    if (strlen($url) > 2048 || !$parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
        http_response_code(400);
        exit('Image links must be valid HTTPS URLs.');
    }
    return $url;
}

function valid_location($latitude, $longitude)
{
    return is_numeric($latitude) && is_numeric($longitude)
        && (float)$latitude >= -90 && (float)$latitude <= 90
        && (float)$longitude >= -180 && (float)$longitude <= 180;
}

function dist($a, $b, $c, $d)
{
    $r = M_PI / 180;
    $value = cos($a * $r) * cos($c * $r) * cos(($d - $b) * $r) + sin($a * $r) * sin($c * $r);
    return 6371 * acos(max(-1.0, min(1.0, $value)));
}

function head($title = 'LocalStore')
{
    $user = me();
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title><link rel="stylesheet" href="style.css"><script>try{const savedTheme=localStorage.getItem("localstore-theme");document.documentElement.dataset.theme=savedTheme==="dark"||savedTheme==="light"?savedTheme:(matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light")}catch(error){}</script></head><body><header><a href="index.php" class="brand"><img src="logo.png" alt="LocalStore"><b>LocalStore</b></a><nav aria-label="Main navigation"><button type="button" class="theme-button" id="theme-toggle" aria-label="Switch to dark mode" title="Switch to dark mode"><span aria-hidden="true">☾</span></button><button type="button" class="history-button" data-history="back" aria-label="Go back" title="Go back">←</button><button type="button" class="history-button" data-history="forward" aria-label="Go forward" title="Go forward">→</button>';
    if (!$user) {
        $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($page === 'login.php') {
            $role = input_string($_GET['role'] ?? null);
            if ($role === 'customer') {
                echo '<a href="login.php?role=owner">Admin sign in</a>';
            } elseif ($role === 'owner') {
                echo '<a href="login.php?role=customer">User sign in</a>';
            }
        } elseif ($page === 'register.php') {
            echo '<a href="login.php">Sign in</a>';
        } elseif ($page !== 'index.php') {
            echo '<a href="login.php?role=customer">User sign in</a><a href="login.php?role=owner">Admin sign in</a><a href="register.php">Create account</a>';
        } else {
            echo '<a href="register.php">Create account</a>';
        }
    } elseif ($user['role'] === 'owner') {
        echo '<a href="admin.php">Admin page</a>';
        echo '<form method="post" action="logout.php" class="nav-logout">';
        csrf_field();
        echo '<button type="submit">Logout</button></form>';
    } else {
        echo '<a href="user.php">User page</a><a href="index.php">Browse stores</a><a href="cart.php">Cart (' . cart_count() . ')</a><a href="orders.php">Orders</a>';
        echo '<form method="post" action="logout.php" class="nav-logout">';
        csrf_field();
        echo '<button type="submit">Logout</button></form>';
    }
    echo '</nav></header><main>';
}

function foot()
{
    echo '</main><footer>LocalStore</footer><script src="app.js"></script></body></html>';
}

function locfields()
{
    echo '<input type="hidden" name="lat" class="lat"><input type="hidden" name="lng" class="lng"><button type="button" class="ghost" onclick="getLoc()">📍 Use my current location</button> <small class="locmsg"></small>';
}
