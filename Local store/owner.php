<?php
require 'config.php';
$user = need('owner');
$stores = q('SELECT id,name FROM stores WHERE owner_id=? ORDER BY name,id', [$user['id']]);
$newStoreMode = input_string($_GET['new'] ?? null) === '1';
$creatingStore = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_store']);
$requestedStoreId = (int)($_POST['selected_store'] ?? $_GET['store_id'] ?? 0);
$store = null;
if (!$newStoreMode && !$creatingStore) {
    foreach ($stores as $availableStore) {
        if ((int)$availableStore['id'] === $requestedStoreId) {
            $store = q('SELECT * FROM stores WHERE id=? AND owner_id=?', [$requestedStoreId, $user['id']])[0] ?? null;
            break;
        }
    }
    if (!$store && $stores && $requestedStoreId === 0) {
        $store = q('SELECT * FROM stores WHERE id=? AND owner_id=?', [$stores[0]['id'], $user['id']])[0] ?? null;
    }
}
$error = '';
$categories = array_merge(...array_values(CATS));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['create_store']) || isset($_POST['store'])) {
        $creating = isset($_POST['create_store']);
        $name = trim(input_string($_POST['name'] ?? null));
        $category = input_string($_POST['category'] ?? null);
        $description = trim(input_string($_POST['description'] ?? null));
        $address = trim(input_string($_POST['address'] ?? null));
        $latitude = trim(input_string($_POST['lat'] ?? null));
        $longitude = trim(input_string($_POST['lng'] ?? null));

        if ($name === '' || text_length($name) > 120 || !in_array($category, $categories, true)
            || text_length($description) > 10000 || $address === '' || text_length($address) > 255) {
            $error = 'Check the store name, type, description, and address.';
        } elseif (!$creating && !$store) {
            $error = 'Select one of your stores before editing it.';
        } elseif ($latitude === '' && $longitude === '' && $store && !$creating) {
            $latitude = (string)$store['lat'];
            $longitude = (string)$store['lng'];
        } elseif (!valid_location($latitude, $longitude)) {
            $error = 'Capture a valid store location before saving.';
        }

        if ($error === '') {
            $image = saveimg(input_string($_POST['url'] ?? null));
            $acceptCod = input_string($_POST['accept_cod'] ?? null) === '1' ? 1 : 0;
            if ($creating) {
                $newStoreId = q('INSERT INTO stores(owner_id,name,category,description,address,lat,lng,image,accept_cod) VALUES(?,?,?,?,?,?,?,?,?)', [
                    $user['id'], $name, $category, $description, $address, $latitude, $longitude, $image, $acceptCod,
                ]);
                header('Location: admin.php?store_id=' . (int)$newStoreId);
            } else {
                q("UPDATE stores SET name=?,category=?,description=?,address=?,lat=?,lng=?,image=IF(?='',image,?),accept_cod=? WHERE id=? AND owner_id=?", [
                    $name, $category, $description, $address, $latitude, $longitude, $image, $image, $acceptCod, $store['id'], $user['id'],
                ]);
                header('Location: admin.php?store_id=' . (int)$store['id']);
            }
            exit;
        }
    } elseif ($store && isset($_POST['product'])) {
        $name = trim(input_string($_POST['pname'] ?? null));
        $price = input_string($_POST['price'] ?? null);
        if ($name === '' || text_length($name) > 120 || !is_numeric($price) || (float)$price <= 0 || (float)$price > 99999999.99) {
            $error = 'Enter a product name and a price greater than zero.';
        } else {
            q('INSERT INTO products(store_id,name,price,image) VALUES(?,?,?,?)', [
                $store['id'], $name, $price, saveimg(input_string($_POST['url'] ?? null)),
            ]);
            header('Location: admin.php?store_id=' . (int)$store['id']);
            exit;
        }
    } elseif ($store && isset($_POST['del'])) {
        q('UPDATE products SET active=0 WHERE id=? AND store_id=?', [(string)(int)$_POST['del'], $store['id']]);
        header('Location: admin.php?store_id=' . (int)$store['id']);
        exit;
    } elseif ($store && isset($_POST['status'])) {
        $orderId = (string)(int)($_POST['oid'] ?? 0);
        $order = q('SELECT id,status FROM orders WHERE id=? AND store_id=?', [$orderId, $store['id']])[0] ?? null;
        $allowed = [
            'placed' => ['preparing', 'cancelled'],
            'preparing' => ['out_for_delivery', 'cancelled'],
            'out_for_delivery' => ['delivered'],
            'delivered' => [],
            'cancelled' => [],
        ];
        $nextStatus = input_string($_POST['status'] ?? null);
        if (!$order || !in_array($nextStatus, $allowed[$order['status']] ?? [], true)) {
            $error = 'That order status change is not allowed.';
        } else {
            q('UPDATE orders SET status=? WHERE id=? AND store_id=?', [$nextStatus, $orderId, $store['id']]);
            header('Location: admin.php?store_id=' . (int)$store['id']);
            exit;
        }
    } elseif ($store && isset($_POST['mark_paid'])) {
        $orderId = (string)(int)($_POST['oid'] ?? 0);
        $order = q("SELECT id,payment_method,payment_status,status FROM orders WHERE id=? AND store_id=?", [$orderId, $store['id']])[0] ?? null;
        if (!$order || $order['payment_method'] !== 'cash_on_delivery' || $order['status'] !== 'delivered' || $order['payment_status'] !== 'pending') {
            $error = 'Only delivered cash-on-delivery orders can be marked as collected.';
        } else {
            q("UPDATE orders SET payment_status='paid' WHERE id=? AND store_id=? AND payment_status='pending'", [$orderId, $store['id']]);
            header('Location: admin.php?store_id=' . (int)$store['id']);
            exit;
        }
    }
}

