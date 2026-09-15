<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Sistem Rekomendasi Buku');
}
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: '');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'db_ta2');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
}

function app_base_path(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = rtrim(dirname($script), '/');

    if ($dir === '' || $dir === '/' || $dir === '.') {
        return '';
    }

    $base = rtrim(dirname($dir), '/');

    if ($base === '' || $base === '/' || $base === '.') {
        return '';
    }

    return $base;
}

function site_url(string $path = ''): string
{
    $base = app_base_path();
    $path = ltrim(str_replace('\\', '/', $path), '/');

    if ($base === '') {
        return '/' . $path;
    }

    return $base . '/' . $path;
}

function db(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    $conn = mysqli_init();

    $is_production = getenv('VERCEL') === '1';

    if ($is_production) {
        $ca_file = __DIR__ . '/ca.pem';

        if (file_exists($ca_file)) {
            $conn->ssl_set(null, null, $ca_file, null, null);
        }
    }

    $success = $conn->real_connect(
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        DB_PORT
    );

    if (!$success) {
        http_response_code(500);
        die('Koneksi database gagal: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');

    return $conn;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    if ($path !== '' && $path[0] === '/') {
        header('Location: ' . $path);
        exit;
    }

    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
        exit;
    }

    header('Location: ' . $path);
    exit;
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }
    $value = (string) $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $value;
}

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin']) && is_array($_SESSION['admin']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect(site_url('admin/login.php'));
    }
}

function current_admin_name(): string
{
    return (string) ($_SESSION['admin']['name'] ?? 'Admin');
}

function current_admin_id(): ?int
{
    return isset($_SESSION['admin']['id']) ? (int) $_SESSION['admin']['id'] : null;
}

function excerpt(string $text, int $length = 140): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length, 'UTF-8') . '…';
}

function get_stopwords(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    
    $cache = [];
    $result = db()->query('SELECT word FROM stopwords');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cache[] = mb_strtolower($row['word'], 'UTF-8');
        }
        $result->free();
    }
    return $cache;
}

function tokenize(string $text): array
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\pL\pN]+/u', ' ', $text);
    $parts = preg_split('/\s+/u', trim((string) $text)) ?: [];

    $stopwords = get_stopwords();

    $tokens = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || mb_strlen($part, 'UTF-8') < 2) {
            continue;
        }
        if (in_array($part, $stopwords, true)) {
            continue;
        }
        $tokens[] = $part;
    }

    return $tokens;
}

function vectorize(string $text): array
{
    $tokens = tokenize($text);
    if (!$tokens) {
        return [];
    }

    $freq = [];
    foreach ($tokens as $token) {
        $freq[$token] = ($freq[$token] ?? 0) + 1;
    }
    return $freq;
}

function cosine_similarity_detail(string $a, string $b): array
{
    $va = vectorize($a);
    $vb = vectorize($b);

    if (!$va || !$vb) {
        return ['score' => 0.0, 'matches' => []];
    }

    $dot = 0.0;
    $matches = [];
    foreach ($va as $term => $count) {
        if (isset($vb[$term])) {
            $dot += $count * $vb[$term];
            $matches[] = $term;
        }
    }

    $normA = 0.0;
    foreach ($va as $count) {
        $normA += $count * $count;
    }

    $normB = 0.0;
    foreach ($vb as $count) {
        $normB += $count * $count;
    }

    $normA = sqrt($normA);
    $normB = sqrt($normB);

    if ($normA <= 0 || $normB <= 0) {
        return ['score' => 0.0, 'matches' => []];
    }

    return ['score' => $dot / ($normA * $normB), 'matches' => $matches];
}

function cosine_similarity(string $a, string $b): float
{
    return cosine_similarity_detail($a, $b)['score'];
}

