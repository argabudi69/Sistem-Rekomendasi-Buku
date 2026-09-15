<?php
require_once __DIR__ . '/../includes/functions.php';

$stats = stats_overview();
$categories = category_list();
$featured = search_books('pembelajaran sekolah dasar literasi sains', 8);
$latest = search_books('', 8);

render_header('Dashboard', 'public', 'dashboard', [
    'subtitle' => 'Sistem rekomendasi buku perpustakaan',
]);
?>

<section class="container">
    <div class="hero">
        <div class="hero-grid">

            <div>
                <h1>Temukan Buku Yang Kamu Sukai</h1>

                <div class="search-panel">
                    <form id="liveSearchForm" data-endpoint="../api/search.php">
                        <div class="search-row">
                            <input
                                id="liveSearchInput"
                                class="search-input"
                                type="text"
                                name="q"
                                placeholder="Cari buku, misalnya: literasi, sains, matematika..."
                                autocomplete="off"
                                value=""
                            >

                            <button class="btn btn-primary" type="submit">
                                Cari Buku
                            </button>
                        </div>
                    </form>

                    <?php if (!empty($categories)): ?>
                    <div class="chips" style="margin-top: 10px;">
                        <?php foreach (array_slice($categories, 0, 6) as $category): ?>
                            <button
                                class="chip"
                                type="button"
                                data-search-chip
                                data-query="<?= e($category); ?>"
                            >
                                <?= e($category); ?>
                            </button>
                        <?php endforeach; ?>
                        <?php if (count($categories) > 6): ?>
                            <a href="../books/index.php" class="chip muted" style="text-decoration: none;">Lainnya...</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="section" style="display: none;">
                    <div class="results-meta" id="resultsMeta"></div>
                </div>
            </div>

            <aside class="hero-side">

                <div class="metric" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2); transition: transform 0.2s;">
                    <div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 50%; display: flex;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                    </div>
                    <div>
                        <strong style="font-size: 1.4rem; display: block; margin-bottom: 0px; color: white;"><?= (int) $stats['books']; ?></strong>
                        <span style="font-size: 0.85rem; opacity: 0.9; font-weight: 500; color: white;">Total Buku</span>
                    </div>
                </div>

                <div class="metric" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); transition: transform 0.2s; margin-top: 15px;">
                    <div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 50%; display: flex;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    </div>
                    <div>
                        <strong style="font-size: 1.4rem; display: block; margin-bottom: 0px; color: white;"><?= (int) $stats['categories']; ?></strong>
                        <span style="font-size: 0.85rem; opacity: 0.9; font-weight: 500; color: white;">Kategori</span>
                    </div>
                </div>

            </aside>
        </div>
    </div>

    <div class="section">

        <div class="section-head">
            <div>
                <h2>Rekomendasi Buku</h2>
            </div>
        </div>

        <div id="searchResults" class="grid-4">
            <?php foreach ($featured as $book): ?>
                <?= book_card_html($book); ?>
            <?php endforeach; ?>
        </div>

    </div>




</section>

<?php render_footer(); ?>