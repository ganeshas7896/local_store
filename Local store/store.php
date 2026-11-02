<?php
require 'config.php';
$id = (int)($_GET['id'] ?? 0);
$store = q('SELECT * FROM stores WHERE id=?', [$id])[0] ?? null;
if (!$store) {
    http_response_code(404);
    exit('Store not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $user = need('customer');
    $productId = (int)($_POST['pid'] ?? 0);
    $product = q('SELECT id FROM products WHERE id=? AND store_id=? AND active=1', [$productId, $id])[0] ?? null;
    if (!$product) {
        http_response_code(404);
        exit('This product is no longer available.');
    }
    $cart = $_SESSION['cart'] ?? ['items' => []];
    if ((int)($cart['store'] ?? 0) !== $id) {
        $cart = ['store' => $id, 'items' => []];
    }
    $cart['store'] = $id;
    $cart['items'] = is_array($cart['items'] ?? null) ? $cart['items'] : [];
    if (!array_key_exists($productId, $cart['items']) && count($cart['items']) >= 100) {
        http_response_code(400);
        exit('A cart can contain up to 100 different products.');
    }
    $quantity = (int)($cart['items'][$productId] ?? 0);
    if ($quantity >= 99) {
        http_response_code(400);
        exit('You can add up to 99 of each product to the cart.');
    }
    $cart['items'][$productId] = $quantity + 1;
    $_SESSION['cart'] = $cart;
    header('Location: store.php?id=' . $id . '&added=1');
    exit;
}

$location = $_SESSION['loc'] ?? null;
$distance = $location ? dist($location[0], $location[1], (float)$store['lat'], (float)$store['lng']) : null;
$reviews = q('SELECT r.*,u.name FROM ratings r JOIN users u ON u.id=r.customer_id WHERE r.store_id=? ORDER BY r.id DESC LIMIT 10', [$id]);
head($store['name']);
?>
<div class="panel">
    <h2><?= e($store['name']) ?> <span class="badge"><?= e($store['category']) ?></span></h2>
    <p><?= e($store['description']) ?><br>📍 <?= e($store['address']) ?><?= $distance !== null ? ' &bull; ' . number_format($distance, 1) . ' km away' : '' ?></p>
    <?php if ((int)$store['accept_cod'] !== 1) echo '<p class="notice-card">Orders paused</p>'; ?>
    <?php if ($distance !== null && $distance > RADIUS) echo '<p class="err">Outside your ' . e(RADIUS) . ' km delivery area.</p>'; ?>
    <?php if (isset($_GET['added'])) echo '<p class="ok">Added to cart</p>'; ?>
</div>
<h3>Products</h3>
<?php $products = q('SELECT * FROM products WHERE store_id=? AND active=1', [$id]); ?>
<?php if (!$products) { ?><p class="notice-card">No products yet.</p><?php } ?>
<div class="grid">
    <?php foreach ($products as $product) { ?>
        <div class="card">
            <img src="<?= img($product['image']) ?>" alt="<?= e($product['name']) ?>">
            <div class="product-details">
                <h4><?= e($product['name']) ?></h4>
                <strong class="price">₹<?= e(money($product['price'])) ?></strong>
            </div>
            <?php if ((int)$store['accept_cod'] === 1) { ?>
            <form method="post">
                <?php csrf_field(); ?>
                <input type="hidden" name="pid" value="<?= (int)$product['id'] ?>">
                <button type="submit">Add to cart</button>
            </form>
            <?php } ?>
        </div>
    <?php } ?>
</div>
<?php if ($reviews) { ?><h3>Reviews</h3><?php } ?>
<?php foreach ($reviews as $review) { ?>
    <div class="panel"><span class="stars"><?= str_repeat('★', (int)$review['stars']) ?></span> <?= e($review['name']) ?><br><?= e($review['comment']) ?></div>
<?php } ?>
<?php foot(); ?>
