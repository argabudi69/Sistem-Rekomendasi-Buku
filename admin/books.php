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

function csv_rows(string $filePath): array
{
    $rows = [];
    $handle = fopen($filePath, 'rb');
    if (!$handle) {
        return $rows;
    }

    $delimiter = ',';
    $firstLine = fgets($handle);
    if ($firstLine !== false) {
        $comma = substr_count($firstLine, ',');
        $semicolon = substr_count($firstLine, ';');
        $delimiter = $semicolon > $comma ? ';' : ',';
        rewind($handle);
    }

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rows[] = $row;
    }

    fclose($handle);
    return $rows;
}

function xlsx_rows(string $filePath): array
{
    $rows = [];

    if (!class_exists('ZipArchive')) {
        return $rows;
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return $rows;
    }

    $sharedStrings = [];

    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $shared = simplexml_load_string($sharedXml);
        if ($shared !== false) {
            $shared->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $items = $shared->xpath('//a:si');
            if (is_array($items)) {
                foreach ($items as $item) {
                    $textParts = [];
                    $nodes = $item->xpath('.//a:t');
                    if (is_array($nodes)) {
                        foreach ($nodes as $node) {
                            $textParts[] = (string) $node;
                        }
                    }
                    $sharedStrings[] = implode('', $textParts);
                }
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        return $rows;
    }

    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) {
        $zip->close();
        return $rows;
    }

    $sheet->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $sheetRows = $sheet->xpath('//a:sheetData/a:row');

    if (is_array($sheetRows)) {
        foreach ($sheetRows as $rowNode) {
            $row = [];
            $cells = $rowNode->xpath('./a:c');
            if (is_array($cells)) {
                foreach ($cells as $cell) {
                    $ref = (string) $cell['r'];
                    preg_match('/([A-Z]+)(\d+)/', $ref, $m);
                    $colLetters = $m[1] ?? 'A';

                    $colIndex = 0;
                    foreach (str_split($colLetters) as $char) {
                        $colIndex = $colIndex * 26 + (ord($char) - 64);
                    }
                    $colIndex--;

                    $value = '';
                    $type = (string) $cell['t'];

                    if ($type === 's') {
                        $index = isset($cell->v) ? (int) $cell->v : 0;
                        $value = $sharedStrings[$index] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $texts = $cell->xpath('.//a:t');
                        if (is_array($texts)) {
                            $parts = [];
                            foreach ($texts as $text) {
                                $parts[] = (string) $text;
                            }
                            $value = implode('', $parts);
                        }
                    } else {
                        $value = isset($cell->v) ? (string) $cell->v : '';
                    }

                    $row[$colIndex] = $value;
                }
            }

            if ($row) {
                ksort($row);
                $rows[] = array_values($row);
            }
        }
    }

    $zip->close();
    return $rows;
}

function is_header_row(array $row): bool
{
    $normalized = array_map(static fn($value) => strtolower(trim((string) $value)), $row);
    $known = ['title', 'author', 'category', 'year', 'description', 'keywords', 'cover_image'];
    $match = 0;

    foreach ($known as $needle) {
        if (in_array($needle, $normalized, true)) {
            $match++;
        }
    }

    return $match >= 2;
}

function map_row_to_book(array $row, ?array $headers = null): array
{
    $fields = [
        'title' => '',
        'author' => '',
        'category' => '',
        'year' => '',
        'description' => '',
        'keywords' => '',
        'cover_image' => '',
    ];

    if ($headers !== null) {
        foreach ($headers as $index => $header) {
            $key = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)));
            if (array_key_exists($key, $fields)) {
                $fields[$key] = trim((string) ($row[$index] ?? ''));
            }
        }
        return $fields;
    }

    $fields['title'] = trim((string) ($row[0] ?? ''));
    $fields['author'] = trim((string) ($row[1] ?? ''));
    $fields['category'] = trim((string) ($row[2] ?? ''));
    $fields['year'] = trim((string) ($row[3] ?? ''));
    $fields['description'] = trim((string) ($row[4] ?? ''));
    $fields['keywords'] = trim((string) ($row[5] ?? ''));
    $fields['cover_image'] = trim((string) ($row[6] ?? ''));

    return $fields;
}

