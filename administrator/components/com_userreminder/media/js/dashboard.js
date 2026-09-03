/**
 * UserReminder dashboard — Chart.js wiring.
 * Depends on: Joomla.getOptions('com_userreminder.dashboard') + Chart.js (optional).
 * If Chart is missing we keep the CSS fallback bars (server-rendered) visible.
 */
(function () {
    'use strict';

    function init() {
        if (typeof Joomla === 'undefined' || !Joomla.getOptions) {
            return;
        }

        var data = Joomla.getOptions('com_userreminder.dashboard', null);
        if (!data) {
            return;
        }

        var hasChart = typeof window.Chart !== 'undefined';

        // Trend — 30 bars.
        var trendCanvas = document.getElementById('ur-trend-canvas');
        var trendFallback = document.getElementById('ur-trend-fallback');

        if (trendCanvas && data.trend && Array.isArray(data.trend) && data.trend.length) {
            var labels = data.trend.map(function (p) { return p.date.slice(5); }); // MM-DD
            var values = data.trend.map(function (p) { return p.count; });
            var hasData = values.some(function (v) { return v > 0; });

            if (hasChart && hasData) {
                if (trendFallback) { trendFallback.style.display = 'none'; }
                trendCanvas.style.display = 'block';
                try {
                    // eslint-disable-next-line no-new
                    new window.Chart(trendCanvas, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Sent',
                                data: values,
                                backgroundColor: 'rgba(13,110,253,0.85)',
                                borderColor: 'rgba(13,110,253,1)',
                                borderWidth: 1,
                                borderRadius: 2,
                                maxBarThickness: 22
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: { enabled: true }
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10, font: { size: 10 } } },
                                y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.06)' } }
                            }
                        }
                    });
                } catch (e) {
                    // Fall back to CSS bars on error.
                    if (trendFallback) { trendFallback.style.display = 'flex'; }
                    trendCanvas.style.display = 'none';
                }
            } else {
                // No chart or no data — show fallback (or empty message already rendered).
                if (!hasChart && trendFallback) {
                    trendFallback.style.display = 'flex';
                }
                if (!hasData && trendCanvas) {
                    trendCanvas.style.display = 'none';
                }
            }
        }

        // By-type donut.
        var donutCanvas = document.getElementById('ur-bytype-canvas');
        if (donutCanvas && data.byType) {
            var byType = data.byType;
            var t1 = byType['1'] || byType[1] || 0;
            var t2 = byType['2'] || byType[2] || 0;
            var t3 = byType['3'] || byType[3] || 0;
            var total = t1 + t2 + t3;

            if (hasChart && total > 0) {
                donutCanvas.style.display = 'block';
                var donutFallback = document.getElementById('ur-bytype-fallback');
                if (donutFallback) { donutFallback.style.display = 'none'; }
                try {
                    // eslint-disable-next-line no-new
                    new window.Chart(donutCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: ['Not activated', 'Never logged in', 'Inactive'],
                            datasets: [{
                                data: [t1, t2, t3],
                                backgroundColor: ['#ffb340', '#0dcaf0', '#6c757d'],
                                borderWidth: 1,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '62%',
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, padding: 12 } }
                            }
                        }
                    });
                } catch (e) {
                    donutCanvas.style.display = 'none';
                    if (donutFallback) { donutFallback.style.display = 'block'; }
                }
            } else if (total === 0) {
                donutCanvas.style.display = 'none';
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
