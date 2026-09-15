<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$conn = db();
$stats = stats_overview();

// Ambil 10 pencarian teratas
$topQueries = [];
$res = $conn->query("SELECT query, COUNT(*) as count FROM search_logs GROUP BY query ORDER BY count DESC LIMIT 10");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $topQueries[] = $row;
    }
}

// Data total hari ini
$todaySearches = 0;
$resToday = $conn->query("SELECT COUNT(*) as total FROM search_logs WHERE DATE(created_at) = CURDATE()");
if ($resToday) {
    $todaySearches = (int) $resToday->fetch_assoc()['total'];
}

// Data total minggu ini
$weekSearches = 0;
$resWeek = $conn->query("SELECT COUNT(*) as total FROM search_logs WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
if ($resWeek) {
    $weekSearches = (int) $resWeek->fetch_assoc()['total'];
}

$labels = array_column($topQueries, 'query');
$data = array_column($topQueries, 'count');

// Ambil distribusi kategori buku
$tempDist = [];
$totalBooksCat = 0;
$resCat = $conn->query("SELECT category, COUNT(*) as count FROM books WHERE deleted_at IS NULL GROUP BY category ORDER BY count DESC");
if ($resCat) {
    while ($row = $resCat->fetch_assoc()) {
        $count = (int) $row['count'];
        $tempDist[] = [
            'category' => $row['category'] ?: 'Tanpa Kategori',
            'count' => $count
        ];
        $totalBooksCat += $count;
    }
}

$categoryDist = [];
$lainnyaCount = 0;
foreach ($tempDist as $item) {
    $percent = round(($item['count'] / max(1, $totalBooksCat)) * 100);
    if ($percent < 1) {
        $lainnyaCount += $item['count'];
    } else {
        $categoryDist[] = $item;
    }
}

if ($lainnyaCount > 0) {
    $categoryDist[] = [
        'category' => 'Lainnya',
        'count' => $lainnyaCount
    ];
}

$catLabels = array_column($categoryDist, 'category');
$catData = array_column($categoryDist, 'count');

$success = flash_get('success');
$error = flash_get('error');

render_header('Dashboard Admin', 'admin', 'dashboard', [
    'subtitle' => 'Panel admin',
]);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<section class="container">

    <div class="section-head">
        <div>
            <h2>Dashboard Admin</h2>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="notice success"><?= e($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice error"><?= e($error); ?></div>
    <?php endif; ?>

    <!-- Enlarged Metric Boxes -->
    <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 32px;">
        <div class="metric" style="flex: 1; min-width: 280px; padding: 32px 24px; background: #0b5394; position: relative; overflow: hidden; border-radius: 12px; border: none; box-shadow: 0 10px 20px rgba(11, 83, 148, 0.3);">
            <div style="position: relative; z-index: 2; text-align: left;">
                <span style="font-size: 1.15rem; color: rgba(255,255,255,0.8); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Total Buku</span>
                <strong style="font-size: 3.5rem; color: #ffffff; display: block; font-weight: 900; line-height: 1;"><?= (int) $stats['books']; ?></strong>
            </div>
            <!-- Watermark Icon: Book -->
            <svg xmlns="http://www.w3.org/2000/svg" width="140" height="140" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; right: -15px; bottom: -25px; transform: rotate(-10deg);">
                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>
            </svg>
        </div>

        <div class="metric" style="flex: 1; min-width: 280px; padding: 32px 24px; background: #e53935; position: relative; overflow: hidden; border-radius: 12px; border: none; box-shadow: 0 10px 20px rgba(229, 57, 53, 0.3);">
            <div style="position: relative; z-index: 2; text-align: left;">
                <span style="font-size: 1.15rem; color: rgba(255,255,255,0.8); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Kategori</span>
                <strong style="font-size: 3.5rem; color: #ffffff; display: block; font-weight: 900; line-height: 1;"><?= (int) $stats['categories']; ?></strong>
            </div>
            <!-- Watermark Icon: Tags -->
            <svg xmlns="http://www.w3.org/2000/svg" width="140" height="140" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; right: -15px; bottom: -25px; transform: rotate(10deg);">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
        </div>

        <div class="metric" style="flex: 1; min-width: 280px; padding: 32px 24px; background: #f9a825; position: relative; overflow: hidden; border-radius: 12px; border: none; box-shadow: 0 10px 20px rgba(249, 168, 37, 0.3);">
            <div style="position: relative; z-index: 2; text-align: left;">
                <span style="font-size: 1.15rem; color: rgba(255,255,255,0.9); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Total Pencarian</span>
                <strong style="font-size: 3.5rem; color: #ffffff; display: block; font-weight: 900; line-height: 1;"><?= (int) $stats['searches']; ?></strong>
            </div>
            <!-- Watermark Icon: Search -->
            <svg xmlns="http://www.w3.org/2000/svg" width="140" height="140" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; right: -15px; bottom: -25px; transform: rotate(-5deg);">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
        </div>
    </div>

    <!-- Category Pie Chart Section -->
    <div class="section" style="margin-bottom: 40px;">
        <div class="panel">
            <div class="panel-body">
                <h3>Proporsi Kategori Buku</h3>
                <div style="position: relative; height: 650px; width: 100%; margin-top: 20px;">
                    <canvas id="categoryChart"></canvas>
                </div>
                <?php if (empty($catLabels)): ?>
                    <div class="notice" style="margin-top: 20px;">Belum ada buku yang terekam.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Analytics Section replaces Buku Terbaru -->
    <div class="section">
        <div class="section-head">
            <div>
                <h2>Analitik Pencarian</h2>
                <p style="white-space: nowrap; overflow: visible;">Memantau kata kunci atau topik buku yang paling sering dicari oleh siswa.</p>
            </div>
        </div>

        <div style="display: flex; gap: 24px; margin-bottom: 24px; flex-wrap: wrap;">
            <div class="metric" style="flex: 1; min-width: 200px; padding: 24px 20px; background: #0b5394; position: relative; overflow: hidden; border-radius: 12px; border: none; box-shadow: 0 10px 20px rgba(11, 83, 148, 0.3);">
                <div style="position: relative; z-index: 2; text-align: left;">
                    <span style="font-size: 0.95rem; color: rgba(255,255,255,0.8); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Pencarian Hari Ini</span>
                    <strong style="font-size: 2.8rem; color: #ffffff; display: block; font-weight: 900; line-height: 1;"><?= $todaySearches; ?></strong>
                </div>
                <!-- Watermark Icon: Clock -->
                <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; right: -10px; bottom: -15px; transform: rotate(-10deg);">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>

            <div class="metric" style="flex: 1; min-width: 200px; padding: 24px 20px; background: #e53935; position: relative; overflow: hidden; border-radius: 12px; border: none; box-shadow: 0 10px 20px rgba(229, 57, 53, 0.3);">
                <div style="position: relative; z-index: 2; text-align: left;">
                    <span style="font-size: 0.95rem; color: rgba(255,255,255,0.8); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Pencarian Minggu Ini</span>
                    <strong style="font-size: 2.8rem; color: #ffffff; display: block; font-weight: 900; line-height: 1;"><?= $weekSearches; ?></strong>
                </div>
                <!-- Watermark Icon: Calendar -->
                <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; right: -10px; bottom: -15px; transform: rotate(10deg);">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>

            <div class="metric" style="flex: 1; min-width: 200px; padding: 24px 20px; background: #f9a825; position: relative; overflow: hidden; border-radius: 12px; border: none; box-shadow: 0 10px 20px rgba(249, 168, 37, 0.3);">
                <div style="position: relative; z-index: 2; text-align: left;">
                    <span style="font-size: 0.95rem; color: rgba(255,255,255,0.9); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Topik Terpopuler</span>
                    <strong style="font-size: 2.8rem; color: #ffffff; display: block; font-weight: 900; line-height: 1;"><?= count($labels); ?></strong>
                </div>
                <!-- Watermark Icon: Trending -->
                <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; right: -10px; bottom: -15px; transform: rotate(-5deg);">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                    <polyline points="17 6 23 6 23 12"/>
                </svg>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <h3>Top 10 Kata Kunci Pencarian</h3>
                <div style="position: relative; height:450px; width:100%; margin-top: 20px;">
                    <canvas id="searchChart"></canvas>
                </div>
                <?php if (empty($labels)): ?>
                    <div class="notice" style="margin-top: 20px;">Belum ada data pencarian yang terekam. Cobalah mencari beberapa buku di Dashboard publik.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const labels = <?= json_encode($labels); ?>;
    const data = <?= json_encode($data); ?>;
    
    if (labels.length > 0) {
        const isDarkInit = document.body.classList.contains('theme-dark');
        const getTextColor = (dark) => dark ? '#cbd5e1' : '#475569';
        const getGridColor = (dark) => dark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(148, 163, 184, 0.4)';

        const getOrCreateSearchTooltip = (chart) => {
            let tooltipEl = chart.canvas.parentNode.querySelector('div.search-tooltip');
            if (!tooltipEl) {
                tooltipEl = document.createElement('div');
                tooltipEl.className = 'search-tooltip';
                tooltipEl.style.background = 'var(--bg-2)';
                tooltipEl.style.borderRadius = '8px';
                tooltipEl.style.opacity = 1;
                tooltipEl.style.pointerEvents = 'none';
                tooltipEl.style.position = 'absolute';
                tooltipEl.style.transform = 'translate(-50%, -100%)';
                tooltipEl.style.transition = 'all .1s ease';
                tooltipEl.style.boxShadow = 'var(--shadow)';
                tooltipEl.style.border = '3px solid transparent';
                tooltipEl.style.padding = '12px 24px';
                tooltipEl.style.textAlign = 'center';
                tooltipEl.style.zIndex = '999';
                chart.canvas.parentNode.appendChild(tooltipEl);
            }
            return tooltipEl;
        };

        const externalSearchTooltipHandler = (context) => {
            const {chart, tooltip} = context;
            const tooltipEl = getOrCreateSearchTooltip(chart);

            if (tooltip.opacity === 0) {
                tooltipEl.style.opacity = 0;
                return;
            }

            if (tooltip.body) {
                const dataPoint = tooltip.dataPoints[0];
                const color = dataPoint.dataset.backgroundColor[dataPoint.dataIndex];
                const value = dataPoint.raw;

                tooltipEl.innerHTML = `
                    <div style="font-size: 2.2rem; font-weight: 900; color: ${color}; line-height: 1;">${value}</div>
                    <div style="font-size: 0.9rem; color: var(--muted); font-weight: 600; margin-top: 6px;">Total Pencarian</div>
                `;
                tooltipEl.style.borderColor = color;
            }

            const {offsetLeft: positionX, offsetTop: positionY} = chart.canvas;
            tooltipEl.style.opacity = 1;
            tooltipEl.style.left = positionX + tooltip.caretX + 'px';
            tooltipEl.style.top = positionY + tooltip.caretY - 15 + 'px';
        };

        const ctx = document.getElementById('searchChart');
        const searchChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Pencarian',
                    data: data,
                    backgroundColor: [
                        '#0A396A', '#CC0000', '#FFCC00', '#0070D2', '#8B0000', 
                        '#10B981', '#F97316', '#8B5CF6', '#EC4899', '#14B8A6'
                    ],
                    barThickness: 60,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { top: 20 }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: false,
                        external: externalSearchTooltipHandler
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            color: getTextColor(isDarkInit),
                            font: { family: "'Inter', sans-serif", weight: '700', size: 12 },
                            padding: 10,
                            maxRotation: 0,
                            autoSkip: false,
                            callback: function(value, index, values) {
                                let label = this.getLabelForValue(value);
                                if (!label) return '';
                                
                                label = label.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
                                
                                const words = label.split(' ');
                                const lines = [];
                                let currentLine = '';
                                
                                words.forEach(word => {
                                    if ((currentLine + word).length > 12) {
                                        if (currentLine.length > 0) {
                                            lines.push(currentLine.trim());
                                        }
                                        currentLine = word + ' ';
                                    } else {
                                        currentLine += word + ' ';
                                    }
                                });
                                lines.push(currentLine.trim());
                                return lines;
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: getGridColor(isDarkInit),
                            drawBorder: false
                        },
                        ticks: {
                            stepSize: 1,
                            precision: 0,
                            color: getTextColor(isDarkInit),
                            font: { family: "'Inter', sans-serif", weight: '700', size: 12 },
                            padding: 10
                        }
                    }
                }
            }
        });

        // Update chart colors on theme toggle
        const themeBtn = document.getElementById('themeToggle');
        if (themeBtn) {
            themeBtn.addEventListener('click', () => {
                setTimeout(() => {
                    const dark = document.body.classList.contains('theme-dark');
                    const cText = getTextColor(dark);
                    const cGrid = getGridColor(dark);
                    
                    searchChart.options.scales.x.ticks.color = cText;
                    searchChart.options.scales.y.ticks.color = cText;
                    searchChart.options.scales.y.grid.color = cGrid;
                    
                    searchChart.update();
                }, 50);
            });
        }
    }

    const catLabels = <?= json_encode($catLabels); ?>;
    const catData = <?= json_encode($catData); ?>;
    
    if (catLabels.length > 0) {
        const getOrCreateTooltip = (chart) => {
            let tooltipEl = chart.canvas.parentNode.querySelector('div.chartjs-tooltip');

            if (!tooltipEl) {
                tooltipEl = document.createElement('div');
                tooltipEl.className = 'chartjs-tooltip';
                tooltipEl.style.background = 'var(--bg-2)';
                tooltipEl.style.borderRadius = '12px';
                tooltipEl.style.opacity = 1;
                tooltipEl.style.pointerEvents = 'none';
                tooltipEl.style.position = 'absolute';
                tooltipEl.style.transform = 'translate(-50%, -100%)';
                tooltipEl.style.transition = 'all .1s ease';
                tooltipEl.style.boxShadow = 'var(--shadow)';
                tooltipEl.style.border = '2px solid transparent';
                tooltipEl.style.padding = '16px 24px';
                tooltipEl.style.textAlign = 'center';
                tooltipEl.style.minWidth = '140px';
                tooltipEl.style.zIndex = '999';

                chart.canvas.parentNode.appendChild(tooltipEl);
            }

            return tooltipEl;
        };

        const externalTooltipHandler = (context) => {
            const {chart, tooltip} = context;
            const tooltipEl = getOrCreateTooltip(chart);

            if (tooltip.opacity === 0) {
                tooltipEl.style.opacity = 0;
                return;
            }

            if (tooltip.body) {
                const dataPoint = tooltip.dataPoints[0];
                const dataset = dataPoint.dataset;
                const color = dataset.backgroundColor[dataPoint.dataIndex];
                const value = dataPoint.raw;
                let total = 0;
                dataset.data.forEach(d => total += d);
                const percentage = Math.round((value / total) * 100) + '%';
                const label = dataPoint.label;

                tooltipEl.innerHTML = `
                    <div style="font-size: 0.85rem; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.05em;">${label}</div>
                    <div style="font-size: 2.5rem; font-weight: 900; color: ${color}; line-height: 1.1; margin-bottom: 2px;">${percentage}</div>
                    <div style="font-size: 0.95rem; color: var(--text); font-weight: 600;">${value} Buku</div>
                `;
                tooltipEl.style.borderColor = color;
            }

            const {offsetLeft: positionX, offsetTop: positionY} = chart.canvas;

            tooltipEl.style.opacity = 1;
            tooltipEl.style.left = positionX + tooltip.caretX + 'px';
            tooltipEl.style.top = positionY + tooltip.caretY - 20 + 'px';
        };

        const ctxPie = document.getElementById('categoryChart');
        
        // Buat pattern garis miring untuk "Lainnya"
        const createStripePattern = () => {
            const patternCanvas = document.createElement('canvas');
            patternCanvas.width = 16;
            patternCanvas.height = 16;
            const pctx = patternCanvas.getContext('2d');
            
            // Warna dasar
            pctx.fillStyle = '#94a3b8'; // Slate 400
            pctx.fillRect(0, 0, 16, 16);
            
            // Garis miring
            pctx.lineWidth = 4;
            pctx.strokeStyle = '#64748b'; // Slate 500
            pctx.beginPath();
            pctx.moveTo(0, 16);
            pctx.lineTo(16, 0);
            pctx.moveTo(-8, 8);
            pctx.lineTo(8, -8);
            pctx.moveTo(8, 24);
            pctx.lineTo(24, 8);
            pctx.stroke();
            
            return pctx.createPattern(patternCanvas, 'repeat');
        };

        const stripePattern = createStripePattern();
        const baseColors = ['#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316', '#14b8a6', '#6366f1'];
        
        const backgroundColors = catLabels.map((label, index) => {
            if (label === 'Lainnya') {
                return stripePattern;
            }
            return baseColors[index % baseColors.length];
        });

        new Chart(ctxPie, {
            type: 'pie',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catData,
                    backgroundColor: backgroundColors,
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: 0
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 24,
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            font: { family: "'Inter', sans-serif", weight: '700', size: 13 }
                        }
                    },
                    tooltip: {
                        enabled: false,
                        external: externalTooltipHandler
                    }
                }
            }
        });
    }
});
</script>

<?php render_footer(); ?>