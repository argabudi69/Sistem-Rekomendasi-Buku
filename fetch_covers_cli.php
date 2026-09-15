<?php
require_once __DIR__ . '/includes/functions.php';

$conn = db();
$result = $conn->query("SELECT id, title, author FROM books WHERE cover_image IS NULL OR cover_image = ''");
$updatedCount = 0;
$failedCount = 0;

$uploadDir = __DIR__ . '/assets/uploads/covers/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

echo "Memulai import cover untuk " . $result->num_rows . " buku...\n\n";

while ($book = $result->fetch_assoc()) {
    echo "Mencari cover: " . $book['title'] . "... ";
    $query = urlencode('intitle:' . $book['title'] . ' inauthor:' . $book['author']);
    $url = "https://www.googleapis.com/books/v1/volumes?q={$query}&maxResults=1";
    
    $response = @file_get_contents($url);
    $success = false;
    
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data['items'][0]['volumeInfo']['imageLinks']['thumbnail'])) {
            $imgUrl = $data['items'][0]['volumeInfo']['imageLinks']['thumbnail'];
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
                    echo "Sukses!\n";
                }
            }
        }
    }
    
    if (!$success) {
        $failedCount++;
        echo "Gagal/Tidak Ditemukan.\n";
    }
    
    usleep(200000); // 0.2s
}

echo "\n--- PROSES SELESAI ---\n";
echo "Berhasil update: $updatedCount buku\n";
echo "Gagal/Tidak ada di Google Books: $failedCount buku\n";