$noticeSuccess = flash_get('success');
$noticeError = flash_get('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_books'])) {
    if (!isset($_FILES['import_file']) || !is_array($_FILES['import_file'])) {
        $noticeError = 'File impor belum dipilih.';
    } elseif ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $noticeError = 'File impor gagal diunggah.';
    } else {
        $fileTmp = $_FILES['import_file']['tmp_name'];
        $fileName = strtolower((string) $_FILES['import_file']['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            $noticeError = 'Format file harus CSV atau XLSX.';
        } else {
            $rawRows = $ext === 'csv' ? csv_rows($fileTmp) : xlsx_rows($fileTmp);

            if (!$rawRows) {
                $noticeError = 'Data pada file impor tidak terbaca.';
            } else {
                $headers = null;
                if (is_header_row($rawRows[0])) {
                    $headers = $rawRows[0];
                    array_shift($rawRows);
                }

                $inserted = 0;
                $failed = 0;

                foreach ($rawRows as $row) {
                    $book = map_row_to_book($row, $headers);

                    $title = trim($book['title']);
                    $author = trim($book['author']);
                    $category = trim($book['category']);
                    $year = trim($book['year']);
                    $description = trim($book['description']);
                    $keywords = trim($book['keywords']);
                    $coverImage = trim($book['cover_image']);

                    if ($title === '' || $author === '' || $category === '') {
                        $failed++;
                        continue;
                    }

                    $titleEsc = $conn->real_escape_string($title);
                    $authorEsc = $conn->real_escape_string($author);
                    $categoryEsc = $conn->real_escape_string($category);
                    $descriptionEsc = $conn->real_escape_string($description);
                    $keywordsEsc = $conn->real_escape_string($keywords);
                    $coverEsc = $conn->real_escape_string($coverImage);
                    $yearSql = ($year !== '' && is_numeric($year)) ? (int) $year : 'NULL';

                    $sql = "
                        INSERT INTO books
                            (title, author, category, year, description, keywords, cover_image, created_at, updated_at)
                        VALUES
                            ('{$titleEsc}', '{$authorEsc}', '{$categoryEsc}', {$yearSql}, '{$descriptionEsc}', '{$keywordsEsc}', '{$coverEsc}', NOW(), NOW())
                    ";

                    if ($conn->query($sql)) {
                        $inserted++;
                    } else {
                        $failed++;
                    }
                }

                $noticeSuccess = "Import selesai. {$inserted} data berhasil ditambahkan.";
                if ($failed > 0) {
                    $noticeError = "Ada {$failed} baris yang dilewati atau gagal diproses.";
                }
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$allowedSort = [
    'title' => 'title',
    'author' => 'author',
    'category' => 'category',
    'year' => 'year',
    'created_at' => 'created_at',
];

$sort = clean_sort_key((string) ($_GET['sort'] ?? 'created_at'), $allowedSort, 'created_at');
$order = clean_sort_order((string) ($_GET['order'] ?? 'desc'));
$sortColumn = $allowedSort[$sort];

$allowedPerPage = [10, 25, 50, 100];
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $allowedPerPage) ? (int)$_GET['per_page'] : 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$whereSql = ' WHERE deleted_at IS NULL';
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

    $url = 'books.php?' . build_query_string($baseParams);

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
if ($perPage !== 10) {
    $baseParams['per_page'] = $perPage;
}

render_header('Kelola Buku', 'admin', 'books', [
    'subtitle' => 'Manajemen data buku',
]);
?>

<section class="container">

    <div class="section-head">
        <div>
            <h2>Kelola Buku</h2>
        </div>

        <div class="form-actions">
            <a class="btn btn-soft" href="../dashboard/index.php">Lihat Publik</a>
            <a class="btn btn-soft" href="book-history.php">Riwayat Hapus</a>
            <a class="btn btn-soft" href="import-covers.php">Auto-Fetch Cover</a>
            <a class="btn btn-soft" href="book-form.php">+ Tambah Buku</a>
        </div>
    </div>

    <?php if ($noticeSuccess): ?>
        <div class="notice success"><?= e($noticeSuccess); ?></div>
    <?php endif; ?>

    <?php if ($noticeError): ?>
        <div class="notice error"><?= e($noticeError); ?></div>
    <?php endif; ?>

    <div class="section">

        <form method="post" enctype="multipart/form-data" class="search-panel" style="margin-bottom: 18px;">
            <div class="search-row">
                <input class="search-input" type="file" name="import_file" accept=".csv,.xlsx" required>
                <button class="btn btn-soft" type="submit" name="import_books" value="1">Import</button>
            </div>
        </form>

        <form method="get" class="search-panel" style="margin-bottom: 20px;">
            <?php if (isset($_GET['sort'])): ?>
                <input type="hidden" name="sort" value="<?= e($_GET['sort']); ?>">
            <?php endif; ?>
            <?php if (isset($_GET['order'])): ?>
                <input type="hidden" name="order" value="<?= e($_GET['order']); ?>">
            <?php endif; ?>
            <div class="search-row">
                <input
                    class="search-input"
                    type="text"
                    name="search"
                    value="<?= e($search); ?>"
                    placeholder="Cari buku..."
                >
                <select name="per_page" class="search-input" style="flex: 0 0 auto; width: auto; cursor: pointer;" onchange="this.form.submit()">
                    <option value="10" <?= $perPage === 10 ? 'selected' : ''; ?>>10 / Halaman</option>
                    <option value="25" <?= $perPage === 25 ? 'selected' : ''; ?>>25 / Halaman</option>
                    <option value="50" <?= $perPage === 50 ? 'selected' : ''; ?>>50 / Halaman</option>
                    <option value="100" <?= $perPage === 100 ? 'selected' : ''; ?>>100 / Halaman</option>
                </select>
                <button class="btn btn-primary" type="submit">Cari</button>
                <a class="btn btn-soft" href="books.php">Reset</a>
            </div>
        </form>

    </div>

    <div class="table-wrap">
        <form id="bulkDeleteForm" method="post" action="book-bulk-delete.php">
        <table>
            <thead>
                <tr>
                    <th style="width:40px;"><input type="checkbox" id="selectAll"></th>
                    <th>Cover</th>
                    <th><?= sort_link('Judul', 'title', $sort, $order, $baseParams); ?></th>
                    <th><?= sort_link('Penulis', 'author', $sort, $order, $baseParams); ?></th>
                    <th><?= sort_link('Kategori', 'category', $sort, $order, $baseParams); ?></th>
                    <th><?= sort_link('Tahun', 'year', $sort, $order, $baseParams); ?></th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>

            <tbody>
            <?php if (!$books): ?>
                <tr>
                    <td colspan="6">Belum ada data buku.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($books as $book): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int) $book['id']; ?>" class="row-checkbox"></td>
                        <td>
                            <?php if (!empty($book['cover_image'])): ?>
                                <img
                                    src="<?= e('../' . ltrim((string) $book['cover_image'], '/')); ?>"
                                    alt="<?= e($book['title']); ?>"
                                    style="width:54px;height:72px;object-fit:cover;border-radius:10px;"
                                >
                            <?php else: ?>
                                <div style="width:54px;height:72px;border-radius:10px;background:rgba(99,102,241,.12);display:flex;align-items:center;justify-content:center;font-weight:700;">
                                    <?= e(mb_strtoupper(mb_substr((string) $book['title'], 0, 1))); ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td><strong><?= e($book['title']); ?></strong></td>
                        <td><?= e($book['author']); ?></td>
                        <td><?= e($book['category']); ?></td>
                        <td><?= $book['year'] ? (int) $book['year'] : '-'; ?></td>
                        <td style="text-align: center;">
                            <div class="form-actions" style="justify-content: center; gap: 8px;">
                                <a class="btn-icon btn-edit" href="book-form.php?id=<?= (int) $book['id']; ?>" title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                </a>
                                <a class="btn-icon btn-delete" href="book-delete.php?id=<?= (int) $book['id']; ?>" onclick="return confirm('Hapus buku ini?')" title="Hapus">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <?php if ($books): ?>
        <div style="padding: 16px; border-top: 1px solid var(--border); background: var(--bg-2);">
            <button type="submit" class="btn btn-soft" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.2);" onclick="return confirm('Pindahkan buku-buku yang dipilih ke riwayat hapus?')">Hapus Terpilih</button>
        </div>
        <?php endif; ?>
        </form>
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
        href="books.php?<?= e(build_query_string($prevParams)); ?>"
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
                href="books.php?<?= e(build_query_string($params)); ?>"
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
        href="books.php?<?= e(build_query_string($nextParams)); ?>"
    >
        &gt;
    </a>

</div>

</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.row-checkbox');
    
    if (selectAll && checkboxes.length > 0) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        });
        
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const allChecked = Array.from(checkboxes).every(c => c.checked);
                const someChecked = Array.from(checkboxes).some(c => c.checked);
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            });
        });
    }
});
</script>

<?php render_footer(); ?>