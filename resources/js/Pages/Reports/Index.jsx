import React, { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Activity,
    ArrowLeftRight,
    BedDouble,
    Building2,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Download,
    FilterX,
    Gauge,
    SlidersHorizontal,
    Timer,
    TrendingUp,
    TriangleAlert,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import {
    Badge,
    Button,
    Card,
    Field,
    FreshnessBadge,
    PageHeader,
    Panel,
    ProgressBar,
    Select,
    StatCard,
} from '../../components/ui';
import {
    classNames,
    formatDateTime,
    formatMinutes,
    formatNumber,
    percent,
    queryParams,
    toneForFreshness,
} from '../../lib/utils';

function durationLabel(minutes) {
    if (minutes === null || minutes === undefined) return '—';
    if (minutes < 60) return `${minutes} min`;

    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    return remainder ? `${hours} hr ${remainder} min` : `${hours} hr`;
}

function TrendChart({ trend = [] }) {
    const max = Math.max(1, ...trend.map((day) => day.count));
    const total = trend.reduce((sum, day) => sum + day.count, 0);

    return (
        <div>
            <div className="mb-3 flex items-baseline justify-between gap-2">
                <p className="subtle">Referrals created per day</p>
                <p className="text-xs font-semibold text-ink-700">
                    {formatNumber(total)} <span className="font-normal text-ink-500">in 14 days</span>
                </p>
            </div>
            <div className="flex h-40 items-end gap-1.5 overflow-x-auto pb-1">
                {trend.map((day) => {
                    const height = day.count ? Math.max(6, Math.round((day.count / max) * 100)) : 2;

                    return (
                        <div key={day.date} className="flex min-w-8 flex-1 flex-col items-center justify-end gap-1" title={`${day.label}: ${day.count}`}>
                            <span className={classNames('text-[9px] font-semibold', day.count ? 'text-ink-600' : 'text-transparent')}>{day.count}</span>
                            <div className="flex h-28 w-full items-end rounded-md bg-ink-50">
                                <div
                                    className={classNames('w-full rounded-md transition-all', day.count ? 'bg-brand-500' : 'bg-ink-200')}
                                    style={{ height: `${height}%` }}
                                />
                            </div>
                            <span className="text-[9px] whitespace-nowrap text-ink-400">{day.label}</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function DistributionList({ items = [], toneFor }) {
    const max = Math.max(1, ...items.map((item) => item.count));

    return (
        <ul className="space-y-2.5">
            {items.map((item) => (
                <li key={item.status} className="flex items-center gap-3">
                    <span className="w-32 shrink-0 truncate text-xs text-ink-600">{item.label}</span>
                    <div className="h-2 flex-1 overflow-hidden rounded-full bg-ink-100">
                        <div
                            className={classNames(
                                'h-full rounded-full',
                                {
                                    emerald: 'bg-emerald-500',
                                    teal: 'bg-teal-500',
                                    amber: 'bg-amber-500',
                                    sky: 'bg-sky-500',
                                    indigo: 'bg-indigo-500',
                                    violet: 'bg-violet-500',
                                    rose: 'bg-rose-500',
                                    slate: 'bg-ink-400',
                                    brand: 'bg-brand-500',
                                }[toneFor(item.status)] ?? 'bg-brand-500',
                            )}
                            style={{ width: `${Math.max(2, Math.round((item.count / max) * 100))}%` }}
                        />
                    </div>
                    <span className="w-8 shrink-0 text-right text-xs font-semibold text-ink-700">{formatNumber(item.count)}</span>
                </li>
            ))}
        </ul>
    );
}

export default function ReportIndex({ overview, filters = {}, hospitals = [], canExportReferrals = false }) {
    const [values, setValues] = useState({
        date_from: filters.date_from ?? overview?.period?.from ?? '',
        date_to: filters.date_to ?? overview?.period?.to ?? '',
        hospital_id: filters.hospital_id ?? '',
    });

    useEffect(() => {
        setValues({
            date_from: filters.date_from ?? overview?.period?.from ?? '',
            date_to: filters.date_to ?? overview?.period?.to ?? '',
            hospital_id: filters.hospital_id ?? '',
        });
    }, [filters.date_from, filters.date_to, filters.hospital_id, overview?.period?.from, overview?.period?.to]);

    const totals = overview?.totals ?? {};
    const rates = overview?.rates ?? {};
    const categories = overview?.categories ?? [];
    const compliance = overview?.compliance ?? [];

    const apply = (event) => {
        event.preventDefault();
        const params = Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '' && value !== null));
        router.get('/reports', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const reset = () => router.get('/reports', {}, { preserveState: true, preserveScroll: true, replace: true });

    const exportQuery = queryParams({
        date_from: values.date_from || overview?.period?.from,
        date_to: values.date_to || overview?.period?.to,
        hospital_id: values.hospital_id,
    });

    const exportHref = (type) => `/reports/export/${type}${exportQuery ? `?${exportQuery}` : ''}`;

    return (
        <AppLayout>
            <Head title="Reports" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Insights"
                    title="Operational reports"
                    description={`${overview?.scope?.label ?? 'Network'} · ${overview?.period?.label ?? 'Current period'}. Bed counts derive from individual bed statuses; referral rates use records created in the selected period.`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <a href={exportHref('availability')} className="btn btn-secondary btn-sm">
                                <Download className="size-4" />
                                Availability CSV
                            </a>
                            {canExportReferrals && (
                                <>
                                    <a href={exportHref('referrals')} className="btn btn-secondary btn-sm">
                                        <Download className="size-4" />
                                        Referrals CSV
                                    </a>
                                    <a href={exportHref('transfers')} className="btn btn-secondary btn-sm">
                                        <Download className="size-4" />
                                        Transfers CSV
                                    </a>
                                </>
                            )}
                        </div>
                    }
                />

                {!canExportReferrals && (
                    <p className="text-[11px] leading-5 text-ink-400">
                        Patient-linked referral and transfer exports are available only to hospital-scoped users. Network reports remain aggregate.
                    </p>
                )}

                <Card className="p-4 sm:p-5">
                    <form onSubmit={apply} className="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <Field label="From" htmlFor="report-from" className="lg:w-48">
                            <input
                                id="report-from"
                                type="date"
                                value={values.date_from}
                                onChange={(event) => setValues((current) => ({ ...current, date_from: event.target.value }))}
                                className="input"
                            />
                        </Field>
                        <Field label="To" htmlFor="report-to" className="lg:w-48">
                            <input
                                id="report-to"
                                type="date"
                                value={values.date_to}
                                onChange={(event) => setValues((current) => ({ ...current, date_to: event.target.value }))}
                                className="input"
                            />
                        </Field>
                        <Field label="Hospital" htmlFor="report-hospital" className="lg:w-64">
                            <Select
                                id="report-hospital"
                                value={values.hospital_id}
                                onChange={(event) => setValues((current) => ({ ...current, hospital_id: event.target.value }))}
                            >
                                <option value="">All hospitals in scope</option>
                                {hospitals.map((hospital) => (
                                    <option key={hospital.id} value={hospital.id}>
                                        {hospital.short_name || hospital.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <div className="flex items-center gap-2 lg:ml-auto">
                            <Button type="button" variant="ghost" size="sm" onClick={reset}>
                                <FilterX className="size-4" />
                                Reset
                            </Button>
                            <Button type="submit" size="sm">
                                <SlidersHorizontal className="size-4" />
                                Apply
                            </Button>
                        </div>
                    </form>
                </Card>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Referrals created"
                        value={formatNumber(totals.referrals)}
                        hint={`${formatNumber(totals.accepted)} accepted · ${formatNumber(totals.declined)} declined`}
                        icon={Activity}
                        tone="brand"
                    />
                    <StatCard
                        label="Acceptance rate"
                        value={`${rates.acceptance_rate ?? 0}%`}
                        hint={`${rates.completion_rate ?? 0}% of accepted referrals completed`}
                        icon={TrendingUp}
                        tone="emerald"
                        trailing={<Badge tone={rates.decline_rate > 20 ? 'rose' : 'slate'}>{rates.decline_rate ?? 0}% declined</Badge>}
                    />
                    <StatCard
                        label="Beds available"
                        value={formatNumber(totals.available)}
                        hint={`${formatNumber(totals.operational)} operational of ${formatNumber(totals.beds)} reported`}
                        icon={BedDouble}
                        tone="teal"
                    />
                    <StatCard
                        label="Fresh reports"
                        value={`${totals.compliance_rate ?? 0}%`}
                        hint={`${formatNumber(totals.fresh_reports)} of ${formatNumber(compliance.length)} hospitals fresh`}
                        icon={Gauge}
                        tone={(totals.compliance_rate ?? 0) >= 70 ? 'emerald' : 'amber'}
                    />
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Occupied" value={formatNumber(totals.occupied)} hint="Currently in use" icon={Building2} tone="indigo" />
                    <StatCard label="Reserved" value={formatNumber(totals.reserved)} hint="Held for accepted referrals" icon={Clock3} tone="amber" />
                    <StatCard
                        label="Average transfer time"
                        value={durationLabel(totals.avg_transfer_minutes)}
                        hint="Departure to arrival, completed records"
                        icon={Timer}
                        tone="sky"
                    />
                    <StatCard
                        label="Completed referrals"
                        value={formatNumber(totals.completed)}
                        hint="Closed within the selected period"
                        icon={CheckCircle2}
                        tone="emerald"
                    />
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
                    <Panel title="Referral volume" description="Created referrals across the last 14 days." icon={CalendarDays}>
                        <TrendChart trend={overview?.trend ?? []} />
                    </Panel>

                    <Panel title="Conversion" description="Rates derived from referrals created in the selected period." icon={TrendingUp}>
                        <div className="space-y-5">
                            <div>
                                <div className="mb-1.5 flex items-center justify-between gap-2">
                                    <span className="text-xs font-medium text-ink-600">Acceptance rate</span>
                                    <span className="text-xs font-bold text-ink-900">{rates.acceptance_rate ?? 0}%</span>
                                </div>
                                <ProgressBar value={rates.acceptance_rate ?? 0} tone="emerald" />
                            </div>
                            <div>
                                <div className="mb-1.5 flex items-center justify-between gap-2">
                                    <span className="text-xs font-medium text-ink-600">Completion among accepted</span>
                                    <span className="text-xs font-bold text-ink-900">{rates.completion_rate ?? 0}%</span>
                                </div>
                                <ProgressBar value={rates.completion_rate ?? 0} tone="brand" />
                            </div>
                            <div>
                                <div className="mb-1.5 flex items-center justify-between gap-2">
                                    <span className="text-xs font-medium text-ink-600">Decline rate</span>
                                    <span className="text-xs font-bold text-ink-900">{rates.decline_rate ?? 0}%</span>
                                </div>
                                <ProgressBar value={rates.decline_rate ?? 0} tone="rose" />
                            </div>
                            <p className="text-[11px] leading-5 text-ink-500">
                                Accepted includes referrals that progressed to transfer scheduled, in transit, arrived, or completed. Declined referrals close immediately.
                            </p>
                        </div>
                    </Panel>
                </div>

                <div className="grid gap-5 lg:grid-cols-2">
                    <Panel title="Referral status mix" description="All referrals created in the selected period." icon={Activity}>
                        <DistributionList items={overview?.referral_statuses ?? []} toneFor={(status) => ({
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
                        }[status] ?? 'slate')} />
                    </Panel>

                    <Panel title="Transfer status mix" description="Transfers created in the selected period." icon={ArrowLeftRight}>
                        <DistributionList items={overview?.transfer_statuses ?? []} toneFor={(status) => ({
                            scheduled: 'sky',
                            ready: 'indigo',
                            in_transit: 'amber',
                            arrived: 'teal',
                            completed: 'emerald',
                            cancelled: 'slate',
                        }[status] ?? 'slate')} />
                    </Panel>
                </div>

                <Panel title="Availability by bed category" description="Counts are calculated from active beds, never entered as totals." icon={BedDouble} bodyClassName="p-0">
                    {categories.length ? (
                        <div className="overflow-x-auto">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th className="text-right">Total</th>
                                        <th className="text-right">Available</th>
                                        <th className="text-right">Occupied</th>
                                        <th className="text-right">Reserved</th>
                                        <th className="text-right">Cleaning</th>
                                        <th className="text-right">Out of service</th>
                                        <th className="min-w-32">Availability</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {categories.map((category) => (
                                        <tr key={category.id}>
                                            <td>
                                                <span className="flex items-center gap-2">
                                                    <span className="text-xs font-semibold text-ink-900">{category.name}</span>
                                                    {category.is_er && <Badge tone="rose">ER</Badge>}
                                                </span>
                                            </td>
                                            <td className="text-right text-xs font-semibold text-ink-800">{formatNumber(category.total)}</td>
                                            <td className="text-right text-xs font-semibold text-emerald-700">{formatNumber(category.available)}</td>
                                            <td className="text-right text-xs text-ink-600">{formatNumber(category.occupied)}</td>
                                            <td className="text-right text-xs text-ink-600">{formatNumber(category.reserved)}</td>
                                            <td className="text-right text-xs text-ink-600">{formatNumber(category.cleaning)}</td>
                                            <td className="text-right text-xs text-ink-600">{formatNumber(category.out_of_service)}</td>
                                            <td>
                                                <ProgressBar value={percent(category.available, category.total)} tone="emerald" showLabel />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="p-6 text-xs text-ink-500">No bed categories have been reported for this scope.</p>
                    )}
                </Panel>

                <Panel
                    title="Hospital reporting compliance"
                    description="Freshness uses the platform thresholds. Stale reports should be confirmed directly before transfer."
                    icon={TriangleAlert}
                    actions={<Badge tone="slate">{compliance.length} hospitals</Badge>}
                    bodyClassName="p-0"
                >
                    {compliance.length ? (
                        <div className="overflow-x-auto">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Hospital</th>
                                        <th>Freshness</th>
                                        <th>Last bed update</th>
                                        <th>Reported by</th>
                                        <th>Age</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {compliance.map((row) => (
                                        <tr key={row.hospital_id}>
                                            <td>
                                                <span className="block text-xs font-semibold text-ink-900">{row.hospital_name}</span>
                                            </td>
                                            <td>
                                                <FreshnessBadge freshness={row.freshness} label={row.freshness_label} />
                                            </td>
                                            <td>
                                                <span className="block whitespace-nowrap text-xs text-ink-600">{formatDateTime(row.last_updated_at)}</span>
                                            </td>
                                            <td>
                                                <span className="block max-w-36 truncate text-xs text-ink-600">{row.last_updated_by || 'No updater'}</span>
                                            </td>
                                            <td>
                                                <span
                                                    className={classNames(
                                                        'text-xs font-medium',
                                                        row.freshness === 'stale'
                                                            ? 'text-rose-700'
                                                            : row.freshness === 'aging'
                                                              ? 'text-amber-700'
                                                              : 'text-ink-600',
                                                    )}
                                                >
                                                    {row.minutes_since_update === null ? 'No report' : formatMinutes(row.minutes_since_update)}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="p-6 text-xs text-ink-500">No hospitals are in the reporting scope.</p>
                    )}
                </Panel>

                <p className="text-[11px] leading-5 text-ink-400">
                    Demo reporting note: the 14-day trend uses the current network scope rather than the date filters, so it is always a recent activity view. All other cards
                    and tables respect the selected period and hospital filter.
                </p>
            </div>
        </AppLayout>
    );
}
