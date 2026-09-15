# Sistem Rekomendasi Buku Berbasis Cosine Similarity

Project ini menggunakan PHP + MySQL dengan alur baru:
- halaman awal langsung dashboard untuk user umum
- user tidak perlu login
- admin memiliki halaman login terpisah untuk menambah, mengubah, dan menghapus data buku
- pencarian buku memakai cosine similarity dari kombinasi judul, penulis, kategori, deskripsi, dan keywords

## Struktur singkat
- `dashboard/` : halaman utama publik
- `books/` : katalog dan detail buku
- `admin/` : login dan CRUD buku
- `api/` : endpoint pencarian JSON
- `assets/` : CSS dan JavaScript
- `database.sql` : skema dan data awal

## Cara menjalankan
1. Import `database.sql` ke MySQL.
2. Pastikan database bernama `db_ta_new`.
3. Sesuaikan file `includes/functions.php` bila username/password MySQL Anda berbeda.
4. Jalankan project melalui `index.php`.

## Login admin default
- Email: `admin@local`
- Password: `admin`

## Catatan
- Pencarian akan menampilkan rekomendasi otomatis saat Anda mengetik.
- Klik chip kategori untuk menguji rekomendasi dengan cepat.
- Halaman detail buku menampilkan buku terkait berdasarkan cosine similarity.
