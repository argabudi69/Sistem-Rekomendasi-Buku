<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $word = trim(mb_strtolower($_POST['word'] ?? '', 'UTF-8'));
        if ($word !== '') {
            $stmt = $conn->prepare('INSERT IGNORE INTO stopwords (word) VALUES (?)');
            $stmt->bind_param('s', $word);
            if ($stmt->execute()) {
                flash_set('success', 'Stopword berhasil ditambahkan.');
            } else {
                flash_set('error', 'Gagal menambahkan stopword.');
            }
            $stmt->close();
        } else {
            flash_set('error', 'Kata tidak boleh kosong.');
        }
        redirect('stopwords.php');
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM stopwords WHERE id = ?');
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                flash_set('success', 'Stopword berhasil dihapus.');
            } else {
                flash_set('error', 'Gagal menghapus stopword.');
            }
            $stmt->close();
        }
        redirect('stopwords.php');
    }
}

$stopwords = [];
$result = $conn->query('SELECT * FROM stopwords ORDER BY word ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stopwords[] = $row;
    }
    $result->free();
}

$noticeSuccess = flash_get('success');
$noticeError = flash_get('error');

render_header('Kelola Stopwords', 'admin', 'stopwords', [
    'subtitle' => 'Manajemen kata yang diabaikan',
]);
?>

<section class="container">
    <div class="section-head">
        <div>
            <h2>Kelola Stopwords</h2>
            <p>Daftar kata yang akan diabaikan oleh algoritma saat menghitung kemiripan (seperti "dan", "di", "ke").</p>
        </div>
    </div>

    <?php if ($noticeSuccess): ?>
        <div class="notice success"><?= e($noticeSuccess); ?></div>
    <?php endif; ?>
    <?php if ($noticeError): ?>
        <div class="notice error"><?= e($noticeError); ?></div>
    <?php endif; ?>

    <div class="detail-grid">
        <div class="panel">
            <div class="panel-body">
                <h3 style="margin-bottom:16px;">Tambah Kata Baru</h3>
                <form method="post" action="stopwords.php">
                    <input type="hidden" name="action" value="add">
                    <div class="field">
                        <label for="word">Kata</label>
                        <input type="text" id="word" name="word" required placeholder="Contoh: adalah">
                    </div>
                    <button type="submit" class="btn btn-primary">Tambah</button>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <h3 style="margin-bottom:16px;">Daftar Stopwords (<?= count($stopwords); ?> kata)</h3>
                <div class="chips" style="margin-top: 16px;">
                    <?php if (!$stopwords): ?>
                        <p class="helper">Belum ada stopwords.</p>
                    <?php else: ?>
                        <?php foreach ($stopwords as $sw): ?>
                            <form method="post" action="stopwords.php" style="display:inline-block; margin:0;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $sw['id']; ?>">
                                <div class="chip" style="display:flex; align-items:center; gap:6px; padding: 6px 12px;">
                                    <?= e($sw['word']); ?>
                                    <button type="submit" onclick="return confirm('Hapus kata ini?')" style="background:none; border:none; cursor:pointer; color:var(--primary-2); padding:0; font-weight:bold; font-size:1.2rem; line-height:1; display:flex;">&times;</button>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php render_footer(); ?>
