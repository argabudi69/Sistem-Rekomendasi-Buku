<?php
require_once __DIR__ . '/../includes/functions.php';

if (admin_logged_in()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = db()->prepare('SELECT id, name, email, password FROM admins WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($email == $admin['email'] && $password == $admin['password']) {
    $_SESSION['admin'] = $admin;

    header("Location: index.php");
    exit;
}

        $error = 'Login gagal. Periksa kembali email dan password.';
    }
}

render_header('Login Admin', 'public', '', [
    'subtitle' => 'Masuk untuk mengelola buku',
]);
?>
<section class="container auth-shell">
    <div class="auth-card">
        <div class="auth-top">
            <span class="badge">🔐 Akses admin</span>
            <h1 style="margin: 14px 0 8px;">Masuk ke panel pengelola</h1>
            <p class="helper">Gunakan akun admin untuk menambahkan buku, kategori, penulis, dan deskripsi yang dipakai pada cosine similarity.</p>
        </div>
        <div class="auth-body">
            <?php if ($error): ?>
                <div class="notice error"><?= e($error); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <div class="field">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Masuk</button>
                    <a class="btn btn-soft" href="../dashboard/index.php">Kembali ke dashboard</a>
                </div>
            </form>
        </div>
    </div>
</section>
<?php render_footer(); ?>
