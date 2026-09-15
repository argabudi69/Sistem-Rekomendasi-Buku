<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    // get cover image to delete if it exists
    $stmt = db()->prepare('SELECT cover_image FROM books WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $book = $res->fetch_assoc();
    $stmt->close();

    if ($book && !empty($book['cover_image'])) {
        $coverPath = __DIR__ . '/../' . ltrim($book['cover_image'], '/');
        if (file_exists($coverPath)) {
            unlink($coverPath);
        }
    }

    $stmt = db()->prepare('DELETE FROM books WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    
    flash_set('success', 'Data buku berhasil dihapus permanen.');
} else {
    flash_set('error', 'ID buku tidak valid.');
}

redirect('book-history.php');