function book_document(array $book): string
{
    $title = (string) ($book['title'] ?? '');
    $author = (string) ($book['author'] ?? '');
    $category = (string) ($book['category'] ?? '');
    $description = (string) ($book['description'] ?? '');
    $keywords = (string) ($book['keywords'] ?? '');

    // Title boosting: we repeat the title 3 times
    return trim(implode(' ', [
        $title, $title, $title, $category, $author, $description, $keywords
    ]));
}

function palette_by_id(int $id): array
{
    $palettes = [
        ['#7c3aed', '#4f46e5'],
        ['#06b6d4', '#0f766e'],
        ['#f97316', '#ef4444'],
        ['#10b981', '#14b8a6'],
        ['#8b5cf6', '#ec4899'],
        ['#2563eb', '#38bdf8'],
        ['#eab308', '#f59e0b'],
        ['#14b8a6', '#22c55e'],
    ];
    return $palettes[$id % count($palettes)];
}

function visual_tone(array $book): array
{
    $id = (int) ($book['id'] ?? 0);
    $palette = palette_by_id($id);
    $initial = strtoupper(mb_substr(trim((string) ($book['title'] ?? '?')), 0, 1, 'UTF-8'));
    if ($initial === '') {
        $initial = '?';
    }

    return [
        'bg' => "linear-gradient(135deg, {$palette[0]}, {$palette[1]})",
        'initial' => $initial,
    ];
}

function book_cover_url(?string $coverImage): string
{
    $coverImage = trim((string) $coverImage);

    if ($coverImage === '') {
        return '';
    }

    if (preg_match('#^(?:https?:)?//#i', $coverImage)) {
        return $coverImage;
    }

    if ($coverImage[0] === '/') {
        return $coverImage;
    }

    return site_url($coverImage);
}

function book_card_html(array $book, string $linkBase = '../books/detail.php?id=', ?float $score = null): string
{
    $tone = visual_tone($book);
    $titleRaw = (string) ($book['title'] ?? '');
    $title = e($titleRaw);
    $author = e($book['author'] ?? '');
    $category = e($book['category'] ?? 'Umum');
    $year = !empty($book['year']) ? (int) $book['year'] : null;
    $description = e(excerpt((string) ($book['description'] ?? ''), 130));
    $id = (int) ($book['id'] ?? 0);
    $scoreHtml = '';
    $coverImage = trim((string) ($book['cover_image'] ?? ''));

    if ($score !== null) {
        $percent = number_format($score * 100, 1);
        $scoreHtml = '<span class="score-pill">' . $percent . '% cocok</span>';
    }

    $yearHtml = $year ? ' • ' . $year : '';

    if ($coverImage !== '') {
        $coverUrl = book_cover_url($coverImage);

        $coverHtml = '
            <div class="book-cover book-cover-image-wrap">
                <img
                    src="' . e($coverUrl) . '"
                    alt="' . $title . '"
                    class="book-cover-img"
                >
            </div>
        ';
    } else {
        $coverHtml = '
            <div class="book-cover book-cover-fallback" style="background:' . $tone['bg'] . ';">
                <div class="book-cover-inner">
                    <div class="book-initial">' . e($tone['initial']) . '</div>
                    <span class="book-category">' . $category . '</span>
                </div>
            </div>
        ';
    }

    $matches = $book['matches'] ?? [];
    $matchesHtml = '';
    if (!empty($matches)) {
        $matchesList = implode(', ', array_slice($matches, 0, 3));
        if (count($matches) > 3) $matchesList .= '...';
        $matchesHtml = '<div style="margin-top: 4px; font-size: 0.82rem; color: var(--muted);"><span style="background: rgba(16, 185, 129, 0.15); color: #059669; padding: 2px 6px; border-radius: 4px; font-weight: 700;">Topik:</span> ' . e($matchesList) . '</div>';
    }

    return '
    <article class="book-card">
        ' . $coverHtml . '
        <div class="book-card-body">
            <div class="book-card-meta">
                <span class="chip muted">' . $category . '</span>'
                . $scoreHtml .
            '</div>
            <h3 class="book-title">' . $title . '</h3>
            <p class="book-author">' . $author . $yearHtml . '</p>
            <p class="book-description">' . $description . '</p>
            ' . $matchesHtml . '
            <div class="book-actions">
                <a class="btn btn-soft" href="' . e($linkBase) . $id . '">Lihat detail</a>
            </div>
        </div>
    </article>';
}

