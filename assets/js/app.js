document.addEventListener('DOMContentLoaded', () => {
    const themeKey = 'ta-theme';
    const savedTheme = localStorage.getItem(themeKey);

    if (savedTheme === 'dark') {
        document.body.classList.add('theme-dark');
    }

    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('theme-dark');
            localStorage.setItem(themeKey, document.body.classList.contains('theme-dark') ? 'dark' : 'light');
        });
    }

    const navToggle = document.querySelector('[data-nav-toggle]');
    const nav = document.querySelector('[data-nav]');
    if (navToggle && nav) {
        navToggle.addEventListener('click', () => nav.classList.toggle('open'));
    }

    const form = document.getElementById('liveSearchForm');
    const input = document.getElementById('liveSearchInput');
    const results = document.getElementById('searchResults');
    const meta = document.getElementById('resultsMeta');
    if (form && input && results) {
        const endpoint = form.dataset.endpoint;
        const initialQuery = input.value.trim();
        let timer = null;

        const scoreLabel = (score) => {
            const percent = Math.max(0, Math.min(100, Math.round(score * 1000) / 10));
            if (percent >= 70) return `${percent}% cocok`;
            if (percent >= 45) return `${percent}% relevan`;
            if (percent >= 15) return `${percent}% terkait`;
            return `${percent}%`;
        };

        const renderCards = (books, query, message) => {
            if (!books || books.length === 0) {
                results.innerHTML = `
                    <div class="panel">
                        <div class="panel-body">
                            <h3 style="margin-top:0;">Tidak ada hasil</h3>
                            <p class="helper">${message || 'Coba kata kunci yang lain, misalnya matematika, sains, atau cerita.'}</p>
                        </div>
                    </div>
                `;
                return;
            }

            results.innerHTML = books.map((book) => {
                let coverHtml = '';
                if (book.cover_image && book.cover_image.trim() !== '') {
                    coverHtml = `
                        <div class="book-cover book-cover-image-wrap">
                            <img
                                src="${book.cover_image.replace(/"/g, '&quot;')}"
                                alt="${book.title.replace(/"/g, '&quot;')}"
                                class="book-cover-img"
                            >
                        </div>
                    `;
                } else {
                    const coverStyle = `background:${book.cover_bg};`;
                    coverHtml = `
                        <div class="book-cover book-cover-fallback" style="${coverStyle}">
                            <div class="book-cover-inner">
                                <div class="book-initial">${book.initial}</div>
                                <span class="book-category">${book.category}</span>
                            </div>
                        </div>
                    `;
                }

                const scoreBadge = book.score !== null && book.score !== undefined
                    ? `<span class="score-pill">${scoreLabel(book.score)}</span>`
                    : '';
                    
                let matchesHtml = '';
                if (book.matches && book.matches.length > 0) {
                    let matchesList = book.matches.slice(0, 3).join(', ');
                    if (book.matches.length > 3) matchesList += '...';
                    matchesHtml = `<div style="margin-top: 4px; font-size: 0.82rem; color: var(--muted);"><span style="background: rgba(16, 185, 129, 0.15); color: #059669; padding: 2px 6px; border-radius: 4px; font-weight: 700;">Topik:</span> ${matchesList}</div>`;
                }

                return `
                    <article class="book-card">
                        ${coverHtml}
                        <div class="book-card-body">
                            <div class="book-card-meta">
                                <span class="chip muted">${book.category}</span>
                                ${scoreBadge}
                            </div>
                            <h3 class="book-title">${book.title}</h3>
                            <p class="book-author">${book.author}${book.year ? ` • ${book.year}` : ''}</p>
                            <p class="book-description">${book.description}</p>
                            ${matchesHtml}
                            <div class="book-actions">
                                <a class="btn btn-soft" href="../books/detail.php?id=${book.id}">Lihat detail</a>
                            </div>
                        </div>
                    </article>
                `;
            }).join('');

            if (meta) {
                meta.textContent = message || (query ? `Menampilkan ${books.length} hasil terbaik untuk “${query}”.` : `Menampilkan ${books.length} buku terbaru.`);
            }
        };

        const fetchBooks = async (query) => {
            results.innerHTML = '<div class="grid-3 loading-skeleton"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div>';
            try {
                const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                renderCards(data.books || [], data.query || query, data.message || '');
            } catch (error) {
                results.innerHTML = `
                    <div class="panel">
                        <div class="panel-body">
                            <h3 style="margin-top:0;">Gagal memuat data</h3>
                            <p class="helper">Periksa koneksi atau konfigurasi server lokal Anda.</p>
                        </div>
                    </div>
                `;
            }
        };

        const onInput = () => {
            clearTimeout(timer);
            timer = setTimeout(() => fetchBooks(input.value.trim()), 240);
        };

        input.addEventListener('input', onInput);
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            fetchBooks(input.value.trim());
        });

        document.querySelectorAll('[data-search-chip]').forEach((chip) => {
            chip.addEventListener('click', () => {
                input.value = chip.getAttribute('data-query') || '';
                fetchBooks(input.value.trim());
            });
        });

        fetchBooks(initialQuery);
    }

    // Custom Logout Modal Logic
    const logoutLinks = document.querySelectorAll('a[href$="logout.php"]');
    const logoutModal = document.getElementById('logoutModal');
    const cancelLogoutBtn = document.getElementById('cancelLogoutBtn');
    const confirmLogoutBtn = document.getElementById('confirmLogoutBtn');

    if (logoutLinks.length > 0 && logoutModal && cancelLogoutBtn && confirmLogoutBtn) {
        logoutLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const logoutUrl = link.getAttribute('href');
                confirmLogoutBtn.setAttribute('href', logoutUrl);
                logoutModal.classList.remove('hidden');
            });
        });

        cancelLogoutBtn.addEventListener('click', () => {
            logoutModal.classList.add('hidden');
        });

        // Optional: Close modal if clicking outside the card
        logoutModal.addEventListener('click', (e) => {
            if (e.target === logoutModal) {
                logoutModal.classList.add('hidden');
            }
        });
    }
});
