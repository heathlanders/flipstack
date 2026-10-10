/**
 * Shared formatters for Analytics Hub pages.
 *
 * Postgres returns NUMERIC aggregates as strings via Laravel's JSON
 * encoder, so any KPI value reaching the Vue layer may be a string.
 * Every formatter coerces to Number first so .toFixed() / Intl never
 * crashes on a string input.
 */

export function toNum(v) {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
}

export function formatCurrency(value) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(toNum(value));
}

export function formatPercent(value, digits = 1) {
    return `${toNum(value).toFixed(digits)}%`;
}

export function formatNumber(value) {
    return new Intl.NumberFormat('en-US').format(toNum(value));
}

export function formatDecimal(value, digits = 1) {
    return toNum(value).toFixed(digits);
}

export function useAnalyticsFormatters() {
    return {
        toNum,
        formatCurrency,
        formatPercent,
        formatNumber,
        formatDecimal,
    };
}
