<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Only use custom session path on local XAMPP to avoid "missing tmp" errors.
    // Live hosting will use the server's default session path.
    // (REMOTE_ADDR is missing when scripts run from the CLI.)
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (PHP_SAPI === 'cli' || $remoteAddr === '127.0.0.1' || $remoteAddr === '::1') {
        $sessionPath = __DIR__ . '/../sessions';
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0777, true);
        }
        session_save_path($sessionPath);
    }
    session_start();
}

const APP_NAME = 'Resource Enterprises';

$isLocalEnv = PHP_SAPI === 'cli'
    || ($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1' 
    || ($_SERVER['REMOTE_ADDR'] ?? '') === '::1' 
    || in_array(strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]), ['localhost', '127.0.0.1'], true);

if ($isLocalEnv && file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    require_once __DIR__ . '/config.php';
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    $localSetupUrl = 'setup_local_database.php';
    $isLocal = ($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1' || ($_SERVER['REMOTE_ADDR'] ?? '') === '::1' || str_contains(($_SERVER['HTTP_HOST'] ?? ''), 'localhost');
    echo "<div style='max-width:700px;margin:40px auto;padding:30px;background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.1);font-family:Arial,sans-serif;'>";
    echo "<h2 style='color:#5D100B;margin-top:0;'>🔌 Database Connection Failed</h2>";
    echo "<p style='color:#666;line-height:1.6;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    if ($isLocal) {
        echo "<div style='background:#fff8e1;padding:18px;border-left:4px solid #FFB300;border-radius:6px;margin:20px 0;'>";
        echo "<h3 style='margin:0 0 10px;color:#795548;'>💡 Local Setup Detected</h3>";
        echo "<p style='margin:0 0 12px;'>Run the local database setup script to create everything automatically:</p>";
        echo "<a href='$localSetupUrl' style='display:inline-block;background:#5D100B;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;font-weight:700;'>🚀 Run Local Setup</a>";
        echo "</div>";
    }
    echo "<div style='background:#f5f5f5;padding:18px;border-radius:6px;margin-top:20px;'>";
    echo "<h4 style='margin:0 0 10px;color:#333;'>Manual Steps:</h4>";
    echo "<ol style='margin:0;padding-left:20px;color:#666;line-height:1.8;'>";
    echo "<li>Start MySQL service (XAMPP/WAMP)</li>";
    echo "<li>Create database: <code style='background:#fff;padding:2px 6px;border-radius:4px;'>enterprise_db</code></li>";
    echo "<li>Run setup script or migrations</li>";
    echo "</ol></div></div>";
    exit(1);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h(null|string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function current_script(): string
{
    return basename($_SERVER['PHP_SELF'] ?? 'index.php');
}

function is_active_page(string $script): bool
{
    return current_script() === $script;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function get_flash(string $type): ?string
{
    if (!isset($_SESSION['flash'][$type])) {
        return null;
    }

    $message = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);
    return $message;
}

function csrf_token(): string
{
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function enforce_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Request rejected: invalid CSRF token.');
    }
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function is_admin(PDO $pdo): bool
{
    if (!is_logged_in()) {
        return false;
    }
    $user = current_user($pdo);
    return ($user['role'] ?? 'user') === 'admin';
}

function is_admin_panel(): bool
{
    return strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
}

function current_user_id(): ?int
{
    return is_logged_in() ? (int) $_SESSION['user_id'] : null;
}

function current_user(PDO $pdo): ?array
{
    $userId = current_user_id();
    if ($userId === null) {
        return null;
    }

    static $cache = [];
    if (!isset($cache[$userId])) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $cache[$userId] = $stmt->fetch() ?: null;
    }

    return $cache[$userId];
}

function require_login(): void
{
    if (is_logged_in()) {
        return;
    }

    $currentUrl = $_SERVER['REQUEST_URI'] ?? '/dashboard.php';
    redirect('login.php?redirect=' . urlencode($currentUrl));
}

function require_admin(PDO $pdo): void
{
    require_login();
    if (!is_admin($pdo)) {
        set_flash('error', 'Unauthorized access.');
        redirect(url('index.php'));
    }
}

function cart_data(): array
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    return $_SESSION['cart'];
}

function cart_count(): int
{
    return array_sum(array_map('intval', cart_data()));
}

function add_to_cart(int $productId, int $quantity): void
{
    if ($quantity < 1) {
        return;
    }

    $cart = cart_data();
    $existing = isset($cart[$productId]) ? (int) $cart[$productId] : 0;
    $cart[$productId] = $existing + $quantity;
    $_SESSION['cart'] = $cart;
}

