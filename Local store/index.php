<?php
require 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $latitude = $_POST['lat'] ?? '';
    $longitude = $_POST['lng'] ?? '';
    if (!valid_location($latitude, $longitude)) {
        http_response_code(400);
        exit('A valid location is required to search nearby stores.');
    }
    $_SESSION['loc'] = [(float)$latitude, (float)$longitude];
    $category = input_string($_POST['cat'] ?? null);
    $searchTerm = trim(input_string($_POST['s'] ?? null));
    header('Location: index.php?' . http_build_query(['cat' => $category, 's' => $searchTerm]));
    exit;
}
$loc = $_SESSION['loc'] ?? null;
$cat = is_string($_GET['cat'] ?? null) ? $_GET['cat'] : '';
$search = is_string($_GET['s'] ?? null) ? trim($_GET['s']) : '';
$stores = [];
if ($loc) {
    $sql = 'SELECT s.*,(SELECT ROUND(AVG(stars),1) FROM ratings WHERE store_id=s.id) rate,(SELECT COUNT(*) FROM ratings WHERE store_id=s.id) rc,(6371*acos(GREATEST(-1,LEAST(1,cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))))) dist FROM stores s WHERE 1';
    $params = [$loc[0], $loc[1], $loc[0]];
    if ($cat !== '') {
        $sql .= ' AND category=?';
        $params[] = $cat;
    }
    if ($search !== '') {
        $sql .= ' AND (name LIKE ? OR category LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    $stores = q($sql . ' HAVING dist<=' . RADIUS . ' ORDER BY dist', $params);
}
head('LocalStore');
?>
<section class="hero">
<div class="hero-copy"><h1>Everything local.<br><span>Close by.</span></h1><p>Shop nearby for daily essentials, gifts, and more.</p><span class="delivery-pill">◎ &nbsp; Stores within <?= RADIUS ?> km</span></div>
<?php if (!me()) { ?>
<div class="role-entry"><a class="btn" href="login.php?role=customer">User sign in <span>→</span></a><a class="btn owner-entry" href="login.php?role=owner">Admin sign in</a></div>
<?php } ?>
<form class="nearby-form" id="store-search" method="post" onsubmit="return go()"><?php csrf_field(); ?><input type="hidden" id="lat" name="lat" class="lat"><input type="hidden" id="lng" name="lng" class="lng">
<select id="cat" name="cat"><option value="">All categories</option><?php foreach(CATS as $g=>$l){echo "<optgroup label='".e($g)."'>";foreach($l as $c)echo '<option value="'.e($c).'"'.($c==$cat?' selected':'').'>'.e($c).'</option>';echo '</optgroup>';}?></select>
<input id="s" name="s" maxlength="100" placeholder="Search stores or categories" value="<?=e($search)?>"><button type="submit">Find nearby <span>→</span></button><small class="locmsg"></small></form></section>
<section class="discover-heading"><h2><?= $loc ? 'Nearby stores' : 'Find local stores' ?></h2><?php if($loc) echo '<span class="result-count">' . count($stores) . ' stores</span>'; ?></section>
<?php if(!$loc)echo '<p class="notice-card ok">Share your location to find nearby stores.</p>';elseif(!$stores)echo '<p class="notice-card">No stores found. Try another search.</p>';?>
<div class="grid store-grid"><?php foreach($stores as $x){?><article class="card store-card"><a class="store-image" href="store.php?id=<?=(int)$x['id']?>"><img src="<?=img($x['image'])?>" alt="<?=e($x['name'])?>"><span class="distance-tag"><?=number_format((float)$x['dist'],1)?> km</span></a><div class="store-card-body"><span class="badge"><?=e($x['category'])?></span><h3><?=e($x['name'])?></h3><p class="store-meta"><span class="stars">★</span> <?=e($x['rate'] ?: 'New')?><?php if ((int)$x['rc'] > 0) { ?><span>·</span> <?= (int)$x['rc'] ?> ratings<?php } ?></p><a class="btn shop-link" href="store.php?id=<?=(int)$x['id']?>">View store <span>→</span></a></div></article><?php }?></div>
<script>function go(){getLoc(()=>document.querySelector('.hero form').submit());return false}</script>
<?php foot();
