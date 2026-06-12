/**
 * charts.js — رسوم Chart.js للتحليلات
 */

'use strict';

const CHART_COLORS = {
    primary:  '#89CFF0',
    dark:     '#5DAED4',
    success:  '#10B981',
    warning:  '#F59E0B',
    danger:   '#EF4444',
    gray:     '#94A3B8',
};

// خيارات مشتركة
const BASE_OPTIONS = {
    responsive:          true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                font:        { family: 'Cairo', size: 12, weight: '600' },
                color:       '#64748B',
                padding:     18,
                boxWidth:    14,
                usePointStyle: true,
            }
        },
        tooltip: {
            rtl:           true,
            textDirection: 'rtl',
            titleFont:     { family: 'Cairo', size: 13 },
            bodyFont:      { family: 'Cairo', size: 12 },
            padding:       12,
            cornerRadius:  8,
            callbacks: {
                label: (ctx) => ` ${ctx.formattedValue} ${ctx.dataset.label || ''}`
            }
        }
    }
};

/**
 * رسم بياني خطي/شريطي — معاملات آخر 7 أيام
 */
function initTransactionsChart(canvasId, labels, deposits, withdrawals) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label:           'إيداع',
                    data:            deposits,
                    backgroundColor: 'rgba(137, 207, 240, 0.7)',
                    borderColor:     CHART_COLORS.primary,
                    borderWidth:     2,
                    borderRadius:    8,
                    borderSkipped:   false,
                },
                {
                    label:           'سحب',
                    data:            withdrawals,
                    backgroundColor: 'rgba(239, 68, 68, 0.5)',
                    borderColor:     CHART_COLORS.danger,
                    borderWidth:     2,
                    borderRadius:    8,
                    borderSkipped:   false,
                }
            ]
        },
        options: {
            ...BASE_OPTIONS,
            scales: {
                x: {
                    grid:   { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                    ticks:  { font: { family: 'Cairo', size: 11 }, color: '#94A3B8' }
                },
                y: {
                    grid:      { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                    ticks:     { font: { family: 'Cairo', size: 11 }, color: '#94A3B8' },
                    beginAtZero: true
                }
            }
        }
    });
}

/**
 * رسم بياني دائري — توزيع أنواع المعاملات
 */
function initTypesChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['إيداع', 'سحب', 'تعديل'],
            datasets: [{
                data,
                backgroundColor: [
                    CHART_COLORS.primary,
                    CHART_COLORS.danger,
                    CHART_COLORS.warning,
                ],
                borderWidth:     0,
                hoverOffset:     5,
            }]
        },
        options: {
            ...BASE_OPTIONS,
            cutout: '68%',
        }
    });
}

/**
 * رسم بياني شريطي أفقي — أفضل الوكلاء
 */
function initAgentsChart(canvasId, labels, values) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label:           'عدد المعاملات',
                data:            values,
                backgroundColor: 'rgba(137, 207, 240, 0.75)',
                borderColor:     CHART_COLORS.dark,
                borderWidth:     2,
                borderRadius:    6,
            }]
        },
        options: {
            ...BASE_OPTIONS,
            indexAxis: 'y',
            scales: {
                x: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { family: 'Cairo', size: 11 }, color: '#94A3B8' }, beginAtZero: true },
                y: { grid: { display: false },             ticks: { font: { family: 'Cairo', size: 11 }, color: '#475569'  } }
            }
        }
    });
}

// تصدير
window.initTransactionsChart = initTransactionsChart;
window.initTypesChart         = initTypesChart;
window.initAgentsChart        = initAgentsChart;
