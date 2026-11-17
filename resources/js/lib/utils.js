const MANILA_TIME_ZONE = 'Asia/Manila';

export function classNames(...values) {
    return values.filter(Boolean).join(' ');
}

export function humanize(value) {
    if (!value) return '';
    return String(value)
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function initials(name) {
    if (!name) return 'IP';
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function toDate(value) {
    if (!value) return null;
    const date = value instanceof Date ? value : new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
}

const dateTimeFormatter = new Intl.DateTimeFormat('en-PH', {
    timeZone: MANILA_TIME_ZONE,
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
});

const dateFormatter = new Intl.DateTimeFormat('en-PH', {
    timeZone: MANILA_TIME_ZONE,
    month: 'short',
    day: 'numeric',
    year: 'numeric',
});

const timeFormatter = new Intl.DateTimeFormat('en-PH', {
    timeZone: MANILA_TIME_ZONE,
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
});

const weekdayFormatter = new Intl.DateTimeFormat('en-PH', {
    timeZone: MANILA_TIME_ZONE,
    weekday: 'short',
    month: 'short',
    day: 'numeric',
});

export function formatDateTime(value) {
    const date = toDate(value);
    return date ? dateTimeFormatter.format(date) : '—';
}

export function formatDate(value) {
    const date = toDate(value);
    return date ? dateFormatter.format(date) : '—';
}

export function formatWeekday(value) {
    const date = toDate(value);
    return date ? weekdayFormatter.format(date) : '—';
}

export function formatTime(value) {
    const date = toDate(value);
    return date ? timeFormatter.format(date) : '—';
}

export function formatDateInput(value) {
    const date = toDate(value);
    if (!date) return '';
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: MANILA_TIME_ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(date);
}

/**
 * Convert an ISO timestamp to a value accepted by <input type="datetime-local">
 * while preserving the Asia/Manila wall-clock time used across the UI.
 */
export function toDateTimeInput(value) {
    const date = toDate(value);
    if (!date) return '';

    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: MANILA_TIME_ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(date);

    const get = (type) => parts.find((part) => part.type === type)?.value ?? '00';
    const hour = get('hour') === '24' ? '00' : get('hour');

    return `${get('year')}-${get('month')}-${get('day')}T${hour}:${get('minute')}`;
}

/**
 * Convert a <input type="datetime-local"> value (Asia/Manila wall-clock)
 * into an explicit offset timestamp so the server stores the correct UTC time.
 * The Philippines has no daylight saving time, so +08:00 is always correct.
 */
export function fromDateTimeInput(value) {
    if (!value) return null;
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) {
        return `${value}:00+08:00`;
    }
    return value;
}

const relativeFormatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

export function relativeTime(value) {
    const date = toDate(value);
    if (!date) return '—';

    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const units = [
        ['year', 60 * 60 * 24 * 365],
        ['month', 60 * 60 * 24 * 30],
        ['week', 60 * 60 * 24 * 7],
        ['day', 60 * 60 * 24],
        ['hour', 60 * 60],
        ['minute', 60],
        ['second', 1],
    ];

    for (const [unit, amount] of units) {
        if (Math.abs(seconds) >= amount || unit === 'second') {
            return relativeFormatter.format(Math.round(seconds / amount), unit);
        }
    }

    return '—';
}

export function minutesSince(value) {
    const date = toDate(value);
    if (!date) return null;
    return Math.max(0, Math.round((Date.now() - date.getTime()) / 60000));
}

export function formatMinutes(minutes) {
    if (minutes === null || minutes === undefined) return '—';
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes} min ago`;

    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    if (hours < 24) {
        return remainder ? `${hours} hr ${remainder} min ago` : `${hours} hr ago`;
    }

    const days = Math.floor(hours / 24);
    return `${days} day${days === 1 ? '' : 's'} ago`;
}

export function formatNumber(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    return new Intl.NumberFormat('en-PH').format(Number(value));
}

export function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function percent(part, total) {
    if (!total) return 0;
    return Math.min(100, Math.max(0, Math.round((part / total) * 100)));
}

export function toneForBedStatus(status) {
    return (
        {
            available: 'emerald',
            occupied: 'indigo',
            reserved: 'amber',
            cleaning: 'sky',
            out_of_service: 'rose',
        }[status] ?? 'slate'
    );
}

export function toneForReferralStatus(status) {
    return (
        {
            draft: 'slate',
            submitted: 'sky',
            under_review: 'indigo',
            more_information_required: 'amber',
            accepted: 'emerald',
            declined: 'rose',
            transfer_scheduled: 'violet',
            in_transit: 'amber',
            arrived: 'teal',
            completed: 'emerald',
            cancelled: 'slate',
            expired: 'slate',
        }[status] ?? 'slate'
    );
}

export function toneForTransferStatus(status) {
    return (
        {
            scheduled: 'sky',
            ready: 'indigo',
            in_transit: 'amber',
            arrived: 'teal',
            completed: 'emerald',
            cancelled: 'slate',
        }[status] ?? 'slate'
    );
}

export function toneForUrgency(urgency) {
    return { routine: 'slate', urgent: 'amber', emergency: 'rose' }[urgency] ?? 'slate';
}

export function toneForFreshness(freshness) {
    return { fresh: 'emerald', aging: 'amber', stale: 'rose', unknown: 'slate' }[freshness] ?? 'slate';
}

export function toneForOwnership(ownership) {
    return ownership === 'private' ? 'violet' : 'teal';
}

export function toneForHospitalStatus(status) {
    return { operational: 'emerald', limited: 'amber', inactive: 'slate' }[status] ?? 'slate';
}

export function toneForUserStatus(status) {
    return { active: 'emerald', suspended: 'rose', inactive: 'slate' }[status] ?? 'slate';
}

export function pluralize(count, singular, plural = `${singular}s`) {
    return `${formatNumber(count)} ${Number(count) === 1 ? singular : plural}`;
}

export function truncate(value, length = 120) {
    if (!value) return '';
    return value.length > length ? `${value.slice(0, length - 1)}…` : value;
}

export function queryParams(params) {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '' || value === false) return;
        if (Array.isArray(value)) {
            value.forEach((item) => search.append(`${key}[]`, item));
            return;
        }
        search.set(key, String(value));
    });

    return search.toString();
}
