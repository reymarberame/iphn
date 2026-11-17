import React, { useEffect, useId } from 'react';
import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    Info,
    Search,
    X,
} from 'lucide-react';
import {
    classNames,
    formatNumber,
    initials,
    percent,
    toneForBedStatus,
    toneForFreshness,
    toneForReferralStatus,
    toneForTransferStatus,
    toneForUrgency,
} from '../lib/utils';

export const TONES = {
    emerald: {
        badge: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
        icon: 'bg-emerald-50 text-emerald-700',
        bar: 'bg-emerald-500',
        text: 'text-emerald-700',
        soft: 'bg-emerald-50',
    },
    teal: {
        badge: 'bg-teal-50 text-teal-700 ring-1 ring-inset ring-teal-200',
        icon: 'bg-teal-50 text-teal-700',
        bar: 'bg-teal-500',
        text: 'text-teal-700',
        soft: 'bg-teal-50',
    },
    amber: {
        badge: 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-200',
        icon: 'bg-amber-50 text-amber-700',
        bar: 'bg-amber-500',
        text: 'text-amber-700',
        soft: 'bg-amber-50',
    },
    sky: {
        badge: 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200',
        icon: 'bg-sky-50 text-sky-700',
        bar: 'bg-sky-500',
        text: 'text-sky-700',
        soft: 'bg-sky-50',
    },
    indigo: {
        badge: 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-200',
        icon: 'bg-indigo-50 text-indigo-700',
        bar: 'bg-indigo-500',
        text: 'text-indigo-700',
        soft: 'bg-indigo-50',
    },
    violet: {
        badge: 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200',
        icon: 'bg-violet-50 text-violet-700',
        bar: 'bg-violet-500',
        text: 'text-violet-700',
        soft: 'bg-violet-50',
    },
    rose: {
        badge: 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
        icon: 'bg-rose-50 text-rose-700',
        bar: 'bg-rose-500',
        text: 'text-rose-700',
        soft: 'bg-rose-50',
    },
    slate: {
        badge: 'bg-ink-100 text-ink-700 ring-1 ring-inset ring-ink-200',
        icon: 'bg-ink-100 text-ink-600',
        bar: 'bg-ink-400',
        text: 'text-ink-700',
        soft: 'bg-ink-50',
    },
    brand: {
        badge: 'bg-brand-50 text-brand-800 ring-1 ring-inset ring-brand-200',
        icon: 'bg-brand-50 text-brand-700',
        bar: 'bg-brand-600',
        text: 'text-brand-700',
        soft: 'bg-brand-50',
    },
};

export function Button({
    variant = 'primary',
    size = 'md',
    className,
    type = 'button',
    children,
    ...props
}) {
    const variants = {
        primary: 'btn-primary',
        secondary: 'btn-secondary',
        ghost: 'btn-ghost',
        danger: 'btn-danger',
        warn: 'btn-warn',
    };

    return (
        <button
            type={type}
            className={classNames('btn', variants[variant], size === 'sm' && 'btn-sm', size === 'lg' && 'btn-lg', className)}
            {...props}
        >
            {children}
        </button>
    );
}

export function Badge({ tone = 'slate', dot = false, className, children }) {
    return (
        <span className={classNames('badge', TONES[tone]?.badge ?? TONES.slate.badge, className)}>
            {dot && <span className="badge-dot" />}
            {children}
        </span>
    );
}

export function BedStatusBadge({ status, label, dot = true }) {
    return (
        <Badge tone={toneForBedStatus(status)} dot={dot}>
            {label ?? status}
        </Badge>
    );
}

export function ReferralStatusBadge({ status, label, dot = true }) {
    return (
        <Badge tone={toneForReferralStatus(status)} dot={dot}>
            {label ?? status}
        </Badge>
    );
}

export function TransferStatusBadge({ status, label, dot = true }) {
    return (
        <Badge tone={toneForTransferStatus(status)} dot={dot}>
            {label ?? status}
        </Badge>
    );
}

export function UrgencyBadge({ urgency, label }) {
    return (
        <Badge tone={toneForUrgency(urgency)}>
            {urgency === 'emergency' && <AlertTriangle className="size-3" />}
            {label ?? urgency}
        </Badge>
    );
}

