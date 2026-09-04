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
        var labels = data.labels || {};

        // Bootstrap's .d-flex is display:flex !important, so it beats an inline
        // display:none. Toggle the class itself instead of fighting it.
        var showTrendFallback = function (show) {
            if (!trendFallback) { return; }
            trendFallback.classList.toggle('d-flex', show);
            trendFallback.style.display = show ? '' : 'none';
        };

        // Trend — 30 bars.
        var trendCanvas = document.getElementById('ur-trend-canvas');
        var trendFallback = document.getElementById('ur-trend-fallback');

        if (trendCanvas && data.trend && Array.isArray(data.trend) && data.trend.length) {
            var labels = data.trend.map(function (p) { return p.date.slice(5); }); // MM-DD
            var values = data.trend.map(function (p) { return p.count; });
            var hasData = values.some(function (v) { return v > 0; });

            if (hasChart && hasData) {
                showTrendFallback(false);
                trendCanvas.style.display = 'block';
                try {
                    // eslint-disable-next-line no-new
                    new window.Chart(trendCanvas, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: labels.sent || 'Sent',
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
                    showTrendFallback(true);
                    trendCanvas.style.display = 'none';
                }
            } else {
                // No chart or no data — show fallback (or empty message already rendered).
                if (!hasChart) {
                    showTrendFallback(true);
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
                            labels: [
                                labels.type1 || 'Not activated',
                                labels.type2 || 'Never logged in',
                                labels.type3 || 'Inactive'
                            ],
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
