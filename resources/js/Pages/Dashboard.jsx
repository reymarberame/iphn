import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowLeftRight,
    ArrowUpRight,
    BedDouble,
    Building2,
    CalendarClock,
    CircleAlert,
    ClipboardList,
    Clock3,
    FilterX,
    MapPin,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Users,
} from 'lucide-react';
import AppLayout from '../Layouts/AppLayout';
import {
    Badge,
    BedStatusBadge,
    Button,
    CapacityBar,
    Card,
    EmptyState,
    Field,
    FreshnessBadge,
    InfoCallout,
    Panel,
    ReferralStatusBadge,
    Select,
    StatCard,
    TONES,
    TransferStatusBadge,
    UrgencyBadge,
} from '../components/ui';
import {
    classNames,
    formatDateTime,
    formatMinutes,
    formatNumber,
    minutesSince,
    pluralize,
    relativeTime,
    toneForFreshness,
} from '../lib/utils';

const OWNERSHIP_OPTIONS = [
    { value: 'government', label: 'Government' },
    { value: 'private', label: 'Private' },
];

function DashboardFilters({ filters, reference, onSubmit, onReset }) {
    const [values, setValues] = useState({
        search: filters.search ?? '',
        region_id: filters.region_id ?? '',
        province_id: filters.province_id ?? '',
        ownership_type: filters.ownership_type ?? '',
        category: filters.category ?? '',
        service_id: filters.service_id ?? '',
    });

    const regions = reference?.regions ?? [];
    const provinces = regions.find((region) => String(region.id) === String(values.region_id))?.provinces ?? [];

    const update = (key, value) => {
        setValues((current) => {
            const next = { ...current, [key]: value };
            if (key === 'region_id') {
                const allowed = regions.find((region) => String(region.id) === String(value))?.provinces ?? [];
                if (!allowed.some((province) => String(province.id) === String(current.province_id))) {
                    next.province_id = '';
                }
            }
            return next;
        });
    };

    return (
        <Card className="p-4 sm:p-5">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    onSubmit(values);
                }}
                className="space-y-3"
            >
                <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <Field label="Search hospitals" htmlFor="dashboard-search" className="lg:w-64">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
                            <input
                                id="dashboard-search"
                                type="search"
                                value={values.search}
                                onChange={(event) => update('search', event.target.value)}
                                placeholder="Name or hospital code"
                                className="input pl-10"
                            />
                        </div>
                    </Field>

                    <Field label="Region" htmlFor="dashboard-region" className="lg:w-52">
                        <Select id="dashboard-region" value={values.region_id} onChange={(event) => update('region_id', event.target.value)}>
                            <option value="">All regions</option>
                            {regions.map((region) => (
                                <option key={region.id} value={region.id}>
                                    {region.short_name || region.name}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Province" htmlFor="dashboard-province" className="lg:w-52">
                        <Select
                            id="dashboard-province"
                            value={values.province_id}
                            onChange={(event) => update('province_id', event.target.value)}
                            disabled={!values.region_id}
                        >
                            <option value="">{values.region_id ? 'All provinces' : 'Select a region first'}</option>
                            {provinces.map((province) => (
                                <option key={province.id} value={province.id}>
                                    {province.name}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Ownership" htmlFor="dashboard-ownership" className="lg:w-40">
                        <Select
                            id="dashboard-ownership"
                            value={values.ownership_type}
                            onChange={(event) => update('ownership_type', event.target.value)}
                        >
                            <option value="">All types</option>
                            {OWNERSHIP_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Bed category" htmlFor="dashboard-category" className="lg:w-48">
                        <Select id="dashboard-category" value={values.category} onChange={(event) => update('category', event.target.value)}>
                            <option value="">All categories</option>
                            {(reference?.categories ?? []).map((category) => (
                                <option key={category.id} value={category.slug}>
                                    {category.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>

                <div className="flex flex-col gap-3 border-t border-ink-100 pt-3 sm:flex-row sm:items-end sm:justify-between">
                    <Field label="Service" htmlFor="dashboard-service" className="sm:w-72">
                        <Select id="dashboard-service" value={values.service_id} onChange={(event) => update('service_id', event.target.value)}>
                            <option value="">Any service</option>
                            {(reference?.services ?? []).map((service) => (
                                <option key={service.id} value={service.id}>
                                    {service.name}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <div className="flex items-center gap-2">
                        <Button type="button" variant="ghost" size="sm" onClick={onReset}>
                            <FilterX className="size-4" />
                            Reset
                        </Button>
                        <Button type="submit" size="sm">
                            <SlidersHorizontal className="size-4" />
                            Apply filters
                        </Button>
                    </div>
                </div>
            </form>
        </Card>
    );
}

function StatusMix({ counts, compact = false }) {
    const segments = [
        { key: 'available', label: 'Available', value: counts.available, className: 'bg-emerald-500' },
        { key: 'occupied', label: 'Occupied', value: counts.occupied, className: 'bg-indigo-500' },
        { key: 'reserved', label: 'Reserved', value: counts.reserved, className: 'bg-amber-500' },
        { key: 'cleaning', label: 'Cleaning', value: counts.cleaning, className: 'bg-sky-500' },
        { key: 'out_of_service', label: 'Out of service', value: counts.out_of_service, className: 'bg-rose-400' },
    ];
    const total = segments.reduce((sum, segment) => sum + segment.value, 0);

    return (
        <div>
            <div className="flex h-2.5 w-full overflow-hidden rounded-full bg-ink-100">
                {segments.map((segment) =>
                    segment.value > 0 ? (
                        <div
                            key={segment.key}
                            className={segment.className}
                            style={{ width: `${(segment.value / Math.max(total, 1)) * 100}%` }}
                            title={`${segment.label}: ${segment.value}`}
                        />
                    ) : null,
                )}
            </div>
            <div className={classNames('mt-4 grid gap-x-5 gap-y-2.5', compact ? 'grid-cols-2' : 'grid-cols-2 sm:grid-cols-5')}>
                {segments.map((segment) => (
                    <div key={segment.key} className="flex items-center justify-between gap-2">
                        <span className="flex min-w-0 items-center gap-2 text-xs text-ink-500">
                            <span className={classNames('size-2 shrink-0 rounded-full', segment.className)} />
                            <span className="truncate">{segment.label}</span>
                        </span>
                        <span className="text-xs font-bold text-ink-800">{formatNumber(segment.value)}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function FreshnessOverview({ freshness, className }) {
    const total = (freshness?.fresh ?? 0) + (freshness?.aging ?? 0) + (freshness?.stale ?? 0) + (freshness?.unknown ?? 0);
    const states = [
        { key: 'fresh', label: 'Fresh', tone: 'emerald' },
        { key: 'aging', label: 'Aging', tone: 'amber' },
        { key: 'stale', label: 'Stale', tone: 'rose' },
        { key: 'unknown', label: 'No report', tone: 'slate' },
    ];

    return (
        <div className={className}>
            <div className="flex h-2.5 w-full overflow-hidden rounded-full bg-ink-100">
                {states.map((state) =>
                    (freshness?.[state.key] ?? 0) > 0 ? (
                        <div
                            key={state.key}
                            className={TONES[state.tone].bar}
                            style={{ width: `${((freshness[state.key] ?? 0) / Math.max(total, 1)) * 100}%` }}
                            title={`${state.label}: ${freshness[state.key]}`}
                        />
                    ) : null,
                )}
            </div>
            <div className="mt-4 grid grid-cols-2 gap-2">
                {states.map((state) => (
                    <div key={state.key} className="flex items-center justify-between gap-2 rounded-lg bg-ink-50 px-2.5 py-2">
                        <FreshnessBadge freshness={state.key} label={state.label} />
                        <span className="text-xs font-bold text-ink-800">{formatNumber(freshness?.[state.key] ?? 0)}</span>
                    </div>
                ))}
            </div>
            {freshness?.policy && (
                <p className="subtle mt-3">
                    Fresh ≤ {freshness.policy.fresh_minutes} min · Aging ≤ {freshness.policy.aging_minutes} min · Stale beyond{' '}
                    {freshness.policy.aging_minutes} min
                </p>
            )}
        </div>
    );
}

function CategoryAvailability({ categories, title = 'Availability by bed category', description, className }) {
    if (!categories?.length) {
        return (
            <Panel title={title} description={description} className={className}>
                <EmptyState icon={BedDouble} title="No bed categories reported" description="Inventory has not been configured for this scope yet." />
            </Panel>
        );
    }

    return (
        <Panel title={title} description={description} className={className}>
            <div className="space-y-4">
                {categories.map((category) => (
                    <div key={category.id ?? category.slug} className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_160px_auto] sm:items-center">
                        <div className="min-w-0">
                            <div className="flex items-center gap-2">
                                <p className="truncate text-sm font-semibold text-ink-900">{category.name}</p>
                                {category.is_er && <Badge tone="rose">ER</Badge>}
                            </div>
                            <p className="subtle mt-0.5">
                                {formatNumber(category.total)} beds · {formatNumber(category.operational)} operational
                            </p>
                        </div>
                        <CapacityBar available={category.available} total={category.total} />
                        <div className="flex flex-wrap gap-1.5 text-[10px] text-ink-500 sm:justify-end">
                            <span className="rounded-md bg-indigo-50 px-1.5 py-0.5 font-semibold text-indigo-700">{category.occupied} occ</span>
                            <span className="rounded-md bg-amber-50 px-1.5 py-0.5 font-semibold text-amber-700">{category.reserved} res</span>
                            <span className="rounded-md bg-rose-50 px-1.5 py-0.5 font-semibold text-rose-700">{category.out_of_service} OOS</span>
                        </div>
                    </div>
                ))}
            </div>
        </Panel>
    );
}

function NetworkHospitalTable({ hospitals }) {
    return (
        <Panel
            title="Hospital coverage"
            description={`${pluralize(hospitals.length, 'hospital')} in your current view`}
            actions={
                <Link href="/hospitals" className="btn btn-secondary btn-sm">
                    Open directory
                    <ArrowUpRight className="size-3.5" />
                </Link>
            }
            bodyClassName="p-0"
        >
            {hospitals.length ? (
                <div className="overflow-x-auto">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Hospital</th>
                                <th className="text-right">Total</th>
                                <th className="text-right">Available</th>
                                <th className="text-right">Occupied</th>
                                <th className="text-right">Reserved</th>
                                <th className="text-right">Cleaning</th>
                                <th className="text-right">OOS</th>
                                <th>Last report</th>
                            </tr>
                        </thead>
                        <tbody>
                            {hospitals.map((hospital) => (
                                <tr key={hospital.hospital_id}>
                                    <td>
                                        <Link href={`/hospitals/${hospital.hospital_id}`} className="group block min-w-48">
                                            <span className="block text-sm font-semibold text-ink-900 group-hover:text-brand-700">
                                                {hospital.hospital_name}
                                            </span>
                                            <span className="mt-0.5 flex items-center gap-1 text-[11px] text-ink-500">
                                                <MapPin className="size-3" />
                                                {hospital.municipality ? `${hospital.municipality}, ` : ''}
                                                {hospital.province ?? hospital.region}
                                            </span>
                                        </Link>
                                    </td>
                                    <td className="text-right font-medium">{formatNumber(hospital.counts.total)}</td>
                                    <td className="text-right">
                                        <span className="font-bold text-emerald-700">{formatNumber(hospital.counts.available)}</span>
                                    </td>
                                    <td className="text-right">{formatNumber(hospital.counts.occupied)}</td>
                                    <td className="text-right">{formatNumber(hospital.counts.reserved)}</td>
                                    <td className="text-right">{formatNumber(hospital.counts.cleaning)}</td>
                                    <td className="text-right">{formatNumber(hospital.counts.out_of_service)}</td>
                                    <td>
                                        <div className="flex flex-col gap-1">
                                            <FreshnessBadge freshness={hospital.freshness} label={hospital.freshness_label} />
                                            <span className="text-[10px] text-ink-400">{relativeTime(hospital.last_updated_at)}</span>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <EmptyState
                    icon={Building2}
                    title="No hospitals match the filters"
                    description="Try widening the region, ownership, or service filters."
                />
            )}
        </Panel>
    );
}

function NetworkDashboard({ summary, filters, recentReferrals, recentUpdates, demoNotice }) {
    const { reference, auth } = usePage().props;
    const [refreshing, setRefreshing] = useState(false);
    const totals = summary.totals;
    const attention = summary.attention ?? [];
    const canOpenReferrals = auth?.user?.has_hospital_membership;

    const applyFilters = (values) => {
        const params = Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '' && value !== null));
        router.get('/', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const resetFilters = () => router.get('/', {}, { preserveState: true, preserveScroll: true, replace: true });

    const refresh = () => {
        setRefreshing(true);
        router.reload({
            only: ['summary', 'recentReferrals', 'recentUpdates'],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setRefreshing(false),
        });
    };

    return (
        <>
            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="eyebrow">Network overview</p>
                            <Badge tone="brand">{summary.scope.label}</Badge>
                        </div>
                        <h1 className="heading-1 mt-2">Bed availability across the network</h1>
                        <p className="muted mt-2 max-w-3xl">
                            Manual reports from participating hospitals. Freshness shows how recently each hospital confirmed its bed board.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="secondary" size="sm" onClick={refresh} disabled={refreshing}>
                            <RefreshCw className={classNames('size-4', refreshing && 'animate-spin')} />
                            Refresh
                        </Button>
                        <Link href="/hospitals" className="btn btn-secondary btn-sm">
                            <Building2 className="size-4" />
                            Directory
                        </Link>
                        <Link href="/reports" className="btn btn-primary btn-sm">
                            <Activity className="size-4" />
                            Reports
                        </Link>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Available beds"
                        value={formatNumber(totals.available)}
                        hint={`${formatNumber(totals.operational)} operational of ${formatNumber(totals.total_beds)} total`}
                        icon={BedDouble}
                        tone="emerald"
                        href="/hospitals"
                    />
                    <StatCard
                        label="Available ER beds"
                        value={formatNumber(totals.available_er)}
                        hint={`${formatNumber(totals.available_inpatient)} other inpatient beds available`}
                        icon={Activity}
                        tone="rose"
                    />
                    <StatCard
                        label="Hospitals in view"
                        value={formatNumber(totals.hospitals)}
                        hint={`${formatNumber(totals.fresh_reports)} fresh · ${formatNumber(totals.stale_reports)} stale reports`}
                        icon={Building2}
                        tone="brand"
                        href="/hospitals"
                    />
                    <StatCard
                        label="Pending referrals"
                        value={formatNumber(totals.pending_referrals)}
                        hint={`${formatNumber(summary.referrals?.incoming ?? 0)} incoming · ${formatNumber(summary.referrals?.outgoing ?? 0)} outgoing`}
                        icon={ClipboardList}
                        tone="amber"
                        href={canOpenReferrals ? '/referrals' : undefined}
                    />
                </div>

                <DashboardFilters filters={filters} reference={reference} onSubmit={applyFilters} onReset={resetFilters} />

                <div className="grid gap-4 xl:grid-cols-3">
                    <CategoryAvailability
                        categories={summary.categories}
                        title="Network capacity by category"
                        description="Available beds versus total inventory in the current filter."
                        className="xl:col-span-2"
                    />

                    <div className="min-w-0 space-y-4">
                        <Panel title="Report freshness" description="How current are the bed boards behind these totals?">
                            <FreshnessOverview freshness={summary.freshness} />
                        </Panel>

                        <Panel
                            title="Needs attention"
                            description="Hospitals with aging or stale bed reports."
                            actions={<Badge tone={attention.length ? 'amber' : 'emerald'}>{attention.length} flagged</Badge>}
                        >
                            {attention.length ? (
                                <ul className="space-y-2.5">
                                    {attention.slice(0, 5).map((hospital) => (
                                        <li key={hospital.hospital_id}>
                                            <Link
                                                href={`/hospitals/${hospital.hospital_id}`}
                                                className="flex items-center justify-between gap-3 rounded-xl border border-ink-100 px-3 py-2.5 transition hover:border-brand-200 hover:bg-brand-50/30"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-xs font-semibold text-ink-900">{hospital.hospital_name}</p>
                                                    <p className="mt-0.5 text-[10px] text-ink-500">
                                                        {formatMinutes(minutesSince(hospital.last_updated_at))} · {hospital.last_updated_by || 'No updater'}
                                                    </p>
                                                </div>
                                                <FreshnessBadge freshness={hospital.freshness} label={hospital.freshness_label} />
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-3 text-xs font-medium text-emerald-800">
                                    <Activity className="size-4" />
                                    All hospital reports are fresh.
                                </div>
                            )}
                        </Panel>
                    </div>
                </div>

                <Panel title="Availability mix" description="Current bed status distribution across the filtered network.">
                    <StatusMix counts={totals} />
                </Panel>

                <NetworkHospitalTable hospitals={summary.hospitals ?? []} />

                <div className="grid gap-4 xl:grid-cols-2">
                    <Panel
                        title="Recent referrals"
                        description={canOpenReferrals ? 'Latest coordination activity in your scope.' : 'Aggregate view only — referral details are restricted to hospital staff.'}
                        actions={
                            canOpenReferrals ? (
                                <Link href="/referrals" className="text-xs font-semibold text-brand-700 hover:underline">
                                    View all
                                </Link>
                            ) : null
                        }
                        bodyClassName="p-0"
                    >
                        {recentReferrals?.length ? (
                            <ul className="divide-y divide-ink-100">
                                {recentReferrals.map((referral) => {
                                    const content = (
                                        <>
                                            <div className="flex min-w-0 items-start gap-3">
                                                <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">
                                                    <ClipboardList className="size-4" />
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="truncate text-xs font-semibold text-ink-900">
                                                        {referral.referral_number}{' '}
                                                        <span className="font-normal text-ink-500">· {referral.urgency_label}</span>
                                                    </p>
                                                    <p className="mt-0.5 truncate text-[11px] text-ink-500">
                                                        {referral.referring_hospital} → {referral.receiving_hospital}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex shrink-0 flex-col items-end gap-1">
                                                <ReferralStatusBadge status={referral.status} label={referral.status_label} dot={false} />
                                                <span className="text-[10px] text-ink-400">{relativeTime(referral.created_at)}</span>
                                            </div>
                                        </>
                                    );

                                    return (
                                        <li key={referral.id}>
                                            {canOpenReferrals ? (
                                                <Link href={`/referrals/${referral.id}`} className="flex items-start justify-between gap-3 px-5 py-3 transition hover:bg-ink-50">
                                                    {content}
                                                </Link>
                                            ) : (
                                                <div className="flex items-start justify-between gap-3 px-5 py-3">{content}</div>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        ) : (
                            <EmptyState icon={ClipboardList} title="No referrals yet" description="Referral activity will appear here once hospitals begin coordinating." />
                        )}
                    </Panel>

                    <Panel title="Recent bed reports" description="Latest manual status changes from participating hospitals." bodyClassName="p-0">
                        {recentUpdates?.length ? (
                            <ul className="divide-y divide-ink-100">
                                {recentUpdates.map((update) => (
                                    <li key={update.id} className="flex items-center justify-between gap-3 px-5 py-3">
                                        <div className="flex min-w-0 items-center gap-3">
                                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-ink-100 text-ink-600">
                                                <BedDouble className="size-4" />
                                            </span>
                                            <div className="min-w-0">
                                                <p className="truncate text-xs font-semibold text-ink-900">
                                                    {update.bed_code} <span className="font-normal text-ink-500">at {update.hospital}</span>
                                                </p>
                                                <p className="mt-0.5 truncate text-[10px] text-ink-400">
                                                    {update.changed_by} · {relativeTime(update.changed_at)}
                                                </p>
                                            </div>
                                        </div>
                                        <BedStatusBadge status={update.new_status} label={update.new_status?.replace(/_/g, ' ')} />
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState icon={Clock3} title="No bed reports yet" description="Bed status updates will appear here." />
                        )}
                    </Panel>
                </div>

                {demoNotice && (
                    <InfoCallout tone="slate" title="About these numbers">
                        {demoNotice}
                    </InfoCallout>
                )}
            </div>
        </>
    );
}

function HospitalHeader({ hospital, hospitals, summary, canUpdateBeds }) {
    return (
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="eyebrow">Hospital command center</p>
                    <FreshnessBadge freshness={summary.freshness} label={summary.freshness_label} />
                </div>
                <h1 className="heading-1 mt-2 text-balance">{hospital.name}</h1>
                <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-ink-500">
                    <span className="inline-flex items-center gap-1.5">
                        <MapPin className="size-3.5" />
                        {hospital.location}
                    </span>
                    <span className="font-mono text-[11px]">{hospital.hospital_code}</span>
                    <span>{hospital.ownership_type === 'private' ? 'Private' : 'Government'}</span>
                    <Badge tone={hospital.status === 'operational' ? 'emerald' : 'amber'}>{hospital.status?.replace(/_/g, ' ')}</Badge>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {hospitals.length > 1 && (
                    <Select
                        aria-label="Switch hospital"
                        value={hospital.id}
                        onChange={(event) => router.get('/', { hospital: event.target.value }, { preserveScroll: true })}
                        className="w-56"
                    >
                        {hospitals.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.short_name || item.name}
                            </option>
                        ))}
                    </Select>
                )}
                <Link href={`/hospitals/${hospital.id}`} className="btn btn-secondary btn-sm">
                    <Building2 className="size-4" />
                    Profile
                </Link>
                {canUpdateBeds && (
                    <Link href={`/beds?hospital=${hospital.id}`} className="btn btn-primary btn-sm">
                        <BedDouble className="size-4" />
                        Open bed board
                    </Link>
                )}
            </div>
        </div>
    );
}

function ReferralQueue({ title, description, referrals, emptyText, href }) {
    return (
        <Panel
            title={title}
            description={description}
            actions={
                <Link href={href} className="text-xs font-semibold text-brand-700 hover:underline">
                    Open queue
                </Link>
            }
            bodyClassName="p-0"
        >
            {referrals?.length ? (
                <ul className="divide-y divide-ink-100">
                    {referrals.map((referral) => (
                        <li key={referral.id}>
                            <Link href={`/referrals/${referral.id}`} className="flex items-start justify-between gap-3 px-5 py-3 transition hover:bg-ink-50">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <span className="text-xs font-semibold text-ink-900">{referral.referral_number}</span>
                                        <UrgencyBadge urgency={referral.urgency} label={referral.urgency_label} />
                                    </div>
                                    <p className="mt-1 truncate text-[11px] text-ink-500">
                                        {referral.patient_reference} · {referral.requested_service || 'No service selected'}
                                    </p>
                                    <p className="mt-0.5 truncate text-[10px] text-ink-400">{referral.hospital}</p>
                                </div>
                                <div className="flex shrink-0 flex-col items-end gap-1">
                                    <ReferralStatusBadge status={referral.status} label={referral.status_label} dot={false} />
                                    <span className="text-[10px] text-ink-400">{relativeTime(referral.submitted_at || referral.created_at)}</span>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyState icon={ClipboardList} title={emptyText} description="Nothing needs your attention right now." />
            )}
        </Panel>
    );
}

function HospitalDashboard({ hospital, hospitals, summary, recentUpdates, incoming, outgoing, transfers, canViewReferrals, canUpdateBeds, demoNotice }) {
    const counts = summary.counts;
    const categories = summary.categories ?? [];
    const erAvailable = categories.filter((category) => category.is_er).reduce((sum, category) => sum + category.available, 0);
    const wards = summary.wards ?? [];
    const minutes = minutesSince(summary.last_updated_at);

    return (
        <div className="space-y-6">
            <HospitalHeader hospital={hospital} hospitals={hospitals} summary={summary} canUpdateBeds={canUpdateBeds} />

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard
                    label="Available"
                    value={formatNumber(counts.available)}
                    hint={`${formatNumber(counts.operational)} operational beds`}
                    icon={BedDouble}
                    tone="emerald"
                    href={`/beds?hospital=${hospital.id}`}
                />
                <StatCard label="Occupied" value={formatNumber(counts.occupied)} hint={`of ${formatNumber(counts.total)} total beds`} icon={Users} tone="indigo" />
                <StatCard label="Reserved" value={formatNumber(counts.reserved)} hint="Held for accepted referrals" icon={CalendarClock} tone="amber" />
                <StatCard label="Cleaning" value={formatNumber(counts.cleaning)} hint="Temporarily unavailable" icon={Clock3} tone="sky" />
                <StatCard
                    label="Out of service"
                    value={formatNumber(counts.out_of_service)}
                    hint={`${formatNumber(erAvailable)} ER beds available`}
                    icon={CircleAlert}
                    tone="rose"
                />
            </div>

            <Card className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div className="flex items-start gap-3">
                    <span className={classNames('icon-tile', TONES[toneForFreshness(summary.freshness)]?.icon)}>
                        <Clock3 className="size-5" />
                    </span>
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-sm font-semibold text-ink-900">Bed board reported {relativeTime(summary.last_updated_at)}</p>
                            <FreshnessBadge freshness={summary.freshness} label={summary.freshness_label} />
                        </div>
                        <p className="subtle mt-1">
                            {summary.last_updated_at
                                ? `${formatDateTime(summary.last_updated_at)} · ${summary.last_updated_by || 'System'}`
                                : 'No bed updates have been recorded for this hospital.'}{' '}
                            {minutes !== null && `· ${formatMinutes(minutes)}`}
                        </p>
                    </div>
                </div>
                {canUpdateBeds ? (
                    <Link href={`/beds?hospital=${hospital.id}`} className="btn btn-secondary btn-sm shrink-0">
                        <RefreshCw className="size-3.5" />
                        Update availability
                    </Link>
                ) : (
                    <Badge tone="slate">Read-only bed access</Badge>
                )}
            </Card>

            <div className="grid gap-4 xl:grid-cols-3">
                <div className="space-y-4 xl:col-span-2">
                    <CategoryAvailability categories={categories} title="Capacity by bed category" description="Counts derive from individual bed statuses." />

                    <Panel title="Ward availability" description="Reported capacity by ward." bodyClassName="p-0">
                        {wards.length ? (
                            <div className="overflow-x-auto">
                                <table className="table table-compact">
                                    <thead>
                                        <tr>
                                            <th>Ward</th>
                                            <th>Type</th>
                                            <th className="text-right">Total</th>
                                            <th className="text-right">Available</th>
                                            <th className="text-right">Occupied</th>
                                            <th className="text-right">Reserved</th>
                                            <th className="text-right">Cleaning</th>
                                            <th className="text-right">OOS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {wards.map((ward) => (
                                            <tr key={ward.id}>
                                                <td className="font-medium text-ink-900">{ward.name}</td>
                                                <td className="capitalize">{ward.ward_type?.replace(/_/g, ' ')}</td>
                                                <td className="text-right">{formatNumber(ward.total)}</td>
                                                <td className="text-right font-semibold text-emerald-700">{formatNumber(ward.available)}</td>
                                                <td className="text-right">{formatNumber(ward.occupied)}</td>
                                                <td className="text-right">{formatNumber(ward.reserved)}</td>
                                                <td className="text-right">{formatNumber(ward.cleaning)}</td>
                                                <td className="text-right">{formatNumber(ward.out_of_service)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <EmptyState icon={BedDouble} title="No wards configured" description="Bed inventory has not been set up for this hospital." />
                        )}
                    </Panel>
                </div>

                <div className="space-y-4">
                    <Panel title="Reported mix" description="All active beds at this hospital.">
                        <StatusMix counts={counts} compact />
                        <div className="mt-4 flex items-center justify-between rounded-xl bg-ink-50 px-3 py-2.5">
                            <span className="text-xs font-medium text-ink-500">Pending referrals</span>
                            <div className="flex items-center gap-2">
                                <Badge tone="sky">{formatNumber(summary.referrals?.incoming ?? 0)} in</Badge>
                                <Badge tone="violet">{formatNumber(summary.referrals?.outgoing ?? 0)} out</Badge>
                            </div>
                        </div>
                        <div className="mt-2 flex items-center justify-between rounded-xl bg-ink-50 px-3 py-2.5">
                            <span className="text-xs font-medium text-ink-500">Active transfers</span>
                            <span className="text-sm font-bold text-ink-900">{formatNumber(summary.referrals?.active_transfers ?? 0)}</span>
                        </div>
                    </Panel>

                    <Panel title="Transfer board" description="Scheduled, ready, and in-transit movements." bodyClassName="p-0">
                        {transfers?.length ? (
                            <ul className="divide-y divide-ink-100">
                                {transfers.map((transfer) => (
                                    <li key={transfer.id}>
                                        <Link href={`/referrals/${transfer.referral_id}`} className="block px-5 py-3 transition hover:bg-ink-50">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="text-xs font-semibold text-ink-900">{transfer.referral_number}</span>
                                                <TransferStatusBadge status={transfer.status} label={transfer.status_label} dot={false} />
                                            </div>
                                            <p className="mt-1 truncate text-[11px] text-ink-500">
                                                {transfer.from} → {transfer.to}
                                            </p>
                                            <p className="mt-0.5 text-[10px] text-ink-400">
                                                {transfer.estimated_arrival_at
                                                    ? `ETA ${formatDateTime(transfer.estimated_arrival_at)}`
                                                    : transfer.departure_at
                                                      ? `Departed ${formatDateTime(transfer.departure_at)}`
                                                      : 'Awaiting departure time'}
                                            </p>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState icon={ArrowLeftRight} title="No active transfers" description="Scheduled transfers will appear here." />
                        )}
                    </Panel>
                </div>
            </div>

            {canViewReferrals ? (
                <div className="grid gap-4 xl:grid-cols-2">
                    <ReferralQueue
                        title="Incoming referrals"
                        description="Referrals waiting for your hospital's response."
                        referrals={incoming}
                        emptyText="No incoming referrals"
                        href="/referrals?direction=incoming"
                    />
                    <ReferralQueue
                        title="Outgoing referrals"
                        description="Referrals sent from this hospital."
                        referrals={outgoing}
                        emptyText="No outgoing referrals"
                        href="/referrals?direction=outgoing"
                    />
                </div>
            ) : (
                <InfoCallout tone="slate" title="Referral details are restricted">
                    Your current role can view bed availability but not patient-linked referral records. Ask a hospital administrator if you need referral
                    coordination access.
                </InfoCallout>
            )}

            <Panel title="Recent bed reports" description="Latest manual status changes at this hospital." bodyClassName="p-0">
                {recentUpdates?.length ? (
                    <ul className="divide-y divide-ink-100">
                        {recentUpdates.map((update) => (
                            <li key={update.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <div className="flex min-w-0 items-center gap-3">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-ink-100 text-ink-600">
                                        <BedDouble className="size-4" />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="text-xs font-semibold text-ink-900">
                                            {update.bed_code}
                                            {update.reason && <span className="font-normal text-ink-500"> · {update.reason}</span>}
                                        </p>
                                        <p className="mt-0.5 text-[10px] text-ink-400">
                                            {update.changed_by} · {relativeTime(update.changed_at)}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    {update.old_status && (
                                        <>
                                            <BedStatusBadge status={update.old_status} label={update.old_status.replace(/_/g, ' ')} dot={false} />
                                            <span className="text-[10px] text-ink-400">→</span>
                                        </>
                                    )}
                                    <BedStatusBadge status={update.new_status} label={update.new_status.replace(/_/g, ' ')} />
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <EmptyState icon={Clock3} title="No bed reports yet" description="Status updates from your team will appear here." />
                )}
            </Panel>

            {demoNotice && (
                <InfoCallout tone="slate" title="Reminder">
                    {demoNotice}
                </InfoCallout>
            )}
        </div>
    );
}

export default function Dashboard(props) {
    const { mode, message } = props;

    return (
        <AppLayout>
            <Head title={mode === 'network' ? 'Network dashboard' : mode === 'hospital' ? 'Hospital dashboard' : 'Dashboard'} />

            {mode === 'network' && <NetworkDashboard {...props} />}
            {mode === 'hospital' && <HospitalDashboard {...props} />}
            {mode === 'unassigned' && (
                <div className="mx-auto max-w-2xl py-10">
                    <Card>
                        <EmptyState
                            icon={Users}
                            title="No hospital assignment yet"
                            description={message || 'Your account is active but has no hospital assignment. Ask a network administrator to assign your role.'}
                            action={
                                <Button variant="secondary" onClick={() => router.reload()}>
                                    <RefreshCw className="size-4" />
                                    Check again
                                </Button>
                            }
                        />
                    </Card>
                </div>
            )}
        </AppLayout>
    );
}
