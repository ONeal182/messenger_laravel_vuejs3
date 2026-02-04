const MONTH_IN_DAYS = 30
const YEAR_IN_MONTHS = 12

export function formatDateLabel(dateLike) {
    const date = normalizeDate(dateLike)
    if (!date) return ''

    const today = new Date()
    const yesterday = new Date()
    yesterday.setDate(yesterday.getDate() - 1)

    if (isSameDay(date, today)) return 'Сегодня'
    if (isSameDay(date, yesterday)) return 'Вчера'

    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
    })
}

export function formatLastSeen(dateLike, nowTs = Date.now()) {
    const date = normalizeDate(dateLike)
    if (!date) return ''

    const diffMs = nowTs - date.getTime()
    const seconds = Math.floor(diffMs / 1000)
    const minutes = Math.floor(seconds / 60)
    const hours = Math.floor(minutes / 60)
    const days = Math.floor(hours / 24)
    const months = Math.floor(days / MONTH_IN_DAYS)
    const years = Math.floor(months / YEAR_IN_MONTHS)

    if (seconds < 10) return 'online'
    if (seconds < 60) return 'был(а) в сети только что'
    if (minutes < 60) return `был(а) в сети ${minutes} мин назад`
    if (hours < 24) return `был(а) в сети ${hours} ч назад`
    if (days < MONTH_IN_DAYS) return `был(а) в сети ${days} дн назад`
    if (months < YEAR_IN_MONTHS) return `был(а) в сети ${months} мес назад`
    return `был(а) в сети ${years} г назад`
}

function isSameDay(a, b) {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    )
}

function normalizeDate(val) {
    if (!val) return null
    if (val instanceof Date) return val
    const d = new Date(val)
    return Number.isNaN(d.getTime()) ? null : d
}
