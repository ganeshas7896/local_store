<?php
require 'config.php';
$role = input_string($_GET['role'] ?? $_POST['role'] ?? null);
if (!in_array($role, ['customer', 'owner'], true)) {
    $role = '';
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim(input_string($_POST['name'] ?? null));
    $email = strtolower(trim(input_string($_POST['email'] ?? null)));
    $phone = trim(input_string($_POST['phone'] ?? null));
    $password = input_string($_POST['password'] ?? null);
    $postedRole = input_string($_POST['role'] ?? null);

    if ($name === '' || text_length($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120
        || $phone === '' || strlen($phone) > 20 || !preg_match('/^[0-9+(). -]{5,20}$/', $phone)
        || strlen($password) < 12 || strlen($password) > 72 || !in_array($postedRole, ['customer', 'owner'], true)
        || ($role !== '' && $role !== $postedRole)) {
        $err = 'Check the form values. Passwords must be 12 to 72 characters.';
    } elseif (q('SELECT id FROM users WHERE email=?', [$email])) {
        $err = 'Email already registered';
    } else {
        try {
            q('INSERT INTO users(name,email,phone,password,role) VALUES(?,?,?,?,?)', [
                $name,
                $email,
                $phone,
                password_hash($password, PASSWORD_DEFAULT),
                $postedRole,
            ]);
            header('Location: login.php');
            exit;
        } catch (mysqli_sql_exception $exception) {
            if ((int)$exception->getCode() !== 1062) {
                throw $exception;
            }
            $err = 'Email already registered';
        }
    }
}
head($role === 'owner' ? 'Admin Registration' : ($role === 'customer' ? 'User Registration' : 'Register'));
?>
<form class="box" method="post" style="max-width:420px;margin:auto">
    <h2>Create account</h2>
    <?php if ($err) echo '<p class="err">' . e($err) . '</p>'; ?>
    <?php csrf_field(); ?>
    Name<input name="name" maxlength="100" autocomplete="name" required>
    Email<input type="email" name="email" maxlength="120" autocomplete="email" required>
    Phone<input name="phone" maxlength="20" autocomplete="tel" required>
    Password<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required>
    I am a<select name="role" required>
        <option value="customer"<?= $role === 'customer' ? ' selected' : '' ?>>User</option>
        <option value="owner"<?= $role === 'owner' ? ' selected' : '' ?>>Admin (store owner)</option>
    </select>
    <button type="submit">Create account</button>
</form>
<?php foot(); ?>
