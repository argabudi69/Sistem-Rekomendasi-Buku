<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$conn = db();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $id > 0;

$book = $isEdit ? get_book_by_id($id) : [
    'title' => '',
    'author' => '',
    'category' => '',
    'year' => '',
    'description' => '',
    'keywords' => '',
    'cover_image' => '',
];

if ($isEdit && !$book) {
    flash_set('error', 'Data buku tidak ditemukan.');
    redirect('books.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');

    $coverImage = $book['cover_image'] ?? '';

    if ($title === '' || $author === '' || $category === '' || $description === '') {
        $error = 'Judul, penulis, kategori, dan deskripsi wajib diisi.';
    } else {
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../assets/uploads/covers/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $tmpName = $_FILES['cover_image']['tmp_name'];
            $originalName = $_FILES['cover_image']['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($extension, $allowedExt, true)) {
                $error = 'Format cover harus JPG, JPEG, PNG, atau WEBP.';
            } else {
                $newName = uniqid('cover_', true) . '.' . $extension;
                $destination = $uploadDir . $newName;

                if (move_uploaded_file($tmpName, $destination)) {
                    if (!empty($book['cover_image'])) {
                        $oldFile = __DIR__ . '/../' . $book['cover_image'];
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }

                    $coverImage = 'assets/uploads/covers/' . $newName;
                } else {
                    $error = 'Gagal mengupload cover buku.';
                }
            }
        }

        if ($error === '') {
            $yearValue = $year !== '' ? (int) $year : null;
            $yearSql = $yearValue !== null ? (int) $yearValue : 'NULL';

            if ($isEdit) {
                $stmt = $conn->prepare('
                    UPDATE books
                    SET title = ?, author = ?, category = ?, year = ' . $yearSql . ', description = ?, keywords = ?, cover_image = ?, updated_at = NOW()
                    WHERE id = ?
                ');
                $stmt->bind_param('ssssssi', $title, $author, $category, $description, $keywords, $coverImage, $id);
                $ok = $stmt->execute();
                $stmt->close();

                if ($ok) {
                    flash_set('success', 'Data buku berhasil diperbarui.');
                    redirect('books.php');
                }
            } else {
                $stmt = $conn->prepare('
                    INSERT INTO books (title, author, category, year, description, keywords, cover_image, created_at, updated_at)
                    VALUES (?, ?, ?, ' . $yearSql . ', ?, ?, ?, NOW(), NOW())
                ');
                $stmt->bind_param('ssssss', $title, $author, $category, $description, $keywords, $coverImage);
                $ok = $stmt->execute();
                $stmt->close();

                if ($ok) {
                    flash_set('success', 'Data buku berhasil ditambahkan.');
                    redirect('books.php');
                }
            }

            $error = 'Gagal menyimpan data buku.';
        }
    }

    $book = [
        'title' => $title,
        'author' => $author,
        'category' => $category,
        'year' => $year,
        'description' => $description,
        'keywords' => $keywords,
        'cover_image' => $coverImage,
    ];
}

render_header($isEdit ? 'Edit Buku' : 'Tambah Buku', 'admin', 'books', [
    'subtitle' => 'Form data buku',
]);
?>

<section class="container">
    <div class="section-head">
        <div>
            <h2><?= $isEdit ? 'Edit buku' : 'Tambah buku'; ?></h2>
        </div>
        <a class="btn btn-soft" href="books.php">← Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="notice error"><?= e($error); ?></div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-body">
            <form method="post" enctype="multipart/form-data">
                <div class="grid-3">
                    <div class="field">
                        <label>Judul buku</label>
                        <input type="text" name="title" value="<?= e($book['title'] ?? ''); ?>" required>
                    </div>

                    <div class="field">
                        <label>Penulis</label>
                        <input type="text" name="author" value="<?= e($book['author'] ?? ''); ?>" required>
                    </div>

                    <div class="field">
                        <label>Kategori</label>
                        <input type="text" name="category" value="<?= e($book['category'] ?? ''); ?>" placeholder="Contoh: IPA" required>
                    </div>
                </div>

                <div class="grid-3">
                    <div class="field">
                        <label>Tahun terbit</label>
                        <input type="number" name="year" value="<?= e((string) ($book['year'] ?? '')); ?>" min="1900" max="2100">
                    </div>

                    <div class="field" style="grid-column: span 2;">
                        <label>Keywords</label>
                        <input type="text" name="keywords" value="<?= e($book['keywords'] ?? ''); ?>" placeholder="Pisahkan dengan koma">
                    </div>
                </div>

                <div class="field">
                    <label>Cover buku</label>
                    <input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp">
                </div>

                <?php if (!empty($book['cover_image'])): ?>
                    <div style="margin: 16px 0 0;">
                        <img
                            src="../<?= e($book['cover_image']); ?>"
                            alt="<?= e($book['title']); ?>"
                            style="width:140px;height:auto;border-radius:16px;object-fit:cover;box-shadow:0 10px 30px rgba(0,0,0,.08);"
                        >
                    </div>
                <?php endif; ?>

                <div class="field" style="margin-top:20px;">
                    <label>Deskripsi buku</label>
                    <textarea name="description" required><?= e($book['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">
                        <?= $isEdit ? 'Simpan perubahan' : 'Simpan buku'; ?>
                    </button>
                    <a class="btn btn-soft" href="books.php">Batal</a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php render_footer(); ?>