<?php
require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$book = $id > 0 ? get_book_by_id($id) : null;

if (!$book) {
    redirect('index.php');
}

$related = related_books($book, 8);
$tone = visual_tone($book);
$coverImage = trim((string) ($book['cover_image'] ?? ''));

render_header($book['title'], 'public', 'books', [
    'subtitle' => 'Detail buku dan rekomendasi serupa',
]);
?>

<section class="container">
    <div class="section-head" style="margin-bottom: 24px;">
        <a class="btn btn-soft" href="index.php" style="border-radius: 999px;">← Kembali ke katalog</a>
    </div>

    <div class="panel" style="margin-bottom: 48px; overflow: hidden; position: relative; border-radius: 32px; border: 1px solid var(--border); box-shadow: var(--shadow-strong);">
        <!-- Premium background effect based on book cover tone -->
        <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 50% 0%, <?= $tone['bg']; ?>40, transparent 60%); z-index: 0; pointer-events: none;"></div>
        
        <div class="panel-body" style="padding: 48px; position: relative; z-index: 1;">
            <div style="display: flex; gap: 48px; flex-wrap: wrap;">
                
                <div style="flex: 0 0 340px;">
                    <?php if ($coverImage !== ''): ?>
                        <div style="border-radius: 20px; overflow: hidden; box-shadow: 0 24px 48px rgba(0,0,0,0.2); aspect-ratio: 2 / 3; border: 1px solid rgba(255,255,255,0.1);">
                            <img src="../<?= e(ltrim($coverImage, '/')); ?>" alt="<?= e($book['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                        </div>
                    <?php else: ?>
                        <div style="border-radius: 20px; overflow: hidden; box-shadow: 0 24px 48px rgba(0,0,0,0.2); aspect-ratio: 2 / 3; background: <?= $tone['bg']; ?>; display: flex; align-items: center; justify-content: center; flex-direction: column; color: white; border: 1px solid rgba(255,255,255,0.1);">
                            <div class="book-initial" style="width: 96px; height: 96px; font-size: 2.5rem; margin-bottom: 20px; box-shadow: 0 8px 24px rgba(0,0,0,0.15);">
                                <?= e($tone['initial']); ?>
                            </div>
                            <span class="book-category" style="background: rgba(0,0,0,0.2); border: none; font-size: 0.9rem; padding: 10px 18px;"><?= e($book['category']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="flex: 1; min-width: 320px; display: flex; flex-direction: column;">
                    <div style="display: flex; gap: 12px; margin-bottom: 20px; align-items: center; flex-wrap: wrap;">
                        <span class="badge" style="background: <?= $tone['bg']; ?>25; color: <?= $tone['bg']; ?>; border: 1px solid <?= $tone['bg']; ?>40; font-size: 0.95rem; padding: 10px 16px;">
                            <?= e($book['category']); ?>
                        </span>
                        <?php if (!empty($book['year'])): ?>
                            <span class="badge" style="background: var(--bg-2); color: var(--text); border: 1px solid var(--border); font-size: 0.95rem; padding: 10px 16px;">
                                <?= (int) $book['year']; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <h1 style="font-size: clamp(2.4rem, 4vw, 3.2rem); line-height: 1.15; margin: 0 0 16px; letter-spacing: -0.04em; font-weight: 800; color: var(--text);">
                        <?= e($book['title']); ?>
                    </h1>
                    
                    <p style="font-size: 1.4rem; color: var(--muted); margin: 0 0 32px; font-weight: 500;">
                        Oleh <span style="color: var(--text);"><?= e($book['author']); ?></span>
                    </p>

                    <div style="width: 80px; height: 6px; background: linear-gradient(135deg, <?= $tone['bg']; ?>, var(--primary-2)); border-radius: 3px; margin-bottom: 32px;"></div>

                    <div style="flex-grow: 1;">
                        <h3 style="font-size: 1.3rem; margin: 0 0 16px; color: var(--text);">Sinopsis</h3>
                        <p style="font-size: 1.1rem; line-height: 1.8; color: var(--muted); margin: 0 0 36px; white-space: pre-wrap;"><?= e($book['description']); ?></p>

                        <?php if (!empty($book['keywords'])): ?>
                            <div style="margin-bottom: 40px;">
                                <h4 style="margin: 0 0 16px; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); font-weight: 700;">Kata Kunci</h4>
                                <div class="chips">
                                    <?php foreach (explode(',', $book['keywords']) as $kw): ?>
                                        <span class="chip" style="background: var(--bg-2); box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid var(--border); padding: 8px 16px; border-radius: 12px;">
                                            #<?= e(trim($kw)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions" style="margin-top: 24px; border-top: 1px solid var(--border); padding-top: 32px;">
                        <a class="btn btn-primary" href="../dashboard/index.php" style="padding: 16px 32px; font-size: 1.1rem; border-radius: 20px;">
                            Cari buku lain
                        </a>
                        <a class="btn btn-soft" href="index.php?q=<?= urlencode($book['category']); ?>" style="padding: 16px 32px; font-size: 1.1rem; border-radius: 20px;">
                            Lihat kategori serupa
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Recommendations section -->
    <div class="section-head" style="margin-bottom: 24px; padding-left: 8px;">
        <div>
            <h2 style="font-size: 2rem; margin-bottom: 8px;">Rekomendasi serupa</h2>
            <p style="font-size: 1.05rem;">Buku-buku ini memiliki kemiripan konten yang tinggi berdasarkan hasil cosine similarity dengan buku di atas.</p>
        </div>
    </div>

    <div class="grid-4" style="margin-bottom: 64px;">
        <?php if ($related): ?>
            <?php foreach ($related as $item): ?>
                <?= book_card_html($item, '../books/detail.php?id=', $item['score'] ?? null); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="notice" style="grid-column: 1 / -1; font-size: 1.1rem; padding: 24px;">Belum ada buku serupa yang cukup relevan.</div>
        <?php endif; ?>
    </div>
</section>

<?php render_footer(); ?>