export function FreshnessBadge({ freshness, label, description, className }) {
    return (
        <Badge tone={toneForFreshness(freshness)} dot className={className} title={description}>
            {label ?? freshness}
        </Badge>
    );
}

export function Field({ label, htmlFor, error, hint, required, children, className }) {
    return (
        <div className={classNames('min-w-0', className)}>
            {label && (
                <label htmlFor={htmlFor} className="field-label">
                    {label}
                    {required && <span className="ml-1 text-rose-500">*</span>}
                </label>
            )}
            {children}
            {error && (
                <p className="field-error">
                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                    <span>{error}</span>
                </p>
            )}
            {!error && hint && <p className="field-hint">{hint}</p>}
        </div>
    );
}

export function TextInput({ error, className, ...props }) {
    return <input className={classNames('input', error && 'input-error', className)} {...props} />;
}

export function Select({ error, className, children, ...props }) {
    return (
        <select className={classNames('select', error && 'input-error', className)} {...props}>
            {children}
        </select>
    );
}

export function Textarea({ error, className, rows = 4, ...props }) {
    return <textarea rows={rows} className={classNames('textarea', error && 'input-error', className)} {...props} />;
}

export function SearchInput({ value, onChange, placeholder = 'Search…', className, id, ...props }) {
    const generatedId = useId();
    const inputId = id ?? generatedId;

    return (
        <div className={classNames('relative', className)}>
            <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
            <input
                id={inputId}
                type="search"
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                className="input pl-10"
                {...props}
            />
        </div>
    );
}

export function Card({ className, children, ...props }) {
    return (
        <section className={classNames('card', className)} {...props}>
            {children}
        </section>
    );
}

