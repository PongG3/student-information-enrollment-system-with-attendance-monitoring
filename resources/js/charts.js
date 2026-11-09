import Chart from 'chart.js/auto';

// Colors match the SIES design.
const palette = {
    navy: '#0d2a52',
    blue: '#1a62a8',
    teal: '#2bb6a8',
    green: '#1c9d77',
    amber: '#e8a33d',
    red: '#d6455d',
    slate: '#8a97ab',
};
const color = (name) => palette[name] ?? name;

Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
Chart.defaults.color = '#5b6b82';
Chart.defaults.borderColor = '#e3eaf5';

document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
    const config = JSON.parse(canvas.dataset.chart);
    const isDoughnut = config.type === 'doughnut';
    const isLine = config.type === 'line';

    const datasets = config.datasets.map((set) => {
        const dataset = { label: set.label, data: set.data };

        if (isDoughnut) {
            dataset.backgroundColor = (set.colors ?? []).map(color);
            dataset.borderColor = '#ffffff';
            dataset.borderWidth = 2;
        } else if (isLine) {
            dataset.borderColor = color(set.color);
            dataset.backgroundColor = color(set.color) + '22';
            dataset.fill = set.fill ?? false;
            dataset.tension = 0.3;
            dataset.pointRadius = 4;
            dataset.pointHoverRadius = 6;
            dataset.spanGaps = true;
        } else {
            dataset.backgroundColor = color(set.color);
            dataset.borderRadius = 6;
            dataset.maxBarThickness = 38;
        }

        return dataset;
    });

    const suffix = config.percent ? '%' : '';
    const sum = (list) => list.reduce((total, value) => total + Number(value || 0), 0);

    // Doughnut legend: "Present 12 (80%)", always in the same order.
    const legendLabels = isDoughnut
        ? {
            usePointStyle: true, boxWidth: 8,
            generateLabels: (chart) => {
                const set = chart.data.datasets[0];
                const total = sum(set.data);
                return chart.data.labels.map((label, index) => {
                    const value = Number(set.data[index] || 0);
                    const share = total > 0 ? Math.round((value / total) * 100) : 0;
                    return {
                        text: `${label} ${value} (${share}%)`,
                        fillStyle: set.backgroundColor[index], strokeStyle: set.backgroundColor[index],
                        pointStyle: 'circle', index, hidden: !chart.getDataVisibility(index),
                        fontColor: '#5b6b82',
                    };
                });
            },
        }
        : { usePointStyle: true, boxWidth: 8 };

    // The total in the middle of a doughnut.
    const centerTotal = {
        id: 'centerTotal',
        afterDraw(chart) {
            if (!isDoughnut) return;
            const { ctx, chartArea } = chart;
            const set = chart.data.datasets[0];
            const total = sum(set.data.filter((_, index) => chart.getDataVisibility(index)));
            const x = (chartArea.left + chartArea.right) / 2;
            const y = (chartArea.top + chartArea.bottom) / 2;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#0d2a52';
            ctx.font = `700 26px ${Chart.defaults.font.family}`;
            ctx.fillText(String(total), x, y - 8);
            ctx.fillStyle = '#5b6b82';
            ctx.font = `12px ${Chart.defaults.font.family}`;
            ctx.fillText(config.centerLabel ?? 'total', x, y + 14);
            ctx.restore();
        },
    };

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: isDoughnut || datasets.length > 1,
                position: 'bottom',
                labels: legendLabels,
            },
            tooltip: {
                callbacks: {
                    label: (context) => {
                        const label = isDoughnut ? context.label : context.dataset.label;
                        return `${label}: ${context.formattedValue}${suffix}`;
                    },
                },
            },
        },
    };

    if (isDoughnut) {
        options.cutout = '62%';
    } else {
        options.indexAxis = config.horizontal ? 'y' : 'x';
        const valueAxis = {
            beginAtZero: true,
            ticks: { precision: 0, callback: (value) => value + suffix },
        };
        if (config.percent) valueAxis.max = 100;

        options.scales = config.horizontal
            ? { x: valueAxis, y: { grid: { display: false } } }
            : { y: valueAxis, x: { grid: { display: false } } };
    }

    new Chart(canvas, {
        type: config.type,
        data: { labels: config.labels, datasets },
        options,
        plugins: [centerTotal],
    });
});
