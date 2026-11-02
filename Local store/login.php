<?php
require 'config.php';
$role = input_string($_GET['role'] ?? $_POST['role'] ?? null);
if (!in_array($role, ['customer', 'owner'], true)) {
    $role = '';
}
$err = '';
$identifier = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identifier = trim(input_string($_POST['identifier'] ?? null));
    $password = input_string($_POST['password'] ?? null);
    $users = $identifier === '' ? [] : q('SELECT id,name,role,password FROM users WHERE email=? OR phone=?', [
        strtolower($identifier),
        $identifier,
    ]);
    $authenticatedUser = null;
    foreach ($users as $candidate) {
        if (password_verify($password, $candidate['password'])
            && ($role === '' || $candidate['role'] === $role)) {
            $authenticatedUser = $candidate;
            break;
        }
    }
    if ($authenticatedUser) {
        session_regenerate_id(true);
        unset($_SESSION['csrf']);
        $_SESSION['u'] = ['id' => $authenticatedUser['id'], 'name' => $authenticatedUser['name'], 'role' => $authenticatedUser['role']];
        header('Location: ' . ($authenticatedUser['role'] === 'owner' ? 'admin.php' : 'user.php'));
        exit;
    }
    $err = 'Invalid email/mobile or password';
}
head($role === 'owner' ? 'Admin Login' : ($role === 'customer' ? 'User Login' : 'Login'));
?>
<form class="box" method="post" style="max-width:420px;margin:auto">
    <h2><?= $role === 'owner' ? 'Admin sign in' : ($role === 'customer' ? 'User sign in' : 'Sign in') ?></h2>
    <?php if ($err) echo '<p class="err">' . e($err) . '</p>'; ?>
    <?php csrf_field(); ?>
    <?php if ($role !== '') { ?><input type="hidden" name="role" value="<?= e($role) ?>"><?php } ?>
    Email or mobile<input type="text" name="identifier" autocomplete="username" maxlength="120" value="<?= e($identifier) ?>" required>
    Password<input type="password" name="password" autocomplete="current-password" required>
    <button type="submit">Sign in</button>
    <p><a href="register.php<?= $role !== '' ? '?role=' . e($role) : '' ?>">Create account</a></p>
</form>
<?php foot(); ?>
