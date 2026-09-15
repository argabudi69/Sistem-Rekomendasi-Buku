<?php
require_once __DIR__ . '/../includes/functions.php';

$query = trim($_GET['q'] ?? '');
$books = $query !== '' ? search_books($query, 24) : all_books();
$categories = category_list();

render_header('Katalog Buku', 'public', 'books', [
    'subtitle' => 'Katalog lengkap dan pencarian lanjutan',
]);
?>
<section class="container">
    <div class="section-head">
        <div>
            <h2>Katalog buku</h2>
            <p>Gunakan pencarian untuk menampilkan buku yang paling dekat secara semantik berdasarkan cosine similarity.</p>
        </div>
    </div>

    <div class="search-panel">
        <form method="get" class="search-row">
            <input class="search-input" type="text" name="q" placeholder="Cari judul, kategori, atau topik..." value="<?= e($query); ?>">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-soft" href="index.php">Reset</a>
        </form>

        <div class="chips">
            <?php foreach (array_slice($categories, 0, 10) as $category): ?>
                <a class="chip" href="index.php?q=<?= urlencode($category); ?>"><?= e($category); ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="section">
        <div class="results-meta">
            <span><?= count($books); ?> buku ditemukan</span>
            <span>•</span>
            <span><?= $query !== '' ? 'Disusun berdasarkan kemiripan' : 'Disusun dari buku terbaru' ?></span>
        </div>

        <div class="grid-4">
            <?php foreach ($books as $book): ?>
                <?= book_card_html($book); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php render_footer(); ?>
