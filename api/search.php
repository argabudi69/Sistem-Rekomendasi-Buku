<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');
$limit = 12;

if ($query !== '') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $cleanQuery = mb_strtolower(trim($query), 'UTF-8');
    if (mb_strlen($cleanQuery, 'UTF-8') >= 3) {
        if (!isset($_SESSION['last_query']) || $_SESSION['last_query'] !== $cleanQuery) {
            $stmt = db()->prepare('INSERT INTO search_logs (query) VALUES (?)');
            $stmt->bind_param('s', $cleanQuery);
            $stmt->execute();
            $stmt->close();
            $_SESSION['last_query'] = $cleanQuery;
        }
    }
}

$books = search_books($query, $limit);
$payload = [];

foreach ($books as $book) {
    $tone = visual_tone($book);
    $payload[] = [
        'id' => (int) $book['id'],
        'title' => (string) $book['title'],
        'author' => (string) $book['author'],
        'category' => (string) $book['category'],
        'year' => $book['year'] ? (int) $book['year'] : null,
        'description' => excerpt((string) $book['description'], 135),
        'initial' => $tone['initial'],
        'cover_bg' => $tone['bg'],
        'cover_image' => book_cover_url($book['cover_image'] ?? ''),
        'score' => isset($book['score']) ? (float) $book['score'] : null,
        'matches' => $book['matches'] ?? [],
    ];
}

$message = $query === ''
    ? 'Menampilkan buku terbaru sebagai rekomendasi awal.'
    : 'Menampilkan hasil teratas berdasarkan cosine similarity.';

echo json_encode([
    'query' => $query,
    'count' => count($payload),
    'message' => $message,
    'books' => $payload,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
