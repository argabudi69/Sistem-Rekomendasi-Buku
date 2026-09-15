<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $ids = array_filter($ids, fn($id) => $id > 0);
    
    if ($ids) {
        $conn = db();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $stmt = $conn->prepare("UPDATE books SET deleted_at = NOW() WHERE id IN ($placeholders)");
        
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        
        if ($stmt->execute()) {
            flash_set('success', count($ids) . ' buku berhasil dipindahkan ke Riwayat Hapus.');
        } else {
            flash_set('error', 'Gagal menghapus buku-buku yang dipilih.');
        }
        $stmt->close();
    } else {
        flash_set('error', 'Tidak ada ID buku yang valid.');
    }
} else {
    flash_set('error', 'Pilih setidaknya satu buku untuk dihapus.');
}

redirect('books.php');
