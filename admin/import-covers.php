<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$conn = db();
$action = $_GET['action'] ?? '';

if ($action === 'run') {
    // Cari buku yang belum punya cover (NULL atau kosong)
    $result = $conn->query("SELECT id, title, author FROM books WHERE cover_image IS NULL OR cover_image = ''");
    $updatedCount = 0;
    $failedCount = 0;
    
    $uploadDir = __DIR__ . '/../assets/uploads/covers/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    while ($book = $result->fetch_assoc()) {
        // Query ke Google Books API
        $query = urlencode('intitle:' . $book['title'] . ' inauthor:' . $book['author']);
        $url = "https://www.googleapis.com/books/v1/volumes?q={$query}&maxResults=1";
        
        // Nonaktifkan warning jika API error
        $response = @file_get_contents($url);
        
        $success = false;
        
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data['items'][0]['volumeInfo']['imageLinks']['thumbnail'])) {
                $imgUrl = $data['items'][0]['volumeInfo']['imageLinks']['thumbnail'];
                // Pastikan menggunakan HTTPS untuk mendownload gambar
                $imgUrl = str_replace('http://', 'https://', $imgUrl);
                
                $imgData = @file_get_contents($imgUrl);
                if ($imgData) {
                    $newName = uniqid('cover_', true) . '.jpg';
                    $destination = $uploadDir . $newName;
                    
                    if (file_put_contents($destination, $imgData)) {
                        $coverPath = 'assets/uploads/covers/' . $newName;
                        $stmt = $conn->prepare("UPDATE books SET cover_image = ? WHERE id = ?");
                        $stmt->bind_param('si', $coverPath, $book['id']);
                        $stmt->execute();
                        $stmt->close();
                        
                        $updatedCount++;
                        $success = true;
                    }
                }
            }
        }
        
        if (!$success) {
            $failedCount++;
        }
        
        // Beri jeda kecil agar tidak terkena limit API Google (rate limiting)
        usleep(200000); // 0.2 detik
    }
    
    $msg = "Proses selesai. Berhasil mengunduh cover untuk $updatedCount buku.";
    if ($failedCount > 0) {
        $msg .= " Namun, $failedCount buku tidak ditemukan covernya di Google Books.";
    }
    
    flash_set('success', $msg);
    redirect('books.php');
}

render_header('Auto-fetch Cover', 'admin', 'books', [
    'subtitle' => 'Import cover otomatis dari Google Books'
]);
?>

<section class="container">
    <div class="section-head">
        <div>
            <h2>Auto-Fetch Cover Buku</h2>
            <p style="color:var(--muted); margin-top:4px;">Mengunduh cover buku secara massal untuk buku yang belum memiliki gambar.</p>
        </div>
        <a class="btn btn-soft" href="books.php">← Kembali</a>
    </div>

    <div class="panel" style="max-width: 600px; margin: 24px auto;">
        <div class="panel-body" style="text-align: center; padding: 40px 20px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary); margin-bottom: 20px;"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
            
            <h3 style="margin-bottom: 12px;">Import Seluruh Cover Sekaligus?</h3>
            <p style="color: var(--text-color); margin-bottom: 24px;">
                Sistem akan mencari cover buku berdasarkan <strong>Judul</strong> dan <strong>Penulis</strong> melalui Google Books API secara otomatis. Buku yang sudah ada cover-nya tidak akan ditimpa.
            </p>
            
            <div style="background: rgba(245, 158, 11, 0.1); border-left: 4px solid var(--warning); padding: 12px 16px; text-align: left; margin-bottom: 24px; border-radius: 4px; font-size: 0.9rem; color: #b45309;">
                <strong>Perhatian:</strong> Proses ini mungkin membutuhkan waktu beberapa detik atau menit tergantung dari kecepatan koneksi dan jumlah buku yang belum memiliki cover (tersisa <?= db()->query("SELECT COUNT(*) AS c FROM books WHERE cover_image IS NULL OR cover_image = ''")->fetch_assoc()['c'] ?> buku). Mohon jangan tutup halaman selama proses berjalan.
            </div>

            <div class="form-actions" style="justify-content: center;">
                <a class="btn btn-primary" href="import-covers.php?action=run" onclick="this.innerHTML='Sedang memproses... Mohon tunggu'; this.style.pointerEvents='none'; this.style.opacity='0.8';">Mulai Import Sekarang</a>
                <a class="btn btn-soft" href="books.php">Batal</a>
            </div>
        </div>
    </div>
</section>

<?php render_footer(); ?>