function get_book_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM books WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $book ?: null;
}

function all_books(): array
{
    $books = [];
    $result = db()->query('SELECT * FROM books WHERE deleted_at IS NULL ORDER BY created_at DESC, id DESC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
        $result->free();
    }
    return $books;
}

function search_books(string $query, int $limit = 12, ?int $excludeId = null): array
{
    $books = all_books();

    if (trim($query) === '') {
        if ($excludeId !== null) {
            $books = array_values(array_filter($books, fn($book) => (int) $book['id'] !== $excludeId));
        }
        return array_slice($books, 0, $limit);
    }

    $ranked = [];
    foreach ($books as $book) {
        if ($excludeId !== null && (int) $book['id'] === $excludeId) {
            continue;
        }

        $sim = cosine_similarity_detail($query, book_document($book));
        $book['score'] = $sim['score'];
        $book['matches'] = $sim['matches'];
        $ranked[] = $book;
    }

    usort($ranked, function ($a, $b) {
        return ($b['score'] <=> $a['score']) ?: ((int) $b['id'] <=> (int) $a['id']);
    });

    $ranked = array_values(array_filter($ranked, fn($book) => ($book['score'] ?? 0) > 0));

    // Normalisasi Relatif (Dampened) agar hasil terbaik bisa mencapai skor tinggi / 1.0
    if (!empty($ranked)) {
        $maxScore = $ranked[0]['score'];
        if ($maxScore > 0) {
            $normFactor = max($maxScore, 0.4);
            foreach ($ranked as &$b) {
                $b['score'] = min(1.0, $b['score'] / $normFactor);
            }
        }
    }

    if (!$ranked) {
        if ($excludeId !== null) {
            $books = array_values(array_filter($books, fn($book) => (int) $book['id'] !== $excludeId));
        }
        return array_slice($books, 0, $limit);
    }

    return array_slice($ranked, 0, $limit);
}

function related_books(array $book, int $limit = 6): array
{
    $seed = book_document($book);
    return search_books($seed, $limit, (int) $book['id']);
}

function stats_overview(): array
{
    $conn = db();
    $counts = [
        'books' => 0,
        'categories' => 0,
        'searches' => 0,
        'latest' => '—',
    ];

    if ($res = $conn->query('SELECT COUNT(*) AS total FROM books WHERE deleted_at IS NULL')) {
        $counts['books'] = (int) ($res->fetch_assoc()['total'] ?? 0);
    }

    if ($res = $conn->query("SELECT COUNT(DISTINCT category) AS total FROM books WHERE category <> '' AND deleted_at IS NULL")) {
        $counts['categories'] = (int) ($res->fetch_assoc()['total'] ?? 0);
    }

    if ($res = $conn->query('SELECT COUNT(*) AS total FROM search_logs')) {
        $counts['searches'] = (int) ($res->fetch_assoc()['total'] ?? 0);
    }

    if ($res = $conn->query('SELECT title FROM books WHERE deleted_at IS NULL ORDER BY created_at DESC, id DESC LIMIT 1')) {
        $counts['latest'] = (string) ($res->fetch_assoc()['title'] ?? '—');
    }

    return $counts;
}

function category_list(): array
{
    $categories = [];
    $result = db()->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category <> '' AND deleted_at IS NULL ORDER BY category ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = (string) $row['category'];
        }
        $result->free();
    }
    return $categories;
}