function update_cart_item(int $productId, int $quantity): void
{
    $cart = cart_data();

    if ($quantity <= 0) {
        unset($cart[$productId]);
    } else {
        $cart[$productId] = $quantity;
    }

    $_SESSION['cart'] = $cart;
}

function remove_cart_item(int $productId): void
{
    $cart = cart_data();
    unset($cart[$productId]);
    $_SESSION['cart'] = $cart;
}

function clear_cart(): void
{
    $_SESSION['cart'] = [];
}

function load_cart_items(PDO $pdo): array
{
    $cart = cart_data();
    if ($cart === []) {
        return [];
    }

    $productIds = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare("SELECT id, name, slug, category, price, stock, image_url FROM products WHERE active = 1 AND id IN ($placeholders)");
    $stmt->execute(array_map('intval', $productIds));

    $products = [];
    foreach ($stmt->fetchAll() as $row) {
        $products[(int) $row['id']] = $row;
    }

    $items = [];
    foreach ($cart as $productId => $qty) {
        $productId = (int) $productId;
        $qty = max(1, (int) $qty);

        if (!isset($products[$productId])) {
            continue;
        }

        $product = $products[$productId];
        $maxStock = max(1, (int) $product['stock']);
        $qty = min($qty, $maxStock);

        $unitPrice = (float) $product['price'];
        $items[] = [
            'id' => $productId,
            'name' => $product['name'],
            'slug' => $product['slug'],
            'category' => $product['category'],
            'image_url' => $product['image_url'],
            'price' => $unitPrice,
            'quantity' => $qty,
            'line_total' => $unitPrice * $qty,
            'stock' => $maxStock,
        ];
    }

    return $items;
}

function cart_subtotal(array $items): float
{
    $sum = 0.0;
    foreach ($items as $item) {
        $sum += (float) $item['line_total'];
    }
    return $sum;
}

function get_active_currency(?PDO $pdoInstance = null): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $pdo;
    $db = $pdoInstance ?? ($pdo ?? null);

    if ($db instanceof PDO) {
        try {
            $stmt = $db->query('SELECT * FROM currencies WHERE is_default = 1 AND active = 1 LIMIT 1');
            $row = $stmt->fetch();
            if ($row) {
                $cached = $row;
                return $cached;
            }
        } catch (Throwable) {
            // Currencies table may not exist yet or connection issue
        }
    }

    $cached = [
        'code' => 'PKR',
        'name' => 'Pakistani Rupee',
        'symbol' => 'Rs. ',
        'symbol_position' => 'before',
        'decimal_places' => 0,
        'thousands_sep' => ',',
        'decimal_sep' => '.',
    ];
    return $cached;
}

function money(float $amount): string
{
    $curr = get_active_currency();
    $dec = (int)($curr['decimal_places'] ?? 2);
    $decSep = (string)($curr['decimal_sep'] ?? '.');
    $thouSep = (string)($curr['thousands_sep'] ?? ',');
    $symbol = (string)($curr['symbol'] ?? '$');
    $pos = (string)($curr['symbol_position'] ?? 'before');

    $formatted = number_format($amount, $dec, $decSep, $thouSep);
    if ($pos === 'after') {
        return $formatted . ' ' . $symbol;
    }
    return $symbol . $formatted;
}

function active_currency_symbol(): string
{
    $curr = get_active_currency();
    return (string)($curr['symbol'] ?? '$');
}

function active_currency_code(): string
{
    $curr = get_active_currency();
    return (string)($curr['code'] ?? 'PKR');
}

function upload_image(array $file): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $uploadDir = __DIR__ . '/../uploads/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $targetFile = $uploadDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return 'uploads/' . $filename;
    }

    return null;
}

function url(string $path): string
{
    if (str_contains($path, '://')) {
        return $path;
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($scriptName);
    
    // Normalize path separators
    $dir = str_replace('\\', '/', $dir);
    
    // If we are in the admin folder, we need to find the project root
    if (str_contains($dir, '/admin')) {
        $dir = substr($dir, 0, strpos($dir, '/admin'));
    }

    $basePath = rtrim($dir, '/');
    
    // Ensure the path starts with a single slash if it's not empty
    $path = ltrim($path, '/');
    
    return $basePath . '/' . $path;
}

function validate_redirect_target(string $target): string
{
    if ($target === '' || str_contains($target, '://')) {
        return 'dashboard.php';
    }

    if (!str_starts_with($target, '/')) {
        return $target;
    }

    return ltrim($target, '/');
}
