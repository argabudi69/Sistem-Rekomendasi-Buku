<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    $stmt = db()->prepare('UPDATE books SET deleted_at = NULL WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    flash_set('success', 'Data buku berhasil dipulihkan dari Riwayat Hapus.');
} else {
    flash_set('error', 'ID buku tidak valid.');
}

redirect('book-history.php');
