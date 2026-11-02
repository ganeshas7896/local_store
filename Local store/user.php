<?php
require 'config.php';
$user = need('customer');
$orders = q(
    'SELECT o.id,o.total,o.status,o.created,s.name AS store_name
     FROM orders o JOIN stores s ON s.id=o.store_id
     WHERE o.customer_id=? ORDER BY o.id DESC LIMIT 5',
    [$user['id']]
);
$cartItems = cart_count();
head('User page');
?>
<h2>Welcome, <?= e($user['name']) ?></h2>
<div class="grid">
    <section class="panel">
        <h3>Browse stores</h3>
        <p>Find local stores and products near you.</p>
        <a class="btn" href="index.php">Browse stores</a>
    </section>
    <section class="panel">
        <h3>Your cart</h3>
        <p><?= $cartItems ?> item<?= $cartItems === 1 ? '' : 's' ?></p>
        <a class="btn" href="cart.php">Open cart</a>
    </section>
    <section class="panel">
        <h3>Your orders</h3>
        <p><?= count($orders) ?> recent order<?= count($orders) === 1 ? '' : 's' ?></p>
        <a class="btn" href="orders.php">View orders</a>
    </section>
</div>

<h3>Recent orders</h3>
<?php if (!$orders) { ?>
    <p class="notice-card">No orders yet. Browse local stores to get started.</p>
<?php } else { ?>
    <?php foreach ($orders as $order) { ?>
        <div class="panel">
            <b>#<?= (int)$order['id'] ?> · <?= e($order['store_name']) ?></b>
            <span class="st"><?= e(str_replace('_', ' ', $order['status'])) ?></span>
            <p>₹<?= e(money($order['total'])) ?> · <?= e($order['created']) ?></p>
        </div>
    <?php } ?>
    <a href="orders.php">All orders →</a>
<?php } ?>
<?php foot(); ?>
