export type TicketStatus =
    | 'open'
    | 'assigned'
    | 'in_progress'
    | 'resolved'
    | 'closed'
    | 'reopened';

export type TicketPriority = 'low' | 'medium' | 'high' | 'critical';

type Meta = { label: string; class: string };

// Setiap warna punya pasangan terang dan gelap. Teks label selalu ditampilkan,
// jadi warna bukan satu-satunya penanda (PRD FR-05).
export const statusMeta: Record<TicketStatus, Meta> = {
    open: {
        label: 'Open',
        class: 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950 dark:text-indigo-200',
    },
    assigned: {
        label: 'Assigned',
        class: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    },
    in_progress: {
        label: 'In Progress',
        class: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    },
    resolved: {
        label: 'Resolved',
        class: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    },
    closed: {
        label: 'Closed',
        class: 'bg-muted text-muted-foreground',
    },
    reopened: {
        label: 'Reopened',
        class: 'bg-orange-100 text-orange-900 dark:bg-orange-950 dark:text-orange-200',
    },
};

export const priorityMeta: Record<TicketPriority, Meta> = {
    low: {
        label: 'Low',
        class: 'bg-muted text-muted-foreground',
    },
    medium: {
        label: 'Medium',
        class: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    },
    high: {
        label: 'High',
        class: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    },
    critical: {
        label: 'Critical',
        class: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
    },
};

export const statusOptions = (
    Object.entries(statusMeta) as [TicketStatus, Meta][]
).map(([value, meta]) => ({ value, label: meta.label }));

export const priorityOptions = (
    Object.entries(priorityMeta) as [TicketPriority, Meta][]
).map(([value, meta]) => ({ value, label: meta.label }));

export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
