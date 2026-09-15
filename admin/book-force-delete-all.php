<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // get all deleted books to remove covers
    $stmt = db()->prepare('SELECT cover_image FROM books WHERE deleted_at IS NOT NULL');
    $stmt->execute();
    $res = $stmt->get_result();
    while ($book = $res->fetch_assoc()) {
        if (!empty($book['cover_image'])) {
            $coverPath = __DIR__ . '/../' . ltrim($book['cover_image'], '/');
            if (file_exists($coverPath)) {
                @unlink($coverPath);
            }
        }
    }
    $stmt->close();

    // delete all
    db()->query('DELETE FROM books WHERE deleted_at IS NOT NULL');
    
    flash_set('success', 'Semua riwayat buku berhasil dihapus permanen.');
}

redirect('book-history.php');
