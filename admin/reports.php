<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

render_header('Pusat Cetak Laporan', 'admin', 'reports', [
    'subtitle' => 'Pusat pembuatan dokumen laporan skripsi',
]);
?>

<section class="container">
    <div class="section-head" style="text-align: center; justify-content: center; margin-bottom: 40px; margin-top: 20px;">
        <div style="width: 100%;">
            <h2>Pusat Cetak Laporan (4-in-1)</h2>
            <p style="margin: 12px auto 0; max-width: 800px; font-size: 1.05rem;">Fitur ini akan menghasilkan 1 dokumen berisi 4 halaman laporan lengkap secara otomatis, yang terdiri dari Metrik Hasil Pencarian, Detail Algoritma Cosine, Tren Pencarian, dan Kesimpulan. Sangat cocok untuk lampiran Skripsi!</p>
        </div>
    </div>

    <div class="panel" style="max-width: 760px; margin: 0 auto; box-shadow: var(--shadow-strong); border-radius: 28px; background: linear-gradient(to bottom, var(--card), var(--bg-2)); border: 1px solid var(--border);">
        <div class="panel-body" style="padding: 40px;">
            <h3 style="margin-top: 0; margin-bottom: 30px; text-align: center; font-size: 1.5rem;">Cetak Dokumen Laporan Skripsi</h3>
            <form action="print-report.php" method="GET" target="_blank">
                <div class="field" style="margin-bottom: 28px;">
                    <label for="q" style="font-size: 1.05rem; margin-bottom: 6px;">Kata Kunci Analisis (Keyword)</label>
                    <input type="text" id="q" name="q" placeholder="Contoh: hewan, sihir, cinta" required style="padding: 18px 22px; font-size: 1.1rem; border-radius: 20px; background: var(--bg); box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                    <p class="helper" style="margin-top: 12px;">Kata kunci ini akan digunakan untuk menyimulasikan algoritma dan menghitung nilai kemiripan (Cosine Similarity) di Halaman 1 dan 2.</p>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; display: flex; justify-content: center; align-items: center; gap: 12px; padding: 20px; font-size: 1.15rem; border-radius: 20px; transition: transform 0.2s, box-shadow 0.2s;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Buat & Cetak Laporan (4 Halaman)
                </button>
            </form>
        </div>
    </div>
</section>

<?php render_footer(); ?>
