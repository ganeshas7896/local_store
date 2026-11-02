<?php
require 'config.php';
$user = need('customer');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = (int)($_POST['oid'] ?? 0);
    $stars = filter_var($_POST['stars'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    $comment = trim(input_string($_POST['comment'] ?? null));
    $order = q("SELECT id,store_id FROM orders WHERE id=? AND customer_id=? AND status='delivered'", [$orderId, $user['id']])[0] ?? null;
    if (!$order) {
        $error = 'Only your delivered orders can be rated.';
    } elseif ($stars === false || text_length($comment) > 255) {
        $error = 'Choose a rating from 1 to 5 and keep the review under 255 characters.';
    } else {
        q('INSERT IGNORE INTO ratings(order_id,store_id,customer_id,stars,comment) VALUES(?,?,?,?,?)', [
            $order['id'], $order['store_id'], $user['id'], $stars, $comment,
        ]);
        header('Location: orders.php');
        exit;
    }
}

head('My Orders');
?>
<h2>Orders</h2>
<?php if ($error) echo '<p class="err">' . e($error) . '</p>'; ?>
<?php $orders = q('SELECT o.*,s.name sn,(SELECT stars FROM ratings WHERE order_id=o.id) rt FROM orders o JOIN stores s ON s.id=o.store_id WHERE o.customer_id=? ORDER BY o.id DESC', [$user['id']]); ?>
<?php if (!$orders) { ?><p class="notice-card">No orders yet.</p><?php } ?>
<?php foreach ($orders as $order) { ?>
    <div class="panel">
        <b>#<?= (int)$order['id'] ?> <?= e($order['sn']) ?></b> &bull; ₹<?= e(money($order['total'])) ?>
        <span class="st"><?= e(str_replace('_', ' ', $order['status'])) ?></span><br>
        <small><?= e($order['created']) ?></small>
        <?php foreach (q('SELECT * FROM order_items WHERE order_id=?', [$order['id']]) as $item) { ?>
            <div><?= e($item['name']) ?> × <?= (int)$item['qty'] ?> <span class="muted">· ₹<?= e(money((float)$item['price'] * (int)$item['qty'])) ?></span></div>
        <?php } ?>
        <p class="payment-summary"><?= e($order['payment_status'] === 'paid' ? 'Paid' : 'Cash on delivery') ?></p>
        <?php if ($order['status'] === 'delivered') { ?>
            <?php if ($order['rt']) { ?>
                <p class="stars"><?= str_repeat('★', (int)$order['rt']) ?> Thanks for rating!</p>
            <?php } else { ?>
                <form method="post">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="oid" value="<?= (int)$order['id'] ?>">
                    <label>Rate<select name="stars"><?php for ($stars = 5; $stars > 0; $stars--) echo '<option value="' . $stars . '">' . $stars . ' ★</option>'; ?></select></label>
                    <input name="comment" maxlength="255" placeholder="Write a review">
                    <button type="submit">Submit rating</button>
                </form>
            <?php } ?>
        <?php } ?>
    </div>
<?php } ?>
<?php foot(); ?>