head('Stores');
?>
<h2>Admin dashboard</h2>
<?php if ($error) echo '<p class="err">' . e($error) . '</p>'; ?>
<?php if ($stores) { ?>
    <form class="panel store-picker" method="get">
        <label for="store_id"><b>Store</b></label>
        <select id="store_id" name="store_id" onchange="this.form.submit()">
            <?php foreach ($stores as $availableStore) { ?>
                <option value="<?= (int)$availableStore['id'] ?>"<?= $store && (int)$store['id'] === (int)$availableStore['id'] ? ' selected' : '' ?>><?= e($availableStore['name']) ?></option>
            <?php } ?>
        </select>
        <noscript><button type="submit">Choose store</button></noscript>
    </form>
    <p><a class="btn" href="admin.php?new=1">+ Add store</a></p>
<?php } ?>

<form class="box" method="post" enctype="multipart/form-data">
    <h3><?= $store ? 'Edit store' : 'New store' ?></h3>
    <?php csrf_field(); ?>
    <?php if ($store) { ?>
        <input type="hidden" name="selected_store" value="<?= (int)$store['id'] ?>">
        <input type="hidden" name="store" value="1">
    <?php } else { ?>
        <input type="hidden" name="create_store" value="1">
    <?php } ?>
    Store name<input name="name" maxlength="120" required value="<?= e($store['name'] ?? '') ?>">
    Shop type<select name="category" required><?php foreach (CATS as $group => $list) { echo '<optgroup label="' . e($group) . '">'; foreach ($list as $category) echo '<option value="' . e($category) . '"' . (($store['category'] ?? '') === $category ? ' selected' : '') . '>' . e($category) . '</option>'; echo '</optgroup>'; } ?></select>
    Description<textarea name="description" maxlength="10000"><?= e($store['description'] ?? '') ?></textarea>
    Address<input name="address" maxlength="255" required value="<?= e($store['address'] ?? '') ?>">
    <label class="field-label" for="accept_cod">Orders</label>
    <select id="accept_cod" name="accept_cod" required>
        <option value="1"<?= (int)($store['accept_cod'] ?? 1) === 1 ? ' selected' : '' ?>>Accepting</option>
        <option value="0"<?= isset($store['accept_cod']) && (int)$store['accept_cod'] === 0 ? ' selected' : '' ?>>Paused</option>
    </select>
    Store image
    <div class="image-options">
        <select onchange="toggleImg(this)"><option value="url">Image URL</option><option value="file">Upload from device</option></select>
        <div class="image-url"><input name="url" type="url" maxlength="2048" placeholder="https://..."></div>
        <div class="image-file" style="display:none"><input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif"></div>
    </div>
    <?php locfields(); ?>
    <?php if ($store) echo '<br><small>Location saved: ' . e(round((float)$store['lat'], 4)) . ', ' . e(round((float)$store['lng'], 4)) . ' (tap button to update)</small>'; ?>
    <br><br><button type="submit"><?= $store ? 'Save store changes' : 'Create store' ?></button>
