<?php
require 'config.php';
$user = need('customer');
$cart = $_SESSION['cart'] ?? ['items' => []];
$cartItems = is_array($cart['items'] ?? null) ? $cart['items'] : [];
$cartTooLarge = count($cartItems) > 100;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear'])) {
    verify_csrf();
    unset($_SESSION['cart']);
    header('Location: cart.php');
    exit;
}

$items = [];
$totalCents = 0;
$orderTotalLimitCents = 999999999999;
$storeId = (int)($cart['store'] ?? 0);
foreach ($cartItems as $productId => $quantity) {
    $productId = (int)$productId;
    $quantity = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
    if ($productId < 1 || $quantity === false) {
        continue;
    }
    $product = q('SELECT * FROM products WHERE id=? AND store_id=? AND active=1', [$productId, $storeId])[0] ?? null;
    if ($product && count($items) < 100) {
        $product['qty'] = $quantity;
        $items[] = $product;
        $totalCents += (int)round((float)$product['price'] * 100) * $quantity;
    }
}
$total = number_format($totalCents / 100, 2, '.', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['clear'])) {
    verify_csrf();
    $address = trim(input_string($_POST['address'] ?? null));
    $latitude = $_POST['lat'] ?? '';
    $longitude = $_POST['lng'] ?? '';
    $store = q('SELECT * FROM stores WHERE id=?', [$storeId])[0] ?? null;

    if ($cartTooLarge) {
        $error = 'A cart can contain up to 100 different products. Clear the cart and add fewer products.';
    } elseif (!$items || !$store) {
        $error = 'Your cart has no available products. Please add products again.';
    } elseif ((int)$store['accept_cod'] !== 1) {
        $error = 'This store is not currently accepting orders. Please choose another store.';
    } elseif ($address === '' || text_length($address) > 500) {
        $error = 'Enter a delivery address of no more than 500 characters.';
    } elseif ($totalCents > $orderTotalLimitCents) {
        $error = 'Your order total exceeds the maximum supported amount. Remove some products and try again.';
    } elseif (!valid_location($latitude, $longitude)) {
        $error = 'Please capture a valid delivery location.';
    } elseif (dist((float)$latitude, (float)$longitude, (float)$store['lat'], (float)$store['lng']) > RADIUS) {
        $error = 'Store is beyond ' . RADIUS . ' km from your delivery location.';
    } else {
        global $db;
        try {
            $db->begin_transaction();
            $orderId = q('INSERT INTO orders(customer_id,store_id,total,address,lat,lng,payment_method,payment_status) VALUES(?,?,?,?,?,?,?,?)', [
                $user['id'], $store['id'], $total, $address, (string)$latitude, (string)$longitude, 'cash_on_delivery', 'pending',
            ]);
            foreach ($items as $item) {
                q('INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES(?,?,?,?,?)', [
                    $orderId, $item['id'], $item['name'], $item['price'], $item['qty'],
                ]);
            }
            $db->commit();
            unset($_SESSION['cart']);
            header('Location: orders.php');
            exit;
        } catch (Throwable $exception) {
            $db->rollback();
            throw $exception;
        }
    }
}

head('Cart');
?>
<h2>Your cart</h2>
<?php if ($error && !$items) echo '<p class="err">' . e($error) . '</p>'; ?>
<?php if (!$items) { ?>
    <p>Cart is empty.</p>
    <?php if ($cartItems) { ?>
        <form method="post">
            <?php csrf_field(); ?>
            <button type="submit" name="clear" value="1" class="danger">Clear unavailable items</button>
        </form>
    <?php } ?>
<?php } else { ?>
    <?php if ($error) echo '<p class="err">' . e($error) . '</p>'; ?>
    <table>
        <thead><tr><th>Item</th><th>Qty</th><th>Price</th></tr></thead><tbody>
        <?php foreach ($items as $item) { ?>
            <tr><td><?= e($item['name']) ?></td><td><?= (int)$item['qty'] ?></td><td>₹<?= e(money((float)$item['price'] * $item['qty'])) ?></td></tr>
        <?php } ?>
        </tbody><tfoot><tr><th colspan="2">Total</th><th class="price">₹<?= e(money($total)) ?></th></tr></tfoot>
    </table>
    <form method="post">
        <?php csrf_field(); ?>
        <button type="submit" name="clear" value="1" class="danger">Clear cart</button>
    </form>
    <form class="box" method="post">
        <h3>Delivery</h3>
        <?php csrf_field(); ?>
        <textarea name="address" maxlength="500" required placeholder="House no, street, landmark"></textarea>
        <?php locfields(); ?>
        <p class="payment-summary">Pay by cash on delivery</p>
        <button type="submit" class="checkout-button">Place order · ₹<?= e(money($total)) ?></button>
    </form>
<?php } ?>
<?php foot(); ?>
