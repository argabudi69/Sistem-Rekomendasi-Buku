<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$query = trim($_GET['q'] ?? 'cinta');
$books = search_books($query, 100);

// Filter out books with score 0
$relevantBooks = array_filter($books, fn($b) => isset($b['score']) && $b['score'] > 0);
usort($relevantBooks, fn($a, $b) => $b['score'] <=> $a['score']);
$top10 = array_slice($relevantBooks, 0, 10);
$top5 = array_slice($relevantBooks, 0, 5);

$totalRelevant = count($relevantBooks);
$scores = array_column($relevantBooks, 'score');
$highestScore = $scores ? max($scores) : 0;
$lowestScore = $scores ? min($scores) : 0;
$avgScore = $scores ? array_sum($scores) / count($scores) : 0;
$categoriesFound = count(array_unique(array_column($relevantBooks, 'category')));

// Tanggal bahasa Indonesia
$days = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$months = ['1'=>'Januari', '2'=>'Februari', '3'=>'Maret', '4'=>'April', '5'=>'Mei', '6'=>'Juni', '7'=>'Juli', '8'=>'Agustus', '9'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'];
$dayName = $days[date('l')];
$monthName = $months[date('n')];
$dateString = $dayName . ', ' . date('d') . ' ' . $monthName . ' ' . date('Y') . ' pukul ' . date('H.i.s');
$simpleDateString = 'Jakarta, ' . date('d') . ' ' . $monthName . ' ' . date('Y');

// Tren Pencarian
$conn = db();
$topQueries = [];
$res = $conn->query("SELECT query, COUNT(*) as count FROM search_logs GROUP BY query ORDER BY count DESC LIMIT 10");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $topQueries[] = $row;
    }
}
$todaySearches = 0;
$resToday = $conn->query("SELECT COUNT(*) as total FROM search_logs WHERE DATE(created_at) = CURDATE()");
if ($resToday) {
    $todaySearches = (int) $resToday->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Skripsi - <?= e($query); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', Arial, sans-serif; margin: 0; padding: 0; background: #525659; color: #000; font-size: 11pt; line-height: 1.5; }
        .page { background: #fff; width: 210mm; min-height: 297mm; margin: 20px auto; padding: 20mm; box-sizing: border-box; box-shadow: 0 4px 10px rgba(0,0,0,0.1); position: relative; }
        
        @media print {
            body { background: #fff; }
            .page { margin: 0; padding: 15mm; box-shadow: none; border: none; width: 100%; height: 100%; page-break-after: always; }
            .no-print { display: none !important; }
        }

        .header { display: flex; align-items: center; border-bottom: 4px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header-logo { width: 80px; height: 80px; display: grid; place-items: center; font-weight: bold; font-size: 10px; text-align: center; margin-right: 20px; }
        .header-text { flex: 1; text-align: center; }
        .header-text h1 { margin: 0; font-size: 16pt; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .header-text p { margin: 2px 0 0; font-size: 10pt; color: #333; }

        .report-title { text-align: center; font-weight: bold; font-size: 14pt; margin: 20px 0; text-transform: uppercase; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt; }
        th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
        th { background: #4f46e5; color: #fff; font-weight: 600; border-color: #4f46e5; }
        tr:nth-child(even) { background: #f8fafc; }
        
        .score-box { background: #f1f5f9; border-left: 4px solid #4f46e5; padding: 10px 15px; margin-bottom: 20px; }
        .score-box h4 { margin: 0 0 5px 0; font-size: 11pt; }
        
        .footer { position: absolute; bottom: 15mm; left: 15mm; right: 15mm; font-size: 9pt; color: #666; border-top: 1px solid #ddd; padding-top: 10px; display: flex; justify-content: space-between; }
        .footer-center { position: absolute; left: 50%; transform: translateX(-50%); font-style: italic; }

        .signature { margin-top: 50px; display: flex; justify-content: flex-end; }
        .signature-box { text-align: center; width: 250px; }
        .signature-box .name { margin-top: 70px; font-weight: bold; text-decoration: underline; }

        .controls { position: fixed; top: 20px; right: 20px; z-index: 1000; }
        .btn { display: inline-block; background: #10b981; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: 600; font-family: inherit; border: none; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn:hover { background: #059669; }
    </style>
</head>
<body>

<div class="controls no-print">
    <button class="btn" onclick="window.print()">🖨️ Cetak Laporan (4 Halaman)</button>
</div>

<?php
function render_page_header($title)
{
    echo '
    <div class="header">

        <div class="header-logo">
            <img src="logo sd.jpeg"
                 alt="Logo Sekolah"
                 style="width:60px;height:60px;object-fit:contain;">
        </div>

        <div class="header-text">
            <h1>SDN BARU 06 PAGI JAKARTA</h1>
            <p>Jl. Puskesmas No.8 2, RT.2/RW.1, Baru, Kec. Ps. Rebo, Kota Jakarta Timur, Daerah Khusus Ibukota Jakarta 13780</p>
            <p>Telp: (021) 87781650 | Email: sdnbaru06pagi@gmail.com</p>
        </div>

    </div>

    <div class="report-title">' . $title . '</div>
    ';
}

function render_page_footer($page) {
    global $dateString;
    echo '
    <div class="footer">
        <div>Dicetak pada: ' . $dateString . '</div>
        <div>Laporan ' . $page . ' dari 4</div>
    </div>
    </div>
    ';
}
?>

<!-- HALAMAN 1: Metrik & Top 10 -->
<div class="page">
    <?php render_page_header('LAPORAN ANALISIS REKOMENDASI BUKU<br>PERPUSTAKAAN DIGITAL'); ?>
    
    <div style="margin-bottom: 20px; font-weight: 600;">Analisis Pencarian: "<?= e($query); ?>"</div>

    <table>
        <thead>
            <tr>
                <th style="width: 30%;">Metrik</th>
                <th style="width: 20%;">Nilai</th>
                <th style="width: 50%;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Buku Ditemukan</td>
                <td><?= $totalRelevant; ?></td>
                <td>Jumlah buku yang relevan</td>
            </tr>
            <tr>
                <td>Skor Rata-rata</td>
                <td><?= number_format($avgScore, 3); ?></td>
                <td>Rata-rata tingkat kesesuaian</td>
            </tr>
            <tr>
                <td>Skor Tertinggi</td>
                <td><?= number_format($highestScore, 3); ?></td>
                <td>Buku dengan kesesuaian tertinggi</td>
            </tr>
            <tr>
                <td>Skor Terendah</td>
                <td><?= number_format($lowestScore, 3); ?></td>
                <td>Buku dengan kesesuaian terendah</td>
            </tr>
            <tr>
                <td>Kategori Ditemukan</td>
                <td><?= $categoriesFound; ?></td>
                <td>Variasi kategori buku yang relevan</td>
            </tr>
        </tbody>
    </table>

    <h3 style="margin-top: 30px;">Tabel Peringkat Rekomendasi</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">Rank</th>
                <th style="width: 35%;">Judul Buku</th>
                <th style="width: 25%;">Penulis</th>
                <th style="width: 20%;">Kategori</th>
                <th style="width: 15%; text-align: center;">Skor</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($top10)): ?>
            <tr><td colspan="5" style="text-align:center;">Tidak ada buku yang relevan dengan kata kunci tersebut.</td></tr>
            <?php else: ?>
                <?php foreach ($top10 as $index => $book): ?>
                <tr>
                    <td style="text-align: center;"><?= $index + 1; ?></td>
                    <td><?= e($book['title']); ?></td>
                    <td><?= e($book['author']); ?></td>
                    <td><?= e($book['category']); ?></td>
                    <td style="text-align: center; font-weight: 600;"><?= number_format($book['score'], 3); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php render_page_footer(1); ?>

<!-- HALAMAN 2: Detail Analisis Skor -->
<div class="page">
    <?php render_page_header('DETAIL ANALISIS SKOR (TOP 5)'); ?>
    
    <p style="margin-bottom: 20px;">Halaman ini menjabarkan rincian irisan kata (<i>terms matching</i>) yang mendasari perhitungan Cosine Similarity untuk 5 buku teratas.</p>

    <?php if (empty($top5)): ?>
        <p>Tidak ada data untuk dianalisis.</p>
    <?php else: ?>
        <?php foreach ($top5 as $index => $book): ?>
            <div style="background: #4f46e5; color: #fff; padding: 6px 12px; font-weight: bold; border-radius: 4px 4px 0 0; font-size: 10pt; margin-top: 15px;">
                Analisis #<?= $index + 1; ?>: <?= e($book['title']); ?> (Total Skor: <?= number_format($book['score'], 3); ?>)
            </div>
            <table style="margin-top: 0; border-top: none;">
                <thead>
                    <tr>
                        <th style="background:#f8fafc; color:#000; border-color:#ddd; width:30%;">Kata Ditemukan</th>
                        <th style="background:#f8fafc; color:#000; border-color:#ddd; width:40%;">Deskripsi Singkat / Keywords</th>
                        <th style="background:#f8fafc; color:#000; border-color:#ddd; width:30%;">Kategori</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="vertical-align: top;">
                            <?php 
                            $matches = $book['matches'] ?? [];
                            if (empty($matches)) {
                                echo '-';
                            } else {
                                foreach($matches as $m) echo "<span style='display:inline-block; background:#e2e8f0; padding:2px 6px; margin:2px; border-radius:4px; font-size:9pt;'>".e($m)."</span>";
                            }
                            ?>
                        </td>
                        <td style="vertical-align: top; font-size: 9pt;"><?= e(mb_substr($book['description'], 0, 150)) . '...'; ?></td>
                        <td style="vertical-align: top;"><?= e($book['category']); ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php render_page_footer(2); ?>

<!-- HALAMAN 3: Tren Pencarian -->
<div class="page">
    <?php render_page_header('TREN PENCARIAN & REKAPITULASI'); ?>

    <h3>Statistik Penggunaan Sistem</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 50%;">Indikator</th>
                <th style="width: 50%;">Nilai</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Pencarian Hari Ini</td>
                <td><?= $todaySearches; ?> kali</td>
            </tr>
            <tr>
                <td>Total Variasi Kata Kunci (Keseluruhan)</td>
                <td><?= count($topQueries); ?> kata kunci unik</td>
            </tr>
        </tbody>
    </table>

    <h3 style="margin-top: 30px;">Top 10 Kata Kunci Pencarian Terpopuler</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 10%; text-align: center;">No</th>
                <th style="width: 60%;">Kata Kunci</th>
                <th style="width: 30%; text-align: center;">Jumlah Pencarian</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($topQueries)): ?>
            <tr><td colspan="3" style="text-align:center;">Belum ada log pencarian.</td></tr>
            <?php else: ?>
                <?php foreach(array_slice($topQueries, 0, 10) as $idx => $q): ?>
                <tr>
                    <td style="text-align: center;"><?= $idx + 1; ?></td>
                    <td style="font-weight: 600;"><?= e(ucwords($q['query'])); ?></td>
                    <td style="text-align: center;"><?= $q['count']; ?> kali</td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="score-box" style="margin-top: 30px;">
        <h4>Catatan Pustakawan:</h4>
        <p style="margin: 0;">Tren pencarian ini dapat digunakan sebagai acuan untuk pengadaan buku baru pada tahun anggaran berikutnya. Kata kunci dengan frekuensi tinggi menunjukkan minat baca siswa yang dominan di Perpustakaan SDN Baru 06 Pagi.</p>
    </div>

    <?php render_page_footer(3); ?>

<!-- HALAMAN 4: Kesimpulan & Pengesahan -->
<div class="page">
    <?php render_page_header('KESIMPULAN DAN REKOMENDASI'); ?>

    <h3 style="margin-top: 30px;">Kesimpulan:</h3>
    <ul style="line-height: 1.8;">
        <li>Pencarian dengan kata kunci <strong>"<?= e($query); ?>"</strong> menghasilkan <strong><?= $totalRelevant; ?> buku</strong> yang relevan.</li>
        <li>Rata-rata skor kesesuaian adalah <strong><?= number_format($avgScore, 3); ?></strong>, yang secara matematis divalidasi oleh algoritma <em>Cosine Similarity</em>.</li>
        <li>Ditemukan <strong><?= $categoriesFound; ?> kategori</strong> berbeda, menunjukkan variasi topik yang memadai dari koleksi perpustakaan.</li>
        <?php if ($highestScore > 0.8): ?>
        <li>Tingkat relevansi tertinggi mencapai <?= number_format($highestScore, 3); ?>, menunjukkan bahwa perpustakaan memiliki koleksi yang sangat akurat dengan minat baca tersebut.</li>
        <?php endif; ?>
    </ul>

    <h3 style="margin-top: 40px;">Rekomendasi Tindak Lanjut:</h3>
    <ul style="line-height: 1.8;">
        <li>Prioritaskan pengadaan atau promosi (pajang di rak depan) buku dengan skor tertinggi dari analisis ini.</li>
        <li>Jika total buku ditemukan kurang dari 5, pertimbangkan untuk menambah koleksi perpustakaan dengan topik <strong>"<?= e($query); ?>"</strong>.</li>
        <li>Evaluasi berkala terhadap daftar <em>stopwords</em> sistem untuk memastikan kata sambung tidak mengurangi presisi hasil pencarian siswa.</li>
    </ul>

    <div class="signature">
        <div class="signature-box">
            <p><?= $simpleDateString; ?><br>Penanggung Jawab Perpustakaan,</p>
            <p class="name">Ririn Nur Hidayati, M.Pd</p>
        </div>
    </div>

    <?php render_page_footer(4); ?>

</body>
</html>
