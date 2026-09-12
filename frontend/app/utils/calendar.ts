const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function todayISO(): { year: number; month: number; day: number } {
    const now = new Date()

    return {
        year: now.getFullYear(),
        month: now.getMonth(),
        day: now.getDate(),
    }
}

function getDaysInMonth(year: number, month: number): number {
    return new Date(year, month + 1, 0).getDate()
}

function getFirstWeekday(year: number, month: number): number {
    return new Date(year, month, 1).getDay()
}

function isoDate(year: number, month: number, day: number): string {
    const mm = String(month + 1).padStart(2, '0')
    const dd = String(day).padStart(2, '0')

    return `${year}-${mm}-${dd}`
}

function parseISODate(value: string): { year: number; month: number; day: number } | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)

    if (!match) {
        return null
    }

    const year = Number(match[1])
    const month = Number(match[2]) - 1
    const day = Number(match[3])

    if (month < 0 || month > 11 || day < 1 || day > getDaysInMonth(year, month)) {
        return null
    }

    return { year, month, day }
}

function buildMonthGrid(year: number, month: number): (number | null)[] {
    const firstWeekday = getFirstWeekday(year, month)
    const daysInMonth = getDaysInMonth(year, month)
    const cells: (number | null)[] = []

    for (let i = 0; i < 42; i += 1) {
        const day = i - firstWeekday + 1

        cells.push(day < 1 || day > daysInMonth ? null : day)
    }

    return cells
}

function monthLabel(year: number, month: number): string {
    return new Date(year, month, 1).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    })
}

function shiftMonth(year: number, month: number, delta: number): { year: number; month: number } {
    const shifted = new Date(year, month + delta, 1)

    return { year: shifted.getFullYear(), month: shifted.getMonth() }
}

function isToday(dateStr: string): boolean {
    const { year, month, day } = todayISO()

    return dateStr === isoDate(year, month, day)
}

function dayLabel(dateStr: string): string {
    const date = new Date(`${dateStr}T00:00:00`)

    return date.toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

export {
    WEEKDAYS,
    isoDate,
    parseISODate,
    getDaysInMonth,
    buildMonthGrid,
    monthLabel,
    shiftMonth,
    isToday,
    dayLabel,
}