export function PageHeader({ eyebrow, title, description, actions, children, className }) {
    return (
        <div className={classNames('flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between', className)}>
            <div className="min-w-0">
                {eyebrow && <p className="eyebrow mb-2">{eyebrow}</p>}
                <h1 className="heading-1 text-balance">{title}</h1>
                {description && <p className="muted mt-2 max-w-3xl">{description}</p>}
                {children}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

export function Panel({ title, description, actions, children, className, bodyClassName, icon: Icon }) {
    return (
        <Card className={className}>
            {(title || actions) && (
                <div className="flex flex-wrap items-start justify-between gap-3 border-b border-ink-100 px-5 py-4 sm:px-6">
                    <div className="flex min-w-0 items-start gap-3">
                        {Icon && (
                            <span className="icon-tile bg-brand-50 text-brand-700">
                                <Icon className="size-5" />
                            </span>
                        )}
                        <div className="min-w-0">
                            {title && <h2 className="heading-3">{title}</h2>}
                            {description && <p className="subtle mt-1">{description}</p>}
                        </div>
                    </div>
                    {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                </div>
            )}
            <div className={classNames(bodyClassName ?? 'p-5 sm:p-6')}>{children}</div>
        </Card>
    );
}

export function StatCard({ label, value, hint, icon: Icon, tone = 'brand', href, trailing }) {
    const content = (
        <>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-semibold tracking-wide text-ink-500 uppercase">{label}</p>
                    <p className="stat-value mt-2">{value}</p>
                </div>
                {Icon && (
                    <span className={classNames('icon-tile', TONES[tone]?.icon ?? TONES.brand.icon)}>
                        <Icon className="size-5" />
                    </span>
                )}
            </div>
            {(hint || trailing) && (
                <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                    {hint && <p className="subtle">{hint}</p>}
                    {trailing}
                </div>
            )}
        </>
    );

    if (href) {
        return (
            <Link
                href={href}
                className="card group block p-5 transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-[var(--shadow-lift)]"
            >
                {content}
            </Link>
        );
    }

    return <div className="card p-5">{content}</div>;
}

export function ProgressBar({ value, tone = 'brand', className, showLabel = false, label }) {
    const width = Math.min(100, Math.max(0, Number(value) || 0));

    return (
        <div className={classNames('flex items-center gap-2', className)}>
            <div className="h-2 w-full overflow-hidden rounded-full bg-ink-100" role="presentation">
                <div
                    className={classNames('h-full rounded-full transition-all duration-500', TONES[tone]?.bar ?? TONES.brand.bar)}
                    style={{ width: `${width}%` }}
                />
            </div>
            {showLabel && <span className="w-9 shrink-0 text-right text-xs font-semibold text-ink-600">{label ?? `${width}%`}</span>}
        </div>
    );
}

export function EmptyState({ icon: Icon = Info, title, description, action, className }) {
    return (
        <div className={classNames('flex flex-col items-center justify-center px-6 py-14 text-center', className)}>
            <span className="flex size-12 items-center justify-center rounded-2xl bg-ink-100 text-ink-500">
                <Icon className="size-6" />
            </span>
            <h3 className="mt-4 font-display text-base font-bold text-ink-900">{title}</h3>
            {description && <p className="muted mt-1.5 max-w-md">{description}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}

export function InfoCallout({ tone = 'brand', title, children, icon: Icon, className }) {
    const icons = { brand: Info, amber: AlertTriangle, rose: AlertTriangle, emerald: CircleCheck, slate: Info };
    const ResolvedIcon = Icon ?? icons[tone] ?? Info;

    const styles = {
        brand: 'border-brand-200 bg-brand-50/70 text-brand-900',
        emerald: 'border-emerald-200 bg-emerald-50/70 text-emerald-900',
        amber: 'border-amber-200 bg-amber-50/70 text-amber-900',
        rose: 'border-rose-200 bg-rose-50/70 text-rose-900',
        slate: 'border-ink-200 bg-ink-50 text-ink-800',
    };

    return (
        <div className={classNames('flex gap-3 rounded-xl border p-4 text-sm leading-6', styles[tone], className)}>
            <ResolvedIcon className="mt-0.5 size-4.5 shrink-0" />
            <div className="min-w-0">
                {title && <p className="font-semibold">{title}</p>}
                <div className={classNames(title && 'mt-1', 'opacity-90')}>{children}</div>
            </div>
        </div>
    );
}

export function Avatar({ name, size = 'md', className }) {
    const sizes = { sm: 'size-8 text-[11px]', md: 'size-9 text-xs', lg: 'size-11 text-sm' };

    return (
        <span
            className={classNames(
                'inline-flex shrink-0 items-center justify-center rounded-full bg-brand-700 font-bold text-white ring-2 ring-white/20',
                sizes[size],
                className,
            )}
            aria-hidden="true"
        >
            {initials(name)}
        </span>
    );
}

export function Tabs({ options, value, onChange, className }) {
    return (
        <div className={classNames('flex flex-wrap gap-1 rounded-xl bg-ink-100/80 p-1', className)} role="tablist">
            {options.map((option) => {
                const active = value === option.value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="tab"
                        aria-selected={active}
                        onClick={() => onChange(option.value)}
                        className={classNames(
                            'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                            active ? 'bg-white text-ink-900 shadow-sm' : 'text-ink-500 hover:text-ink-800',
                        )}
                    >
                        {option.label}
                        {option.count !== undefined && (
                            <span
                                className={classNames(
                                    'ml-1.5 rounded-full px-1.5 py-0.5 text-[10px]',
                                    active ? 'bg-brand-50 text-brand-700' : 'bg-white/70 text-ink-500',
                                )}
                            >
                                {option.count}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}

export function Pagination({ paginator, className }) {
    if (!paginator || !paginator.last_page || paginator.last_page <= 1) {
        return paginator?.total ? (
            <p className={classNames('text-xs text-ink-500', className)}>
                Showing {formatNumber(paginator.from)}–{formatNumber(paginator.to)} of {formatNumber(paginator.total)}
            </p>
        ) : null;
    }

    const pages = [];
    const current = paginator.current_page;
    const last = paginator.last_page;

    for (let page = 1; page <= last; page += 1) {
        if (last <= 7 || page === 1 || page === last || Math.abs(page - current) <= 1) {
            pages.push(page);
        } else if (pages[pages.length - 1] !== '…') {
            pages.push('…');
        }
    }

    const linkFor = (page) => {
        const match = paginator.links?.find((link) => link.label === String(page));
        return match?.url ?? null;
    };

    return (
        <div className={classNames('flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between', className)}>
            <p className="text-xs text-ink-500">
                Showing {formatNumber(paginator.from)}–{formatNumber(paginator.to)} of {formatNumber(paginator.total)}
            </p>
            <nav className="flex items-center gap-1" aria-label="Pagination">
                {paginator.prev_page_url ? (
                    <Link
                        href={paginator.prev_page_url}
                        preserveScroll
                        className="btn-icon border border-ink-200 bg-white"
                        aria-label="Previous page"
                    >
                        <ChevronLeft className="size-4" />
                    </Link>
                ) : (
                    <span className="btn-icon border border-ink-100 bg-ink-50 text-ink-300">
                        <ChevronLeft className="size-4" />
                    </span>
                )}

                {pages.map((page, index) =>
                    page === '…' ? (
                        <span key={`ellipsis-${index}`} className="px-1.5 text-xs text-ink-400">
                            …
                        </span>
                    ) : page === current ? (
                        <span
                            key={page}
                            aria-current="page"
                            className="inline-flex size-9 items-center justify-center rounded-xl bg-brand-700 text-xs font-bold text-white shadow-sm"
                        >
                            {page}
                        </span>
                    ) : (
                        <Link
                            key={page}
                            href={linkFor(page)}
                            preserveScroll
                            className="inline-flex size-9 items-center justify-center rounded-xl border border-ink-200 bg-white text-xs font-semibold text-ink-600 transition hover:border-ink-300 hover:bg-ink-50"
                        >
                            {page}
                        </Link>
                    ),
                )}

                {paginator.next_page_url ? (
                    <Link
                        href={paginator.next_page_url}
                        preserveScroll
                        className="btn-icon border border-ink-200 bg-white"
                        aria-label="Next page"
                    >
                        <ChevronRight className="size-4" />
                    </Link>
                ) : (
                    <span className="btn-icon border border-ink-100 bg-ink-50 text-ink-300">
                        <ChevronRight className="size-4" />
                    </span>
                )}
            </nav>
        </div>
    );
}

export function Modal({ open, onClose, title, description, children, footer, size = 'md', icon: Icon }) {
    useEffect(() => {
        if (!open) return undefined;

        const onKeyDown = (event) => {
            if (event.key === 'Escape') onClose?.();
        };

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open, onClose]);

    if (!open) return null;

    const sizes = { sm: 'max-w-md', md: 'max-w-xl', lg: 'max-w-3xl', xl: 'max-w-5xl' };

    return (
        <div className="fixed inset-0 z-[80] flex items-end justify-center p-0 sm:items-center sm:p-6" role="presentation">
            <div className="absolute inset-0 bg-ink-950/50 backdrop-blur-[2px]" onClick={onClose} aria-hidden="true" />
            <div
                role="dialog"
                aria-modal="true"
                aria-label={title}
                className={classNames(
                    'relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl',
                    sizes[size],
                )}
            >
                <div className="flex items-start justify-between gap-4 border-b border-ink-100 px-5 py-4 sm:px-6">
                    <div className="flex min-w-0 items-start gap-3">
                        {Icon && (
                            <span className="icon-tile bg-brand-50 text-brand-700">
                                <Icon className="size-5" />
                            </span>
                        )}
                        <div className="min-w-0">
                            <h2 className="heading-3">{title}</h2>
                            {description && <p className="subtle mt-1">{description}</p>}
                        </div>
                    </div>
                    <button type="button" onClick={onClose} className="btn-icon -mr-1 -mt-1" aria-label="Close dialog">
                        <X className="size-5" />
                    </button>
                </div>
                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">{children}</div>
                {footer && (
                    <div className="flex flex-wrap items-center justify-end gap-2 border-t border-ink-100 bg-ink-50/60 px-5 py-4 sm:px-6">
                        {footer}
                    </div>
                )}
            </div>
        </div>
    );
}

export function KeyValue({ label, children, className, mono = false }) {
    return (
        <div className={classNames('min-w-0', className)}>
            <dt className="text-[11px] font-semibold tracking-wide text-ink-500 uppercase">{label}</dt>
            <dd className={classNames('mt-1 text-sm text-ink-800', mono && 'font-mono text-xs')}>{children || '—'}</dd>
        </div>
    );
}

export function CapacityBar({ available, total, tone = 'emerald', className }) {
    return (
        <div className={className}>
            <div className="flex items-baseline justify-between gap-2">
                <span className="text-sm font-semibold text-ink-900">
                    {formatNumber(available)} <span className="font-normal text-ink-500">of {formatNumber(total)}</span>
                </span>
                <span className="text-[11px] font-semibold text-ink-500">{percent(available, total)}%</span>
            </div>
            <ProgressBar value={percent(available, total)} tone={tone} className="mt-1.5" />
        </div>
    );
}
