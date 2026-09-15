<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$conn = db();

function clean_sort_key(string $key, array $allowed, string $default): string
{
    return array_key_exists($key, $allowed) ? $key : $default;
}

function clean_sort_order(string $order): string
{
    $order = strtolower($order);
    return $order === 'asc' ? 'ASC' : 'DESC';
}

function build_query_string(array $params): string
{
    return http_build_query($params);
}

$noticeSuccess = flash_get('success');
$noticeError = flash_get('error');

$search = trim($_GET['search'] ?? '');
$allowedSort = [
    'title' => 'title',
    'author' => 'author',
    'category' => 'category',
    'year' => 'year',
    'deleted_at' => 'deleted_at',
];

$sort = clean_sort_key((string) ($_GET['sort'] ?? 'deleted_at'), $allowedSort, 'deleted_at');
$order = clean_sort_order((string) ($_GET['order'] ?? 'desc'));
$sortColumn = $allowedSort[$sort];

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$whereSql = ' WHERE deleted_at IS NOT NULL';
$countSql = 'SELECT COUNT(*) AS total FROM books';
$listSql = 'SELECT * FROM books';

if ($search !== '') {
    $searchEsc = $conn->real_escape_string($search);
    $whereSql .= " AND (title LIKE '%{$searchEsc}%' OR author LIKE '%{$searchEsc}%' OR category LIKE '%{$searchEsc}%' OR description LIKE '%{$searchEsc}%' OR keywords LIKE '%{$searchEsc}%')";
}

$countSql .= $whereSql;
$listSql .= $whereSql . " ORDER BY {$sortColumn} {$order} LIMIT {$perPage} OFFSET {$offset}";

$totalBooks = 0;

$countResult = $conn->query($countSql);
if ($countResult) {
    $totalBooks = (int) ($countResult->fetch_assoc()['total'] ?? 0);
}

$result = $conn->query($listSql);

$books = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    $result->free();
}

$totalPages = max(1, (int) ceil($totalBooks / $perPage));

function sort_link(
    string $label,
    string $key,
    string $currentSort,
    string $currentOrder,
    array $baseParams
): string {

    $isActive = $currentSort === $key;

    $nextOrder = (
        $isActive && strtoupper($currentOrder) === 'ASC'
    ) ? 'desc' : 'asc';

    $baseParams['sort'] = $key;
    $baseParams['order'] = $nextOrder;
    $baseParams['page'] = 1;

    $icon = '↕';

    if ($isActive) {
        $icon = strtoupper($currentOrder) === 'ASC'
            ? '▲'
            : '▼';
    }

    $url = 'book-history.php?' . build_query_string($baseParams);

    return '
        <a
            href="' . e($url) . '"
            style="
                display:flex;
                align-items:center;
                gap:6px;
                text-decoration:none;
                color:inherit;
                font-weight:600;
            "
        >
            <span>' . e($label) . '</span>
            <span style="font-size:11px;opacity:.7;">' . $icon . '</span>
        </a>
    ';
}

$baseParams = [];
if ($search !== '') {
    $baseParams['search'] = $search;
}

render_header('Riwayat Hapus', 'admin', 'book-history', [
    'subtitle' => 'Data buku yang telah dihapus',
]);
?>

