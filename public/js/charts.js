/*
 * Chart kit.
 *
 * Chart.js out of the box draws a boxed plot with full gridlines, a legend and
 * hard-cornered bars — the look of a 2013 reporting tool. Everything here
 * exists to make the charts in this app read as one deliberate set: colours
 * come from the CSS tokens rather than being restated as hex strings, axes are
 * stripped back to a single dashed horizontal rule, and the money formatting
 * is shared with the rest of the interface.
 *
 * Pages call ftChart.area / .bars / .donut / .sparkline rather than building
 * Chart.js config, so a change to the house style is one edit here.
 */
(function () {
    'use strict';

    if (!window.Chart) return;

    var css = getComputedStyle(document.documentElement);
    var token = function (name, fallback) {
        var value = css.getPropertyValue(name);
        return value ? value.trim() : fallback;
    };

    var colors = {
        text: token('--muted-foreground', '#64748b'),
        subtle: token('--subtle', '#94a3b8'),
        border: token('--border', '#e2e8f0'),
        card: token('--card', '#ffffff'),
        foreground: token('--foreground', '#0f172a'),
        income: token('--income', '#16a34a'),
        expense: token('--expense', '#dc2626'),
        debt: token('--debt', '#ea580c'),
        primary: token('--primary', '#1e293b'),
    };

    // The series palette, in the order categories get assigned. Legends beside
    // a chart read the same list so a swatch and its slice cannot drift apart.
    var palette = [1, 2, 3, 4, 5, 6, 7, 8].map(function (n) {
        return token('--chart-' + n, '#64748b');
    });

    var money = function (value) {
        return '₹' + Number(value).toLocaleString('en-IN', { maximumFractionDigits: 0 });
    };

    // Axis ticks need to stay short or they push the plot area around: a lakh
    // is "1.2L", not "1,20,000".
    var compact = function (value) {
        var n = Math.abs(Number(value));

        if (n >= 10000000) return '₹' + (value / 10000000).toFixed(1).replace(/\.0$/, '') + 'Cr';
        if (n >= 100000) return '₹' + (value / 100000).toFixed(1).replace(/\.0$/, '') + 'L';
        if (n >= 1000) return '₹' + (value / 1000).toFixed(n >= 10000 ? 0 : 1).replace(/\.0$/, '') + 'k';

        return '₹' + Math.round(value);
    };

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 11;
    Chart.defaults.color = colors.text;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.plugins.legend.display = false;

    Chart.defaults.plugins.tooltip.backgroundColor = colors.foreground;
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.displayColors = false;
    Chart.defaults.plugins.tooltip.titleFont = { weight: '600', size: 11 };
    Chart.defaults.plugins.tooltip.bodyFont = { size: 12 };
    Chart.defaults.plugins.tooltip.caretSize = 5;
    Chart.defaults.plugins.tooltip.boxPadding = 4;

    /**
     * A vertical gradient under a line. Built against the chart's own pixel
     * area, so it has to be created per-render rather than once up front.
     */
    function fill(ctx, area, color) {
        if (!area) return 'transparent';

        var gradient = ctx.createLinearGradient(0, area.top, 0, area.bottom);
        gradient.addColorStop(0, tint(color, 0.22));
        gradient.addColorStop(1, tint(color, 0));

        return gradient;
    }

    // oklch() values from the tokens cannot be alpha-adjusted by string surgery
    // the way hex can, so the colour is pushed through a canvas to get rgba.
    var probe = document.createElement('canvas').getContext('2d');
    function tint(color, alpha) {
        probe.fillStyle = '#000';
        probe.fillStyle = color;

        var resolved = probe.fillStyle;

        if (resolved.charAt(0) === '#') {
            var r = parseInt(resolved.substr(1, 2), 16);
            var g = parseInt(resolved.substr(3, 2), 16);
            var b = parseInt(resolved.substr(5, 2), 16);
            return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
        }

        if (resolved.indexOf('rgb') === 0) {
            return resolved.replace(/^rgba?\(([^)]+)\)$/, function (_, inner) {
                var parts = inner.split(',').slice(0, 3).map(function (p) { return p.trim(); });
                return 'rgba(' + parts.join(',') + ',' + alpha + ')';
            });
        }

        return resolved;
    }

    /** One dashed horizontal rule per gridline, no vertical grid, no borders. */
    function scales(options) {
        options = options || {};

        return {
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: {
                    color: colors.subtle,
                    maxRotation: 0,
                    autoSkipPadding: 12,
                    padding: 6,
                },
                stacked: !!options.stacked,
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: colors.border,
                    drawTicks: false,
                    lineWidth: 1,
                },
                border: { display: false, dash: [3, 4] },
                ticks: {
                    color: colors.subtle,
                    padding: 8,
                    maxTicksLimit: 5,
                    callback: function (value) { return compact(value); },
                },
                stacked: !!options.stacked,
            },
        };
    }

    var ftChart = {
        palette: palette,
        colors: colors,
        money: money,
        compact: compact,
        tint: tint,

        /**
         * Trend chart. Smooth line, gradient body, no dots until hovered —
         * the shape is the message and a row of markers only adds noise.
         */
        area: function (el, config) {
            if (!el) return null;

            var series = config.datasets.map(function (set, i) {
                var color = set.color || palette[i % palette.length];

                return {
                    label: set.label,
                    data: set.data,
                    borderColor: color,
                    borderWidth: 2,
                    tension: 0.35,
                    fill: config.fill === false ? false : (set.fill !== false),
                    backgroundColor: function (context) {
                        return fill(context.chart.ctx, context.chart.chartArea, color);
                    },
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHoverBorderWidth: 2,
                    pointHoverBorderColor: colors.card,
                    pointHoverBackgroundColor: color,
                    pointBackgroundColor: color,
                };
            });

            return new Chart(el, {
                type: 'line',
                data: { labels: config.labels, datasets: series },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    layout: { padding: { top: 8 } },
                    scales: scales(),
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (c) {
                                    return c.dataset.label + ': ' + money(c.parsed.y);
                                },
                            },
                        },
                    },
                },
            });
        },

        /**
         * Bars. Horizontal by default for ranked lists, because comparing
         * lengths that all start at the same edge is easier than comparing
         * columns, and long category names have room to be read.
         */
        bars: function (el, config) {
            if (!el) return null;

            var horizontal = config.horizontal !== false;
            var multi = config.datasets && config.datasets.length > 1;

            var series = (config.datasets
                || [{ label: config.label || 'Amount', data: config.data, color: config.color }])
                .map(function (set, i) {
                    return {
                        label: set.label,
                        data: set.data,
                        backgroundColor: set.color
                            || (config.colorPerBar
                                ? set.data.map(function (_, j) { return palette[j % palette.length]; })
                                : palette[i % palette.length]),
                        borderRadius: 5,
                        borderSkipped: false,
                        // Bars sized to the data: three categories should not
                        // render as three slabs filling the panel.
                        barThickness: config.thickness || (horizontal ? 18 : undefined),
                        maxBarThickness: 44,
                    };
                });

            var axes = scales({ stacked: !!config.stacked });

            if (horizontal) {
                // Money runs along x when the bars lie down, so the formatted
                // and plain axes swap over with them.
                var value = axes.y;
                var category = axes.x;

                value.ticks.callback = function (v) { return compact(v); };
                category.ticks.callback = undefined;

                axes = { x: value, y: category };
                axes.y.grid = { display: false };
                axes.x.grid = { color: colors.border, drawTicks: false };
            }

            return new Chart(el, {
                type: 'bar',
                data: { labels: config.labels, datasets: series },
                options: {
                    responsive: true,
                    indexAxis: horizontal ? 'y' : 'x',
                    interaction: { mode: 'index', intersect: false },
                    scales: axes,
                    plugins: {
                        legend: { display: !!multi, position: 'bottom', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', padding: 14 } },
                        tooltip: {
                            callbacks: {
                                label: function (c) {
                                    var amount = money(horizontal ? c.parsed.x : c.parsed.y);
                                    return multi ? c.dataset.label + ': ' + amount : amount;
                                },
                            },
                        },
                    },
                },
            });
        },

        /** Ring, sized to leave room for a total in the middle. */
        donut: function (el, config) {
            if (!el) return null;

            var total = config.data.reduce(function (sum, n) { return sum + Number(n); }, 0);

            return new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: config.labels,
                    datasets: [{
                        data: config.data,
                        backgroundColor: config.colors || palette.slice(0, config.data.length),
                        borderWidth: 2,
                        borderColor: colors.card,
                        hoverOffset: 6,
                        hoverBorderColor: colors.card,
                    }],
                },
                options: {
                    responsive: true,
                    cutout: config.cutout || '74%',
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (c) {
                                    var share = total > 0 ? Math.round((c.parsed / total) * 100) : 0;
                                    return money(c.parsed) + '  ·  ' + share + '%';
                                },
                            },
                        },
                    },
                },
            });
        },

        /**
         * Sparkline for stat cards: shape only, no axes, no interaction. It
         * answers "which way has this been going" without asking for a second
         * chart panel.
         */
        sparkline: function (el, data, tone) {
            if (!el || !data || data.length < 2) return null;

            var color = colors[tone] || colors.primary;

            return new Chart(el, {
                type: 'line',
                data: {
                    labels: data.map(function (_, i) { return i; }),
                    datasets: [{
                        data: data,
                        borderColor: color,
                        borderWidth: 1.75,
                        tension: 0.4,
                        pointRadius: 0,
                        fill: true,
                        backgroundColor: function (context) {
                            return fill(context.chart.ctx, context.chart.chartArea, color);
                        },
                    }],
                },
                options: {
                    responsive: true,
                    events: [],
                    layout: { padding: 1 },
                    scales: {
                        x: { display: false },
                        y: { display: false, beginAtZero: true },
                    },
                    plugins: { tooltip: { enabled: false } },
                },
            });
        },
    };

    window.ftChart = ftChart;

    // Kept for the pages that still address the palette and formatter directly.
    window.ftChartPalette = palette;
    window.ftMoney = money;
})();
