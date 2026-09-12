export interface DueBadgeInfo {
    label: string
    type: string
}

type DatedTask = {
    due_date: string | null
    status: string
}

type Taggable = {
    color?: string | null
}

const DUE_SOON_DAYS = 3

const STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    in_progress: 'In progress',
    completed: 'Completed',
}

const STATUS_CLASSES: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-600',
    in_progress: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
}

const PRIORITY_LABELS: Record<string, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
}

const PRIORITY_CLASSES: Record<string, string> = {
    low: 'bg-gray-100 text-gray-600',
    medium: 'bg-amber-100 text-amber-700',
    high: 'bg-red-100 text-red-700',
}

function todayISO(): string {
    const now = new Date()
    const month = String(now.getMonth() + 1).padStart(2, '0')
    const day = String(now.getDate()).padStart(2, '0')

    return `${now.getFullYear()}-${month}-${day}`
}

function addDaysToISO(iso: string, days: number): string {
    const date = new Date(`${iso}T00:00:00`)
    date.setDate(date.getDate() + days)
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${date.getFullYear()}-${month}-${day}`
}

function dueBadge(task: DatedTask): DueBadgeInfo | null {
    if (!task.due_date || task.status === 'completed') {
        return null
    }

    const today = todayISO()

    if (task.due_date < today) {
        return { label: 'Overdue', type: 'overdue' }
    }

    if (task.due_date === today) {
        return { label: 'Due today', type: 'due-today' }
    }

    if (task.due_date <= addDaysToISO(today, DUE_SOON_DAYS)) {
        return { label: 'Due soon', type: 'due-soon' }
    }

    return null
}

function badgeClass(task: DatedTask): string {
    const type = dueBadge(task)?.type

    if (type === 'overdue') {
        return 'bg-red-100 text-red-700'
    }

    if (type === 'due-today') {
        return 'bg-amber-100 text-amber-700'
    }

    return 'bg-blue-100 text-blue-700'
}

function statusLabel(status: string): string {
    return STATUS_LABELS[status] ?? status
}

function statusChipClass(status: string): string {
    return STATUS_CLASSES[status] ?? 'bg-gray-100 text-gray-600'
}

function priorityLabel(priority: string): string {
    return PRIORITY_LABELS[priority] ?? priority
}

function priorityChipClass(priority: string): string {
    return PRIORITY_CLASSES[priority] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(value: string): string {
    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return date.toLocaleString()
}

function tagChipStyle(tag: Taggable): Record<string, string> {
    const color = tag.color ?? '#6b7280'

    return {
        backgroundColor: `${color}22`,
        border: `1px solid ${color}55`,
        color,
    }
}

const RECURRENCE_LABELS: Record<string, string> = {
    daily: 'Daily',
    weekly: 'Weekly',
    monthly: 'Monthly',
    yearly: 'Yearly',
}

function recurrenceLabel(frequency: string | null): string {
    return frequency ? RECURRENCE_LABELS[frequency] ?? frequency : ''
}

function recurrenceBadgeClass(): string {
    return 'bg-purple-100 text-purple-700'
}

export {
    dueBadge,
    badgeClass,
    statusLabel,
    statusChipClass,
    priorityLabel,
    priorityChipClass,
    formatDate,
    tagChipStyle,
    recurrenceLabel,
    recurrenceBadgeClass,
}