<section class="container">

    <div class="section-head">
        <div>
            <h2>Riwayat Penghapusan Buku</h2>
        </div>

        <div class="form-actions">
            <?php if ($totalBooks > 0): ?>
            <button class="btn btn-soft" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.2);" onclick="document.getElementById('deleteAllModal').classList.remove('hidden')">Hapus Semua Riwayat</button>
            <?php endif; ?>
            <a class="btn btn-soft" href="books.php">Kembali ke Kelola Buku</a>
        </div>
    </div>

    <?php if ($noticeSuccess): ?>
        <div class="notice success"><?= e($noticeSuccess); ?></div>
    <?php endif; ?>

    <?php if ($noticeError): ?>
        <div class="notice error"><?= e($noticeError); ?></div>
    <?php endif; ?>

    <div class="section">
        <form method="get" class="search-panel" style="margin-bottom: 20px;">
            <div class="search-row">
                <input
                    class="search-input"
                    type="text"
                    name="search"
                    value="<?= e($search); ?>"
                    placeholder="Cari buku terhapus..."
                >
                <button class="btn btn-primary" type="submit">Cari</button>
                <a class="btn btn-soft" href="book-history.php">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Cover</th>
                    <th><?= sort_link('Judul', 'title', $sort, $order, $baseParams); ?></th>
                    <th><?= sort_link('Kategori', 'category', $sort, $order, $baseParams); ?></th>
                    <th><?= sort_link('Waktu Dihapus', 'deleted_at', $sort, $order, $baseParams); ?></th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>

            <tbody>
            <?php if (!$books): ?>
                <tr>
                    <td colspan="5">Belum ada data buku yang dihapus.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($books as $book): ?>
                    <tr>
                        <td>
                            <?php if (!empty($book['cover_image'])): ?>
                                <img
                                    src="<?= e('../' . ltrim((string) $book['cover_image'], '/')); ?>"
                                    alt="<?= e($book['title']); ?>"
                                    style="width:54px;height:72px;object-fit:cover;border-radius:10px;opacity:0.6;"
                                >
                            <?php else: ?>
                                <div style="width:54px;height:72px;border-radius:10px;background:rgba(99,102,241,.12);display:flex;align-items:center;justify-content:center;font-weight:700;opacity:0.6;">
                                    <?= e(mb_strtoupper(mb_substr((string) $book['title'], 0, 1))); ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td><strong><?= e($book['title']); ?></strong><br><small><?= e($book['author']); ?></small></td>
                        <td><?= e($book['category']); ?></td>
                        <td><?= format_date_id($book['deleted_at']); ?></td>
                        <td style="text-align: center;">
                            <div class="form-actions" style="justify-content: center; gap: 8px;">
                                <a class="btn-icon btn-restore" href="javascript:void(0);" onclick="confirmRestore(<?= (int) $book['id']; ?>)" title="Restore">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                </a>
                                <a class="btn-icon btn-delete" href="javascript:void(0);" onclick="confirmForceDelete(<?= (int) $book['id']; ?>)" title="Hapus Permanen">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div class="helper">
            Menampilkan <?= $totalBooks ? ($offset + 1) : 0; ?>-<?= min($offset + $perPage, $totalBooks); ?> dari <?= $totalBooks; ?> data
        </div>

        <div class="form-actions" style="flex-wrap:wrap; align-items:center;">

    <?php
    $prevParams = $baseParams;
    $prevParams['sort'] = $sort;
    $prevParams['order'] = strtolower($order);
    $prevParams['page'] = max(1, $page - 1);

    $nextParams = $baseParams;
    $nextParams['sort'] = $sort;
    $nextParams['order'] = strtolower($order);
    $nextParams['page'] = min($totalPages, $page + 1);

    $window = 2;
    ?>

    <a
        class="btn btn-soft <?= $page <= 1 ? 'disabled' : ''; ?>"
        href="book-history.php?<?= e(build_query_string($prevParams)); ?>"
    >
        &lt;
    </a>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>

        <?php if (
            $i == 1 ||
            $i == $totalPages ||
            ($i >= $page - $window && $i <= $page + $window)
        ): ?>

            <?php
            $params = $baseParams;
            $params['sort'] = $sort;
            $params['order'] = strtolower($order);
            $params['page'] = $i;
            $active = $i === $page;
            ?>

            <a
                class="btn <?= $active ? 'btn-primary' : 'btn-soft'; ?>"
                href="book-history.php?<?= e(build_query_string($params)); ?>"
            >
                <?= $i; ?>
            </a>

        <?php elseif (
            $i == $page - $window - 1 ||
            $i == $page + $window + 1
        ): ?>

            <span style="padding:0 4px;">...</span>

        <?php endif; ?>

    <?php endfor; ?>

    <a
        class="btn btn-soft <?= $page >= $totalPages ? 'disabled' : ''; ?>"
        href="book-history.php?<?= e(build_query_string($nextParams)); ?>"
    >
        &gt;
    </a>

</div>

</section>

<div class="modal-overlay hidden" id="deleteAllModal">
    <div class="modal-card">
        <h3>Konfirmasi Hapus Semua</h3>
        <p style="color: #ef4444; font-weight: 600; margin-bottom: 12px;">Peringatan: Anda akan menghapus semua buku secara permanen!</p>
        <p>Data yang dihapus tidak dapat dikembalikan lagi. Apakah Anda yakin?</p>
        <div class="modal-actions" style="margin-top: 24px;">
            <button class="btn btn-soft" onclick="document.getElementById('deleteAllModal').classList.add('hidden')">Batal</button>
            <form method="post" action="book-force-delete-all.php" style="margin: 0;">
                <button type="submit" class="btn btn-primary" style="background: #ef4444; border-color: #ef4444;">Ya, Hapus Semua</button>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay hidden" id="deleteSingleModal">
    <div class="modal-card">
        <h3>Hapus Permanen</h3>
        <p style="color: #ef4444; font-weight: 600; margin-bottom: 12px;">Peringatan: Buku ini akan dihapus secara permanen!</p>
        <p>Data yang dihapus tidak dapat dikembalikan lagi. Apakah Anda yakin?</p>
        <div class="modal-actions" style="margin-top: 24px;">
            <button class="btn btn-soft" onclick="document.getElementById('deleteSingleModal').classList.add('hidden')">Batal</button>
            <a href="#" id="deleteSingleBtn" class="btn btn-primary" style="background: #ef4444; border-color: #ef4444;">Ya, Hapus</a>
        </div>
    </div>
</div>

<div class="modal-overlay hidden" id="restoreModal">
    <div class="modal-card">
        <h3>Kembalikan Buku</h3>
        <p>Buku ini akan dikembalikan ke daftar utama dan bisa diakses kembali. Lanjutkan?</p>
        <div class="modal-actions" style="margin-top: 24px;">
            <button class="btn btn-soft" onclick="document.getElementById('restoreModal').classList.add('hidden')">Batal</button>
            <a href="#" id="restoreBtn" class="btn btn-primary" style="background: #10b981; border-color: #10b981;">Ya, Kembalikan</a>
        </div>
    </div>
</div>

<script>
function confirmForceDelete(id) {
    document.getElementById('deleteSingleBtn').href = 'book-force-delete.php?id=' + id;
    document.getElementById('deleteSingleModal').classList.remove('hidden');
}
function confirmRestore(id) {
    document.getElementById('restoreBtn').href = 'book-restore.php?id=' + id;
    document.getElementById('restoreModal').classList.remove('hidden');
}
</script>

<?php render_footer(); ?>