</form>

<?php if ($store) { ?>
    <form class="box" method="post" enctype="multipart/form-data">
        <h3>Add product to <?= e($store['name']) ?></h3>
        <?php csrf_field(); ?>
        <input type="hidden" name="selected_store" value="<?= (int)$store['id'] ?>">
        <input type="hidden" name="product" value="1">
        Name<input name="pname" maxlength="120" required>
        Price (₹)<input type="number" min="0.01" max="99999999.99" step="0.01" name="price" required>
        Image
        <div class="image-options">
            <select onchange="toggleImg(this)"><option value="url">Image URL</option><option value="file">Upload from device</option></select>
            <div class="image-url"><input name="url" type="url" maxlength="2048" placeholder="https://..."></div>
            <div class="image-file" style="display:none"><input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif"></div>
        </div>
        <button type="submit">Add product</button>
    </form>
    <h3>Products</h3>
    <?php $products = q('SELECT * FROM products WHERE store_id=? AND active=1', [$store['id']]); ?>
    <?php if (!$products) { ?><p class="notice-card">No products yet.</p><?php } ?>
    <div class="grid">
        <?php foreach ($products as $product) { ?>
            <div class="card">
                <img src="<?= img($product['image']) ?>" alt="<?= e($product['name']) ?>">
                <h4><?= e($product['name']) ?></h4><strong class="price">₹<?= e(money($product['price'])) ?></strong>
                <form method="post">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="selected_store" value="<?= (int)$store['id'] ?>">
                    <input type="hidden" name="del" value="<?= (int)$product['id'] ?>">
                    <button type="submit" class="danger" onclick="return confirm('Remove this product?')">Remove</button>
                </form>
            </div>
        <?php } ?>
    </div>
    <h3>Orders</h3>
    <?php $orders = q('SELECT o.*,u.name,u.phone FROM orders o JOIN users u ON u.id=o.customer_id WHERE o.store_id=? ORDER BY o.id DESC', [$store['id']]); ?>
    <?php if (!$orders) { ?><p class="notice-card">No orders yet.</p><?php } ?>
    <?php foreach ($orders as $order) { ?>
        <div class="panel">
            <b>#<?= (int)$order['id'] ?></b> <?= e($order['name']) ?> (<?= e($order['phone']) ?>) &bull; ₹<?= e(money($order['total'])) ?><br>
            <span class="payment-summary">Cash on Delivery · <?= e($order['payment_status'] === 'paid' ? 'Collected' : 'Payment due') ?></span><br>
            <?= e($order['address']) ?>
            <a target="_blank" rel="noopener noreferrer" href="https://maps.google.com/?q=<?= rawurlencode($order['lat'] . ',' . $order['lng']) ?>">Map</a><br>
            <?php foreach (q('SELECT * FROM order_items WHERE order_id=?', [$order['id']]) as $item) echo e($item['name']) . ' × ' . (int)$item['qty'] . '; '; ?>
            <form method="post">
                <?php csrf_field(); ?>
                <input type="hidden" name="selected_store" value="<?= (int)$store['id'] ?>">
                <input type="hidden" name="oid" value="<?= (int)$order['id'] ?>">
                <select name="status" style="width:auto">
                    <?php foreach (['placed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'] as $status) echo '<option value="' . e($status) . '"' . ($order['status'] === $status ? ' selected' : '') . '>' . e(str_replace('_', ' ', $status)) . '</option>'; ?>
                </select>
                <button type="submit" name="update_status" value="1">Update</button>
            </form>
            <?php if ($order['status'] === 'delivered' && $order['payment_method'] === 'cash_on_delivery' && $order['payment_status'] === 'pending') { ?>
                <form method="post">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="selected_store" value="<?= (int)$store['id'] ?>">
                    <input type="hidden" name="oid" value="<?= (int)$order['id'] ?>">
                    <button type="submit" name="mark_paid" value="1">Mark cash as collected</button>
                </form>
            <?php } ?>
        </div>
    <?php } ?>
<?php } ?>
<?php foot(); ?>