function render_header(string $title, string $mode = 'public', string $active = '', array $extra = []): void
{
    $bodyClass = trim(($extra['body_class'] ?? '') . ' ' . ($mode === 'admin' ? 'page-admin' : 'page-public'));
    $subtitle = $extra['subtitle'] ?? 'Perpustakaan SDN Baru 06 Pagi Jakarta';

    $publicNav = [
        ['label' => 'Beranda', 'href' => site_url('dashboard/index.php'), 'key' => 'dashboard', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'],
        ['label' => 'Katalog', 'href' => site_url('books/index.php'), 'key' => 'books', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>'],
        ['label' => 'Admin', 'href' => site_url('admin/login.php'), 'key' => 'admin', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>', 'color' => '#2563eb'],
    ];
    $adminNav = [
        ['label' => 'Dashboard', 'href' => site_url('admin/index.php'), 'key' => 'dashboard', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'],
        ['label' => 'Buku', 'href' => site_url('admin/books.php'), 'key' => 'books', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>'],
        ['label' => 'Laporan', 'href' => site_url('admin/reports.php'), 'key' => 'reports', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>'],
        ['label' => 'Riwayat', 'href' => site_url('admin/book-history.php'), 'key' => 'book-history', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>'],
        ['label' => 'Stopwords', 'href' => site_url('admin/stopwords.php'), 'key' => 'stopwords', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>'],
        ['label' => 'Logout', 'href' => site_url('admin/logout.php'), 'key' => 'logout', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>', 'color' => '#ef4444'],
    ];
    $navItems = $mode === 'admin' ? $adminNav : $publicNav;
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) . ' · ' . APP_NAME; ?></title>
    <link rel="stylesheet" href="<?= e(site_url('assets/css/style.css?v=' . filemtime(__DIR__ . '/../assets/css/style.css'))); ?>">
    <script defer src="<?= e(site_url('assets/js/app.js?v=' . filemtime(__DIR__ . '/../assets/js/app.js'))); ?>"></script>
</head>
<body class="<?= e($bodyClass); ?>">
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= $mode === 'admin' ? e(site_url('admin/index.php')) : e(site_url('dashboard/index.php')); ?>">
            <span class="brand-mark">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                </svg>
            </span>
            <span class="brand-text">
                <strong><?= e(APP_NAME); ?></strong>
                <small><?= e($subtitle); ?></small>
            </span>
        </a>
        <button class="icon-btn mobile-toggle" type="button" data-nav-toggle aria-label="Buka menu">☰</button>
        <nav class="site-nav" data-nav>
            <?php foreach ($navItems as $item): ?>
                <?php $style = isset($item['color']) ? 'style="color: ' . e($item['color']) . '; font-weight: bold;"' : ''; ?>
                <a class="<?= $active === $item['key'] ? 'active' : ''; ?>" href="<?= e($item['href']); ?>" <?= $style; ?>>
                    <?= $item['icon'] ?? ''; ?>
                    <span><?= e($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <button class="icon-btn theme-toggle" type="button" id="themeToggle" aria-label="Ubah tema">◐</button>
    </div>
</header>
<main class="site-main">
    <?php
}

function render_footer(): void
{
    ?>
</main>
<footer class="site-footer">
    <div class="container footer-inner">
        <div>
            <strong>Perpustakaan Digital SDN Baru 06 Pagi</strong>
            <p>Meningkatkan minat baca dan wawasan siswa melalui literasi digital.</p>
        </div>
        <div class="footer-note">&copy; <?= date('Y'); ?> SDN Baru 06 Pagi Jakarta. Semua Hak Cipta Dilindungi.</div>
    </div>
</footer>

<!-- Custom Logout Modal -->
<div class="modal-overlay hidden" id="logoutModal">
    <div class="modal-card">
        <h3>Konfirmasi Logout</h3>
        <p>Apakah Anda yakin ingin keluar dari halaman admin?</p>
        <div class="modal-actions">
            <button class="btn btn-soft" id="cancelLogoutBtn">Batal</button>
            <a class="btn btn-primary" id="confirmLogoutBtn" href="#">Ya, Keluar</a>
        </div>
    </div>
</div>

</body>
</html>
    <?php
}

function format_date_id(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return e($date);
    }
    return date('d M Y', $ts);
}
