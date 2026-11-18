import React, { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowRight,
    ArrowUpRight,
    ClipboardList,
    FilterX,
    Plus,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import {
    Badge,
    Button,
    Card,
    EmptyState,
    Field,
    PageHeader,
    Pagination,
    ReferralStatusBadge,
    Select,
    StatCard,
    Tabs,
    UrgencyBadge,
} from '../../components/ui';
import { classNames, formatDateTime, formatNumber, relativeTime } from '../../lib/utils';

const DIRECTION_META = {
    incoming: { label: 'Incoming', tone: 'sky', icon: ArrowDownLeft },
    outgoing: { label: 'Outgoing', tone: 'violet', icon: ArrowUpRight },
    both: { label: 'Both', tone: 'teal', icon: ArrowRight },
};

export default function ReferralIndex({ referrals, filters = {}, statusOptions = [], urgencyOptions = [], counts = {}, canCreate = false }) {
    const [values, setValues] = useState({
        direction: filters.direction ?? '',
        status: filters.status ?? '',
        urgency: filters.urgency ?? '',
        search: filters.search ?? '',
    });

    useEffect(() => {
        setValues({
            direction: filters.direction ?? '',
            status: filters.status ?? '',
            urgency: filters.urgency ?? '',
            search: filters.search ?? '',
        });
    }, [filters.direction, filters.status, filters.urgency, filters.search]);

    const go = (nextValues) => {
        const params = Object.fromEntries(Object.entries(nextValues).filter(([, value]) => value !== '' && value !== null));
        router.get('/referrals', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const apply = (event) => {
        event.preventDefault();
        go(values);
    };

    const setDirection = (direction) => {
        const next = { ...values, direction };
        setValues(next);
        go(next);
    };

    const reset = () => router.get('/referrals', {}, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <AppLayout>
            <Head title="Referrals" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Operations"
                    title="Referral coordination"
                    description="Track incoming and outgoing referrals. Patient-linked details are visible only to the referring and receiving hospitals."
                    actions={
                        canCreate ? (
                            <Link href="/referrals/create" className="btn btn-primary btn-sm">
                                <Plus className="size-4" />
                                New referral
                            </Link>
                        ) : (
                            <Badge tone="slate">Read-only referral access</Badge>
                        )
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Pending incoming"
                        value={formatNumber(counts.pending_incoming)}
                        hint="Waiting for your hospital"
                        icon={ArrowDownLeft}
                        tone="sky"
                    />
                    <StatCard
                        label="Pending outgoing"
                        value={formatNumber(counts.pending_outgoing)}
                        hint="Awaiting receiving response"
                        icon={ArrowUpRight}
                        tone="violet"
                    />
                    <StatCard label="Active accepted" value={formatNumber(counts.accepted)} hint="Accepted or in transfer" icon={ClipboardList} tone="emerald" />
                    <StatCard label="Completed (30 days)" value={formatNumber(counts.completed_last_30_days)} hint="Closed transfers" icon={ClipboardList} tone="brand" />
                </div>

                <Card className="p-4 sm:p-5">
                    <div className="flex flex-col gap-4">
                        <Tabs
                            value={values.direction || 'all'}
                            onChange={(value) => setDirection(value === 'all' ? '' : value)}
                            options={[
                                { value: 'all', label: 'All referrals' },
                                { value: 'incoming', label: 'Incoming', count: counts.pending_incoming },
                                { value: 'outgoing', label: 'Outgoing', count: counts.pending_outgoing },
                            ]}
                        />

                        <form onSubmit={apply} className="flex flex-col gap-3 border-t border-ink-100 pt-4 lg:flex-row lg:items-end">
                            <Field label="Search" htmlFor="referral-search" className="lg:w-72">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
                                    <input
                                        id="referral-search"
                                        type="search"
                                        value={values.search}
                                        onChange={(event) => setValues((current) => ({ ...current, search: event.target.value }))}
                                        placeholder="Referral number, patient reference, or name"
                                        className="input pl-10"
                                    />
                                </div>
                            </Field>

                            <Field label="Status" htmlFor="referral-status" className="lg:w-56">
                                <Select
                                    id="referral-status"
                                    value={values.status}
                                    onChange={(event) => setValues((current) => ({ ...current, status: event.target.value }))}
                                >
                                    <option value="">All statuses</option>
                                    {statusOptions.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <Field label="Urgency" htmlFor="referral-urgency" className="lg:w-44">
                                <Select
                                    id="referral-urgency"
                                    value={values.urgency}
                                    onChange={(event) => setValues((current) => ({ ...current, urgency: event.target.value }))}
                                >
                                    <option value="">All urgencies</option>
                                    {urgencyOptions.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
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
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    {referrals?.data?.length ? (
                        <>
                            <div className="overflow-x-auto">
                                <table className="table">
                                    <thead>
                                        <tr>
                                            <th>Referral</th>
                                            <th>Route</th>
                                            <th>Service</th>
                                            <th>Urgency</th>
                                            <th>Status</th>
                                            <th>Submitted</th>
                                            <th>Coordinator</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {referrals.data.map((referral) => {
                                            const direction = DIRECTION_META[referral.direction] ?? DIRECTION_META.outgoing;
                                            const DirectionIcon = direction.icon;

                                            return (
                                                <tr key={referral.id} className="group">
                                                    <td>
                                                        <Link href={`/referrals/${referral.id}`} className="block">
                                                            <span className="block text-xs font-bold text-ink-900 group-hover:text-brand-700">{referral.referral_number}</span>
                                                            <span className="mt-0.5 block truncate text-[11px] text-ink-500">
                                                                {referral.patient_reference} · {referral.patient_name}
                                                            </span>
                                                        </Link>
                                                    </td>
                                                    <td>
                                                        <Badge tone={direction.tone}>
                                                            <DirectionIcon className="size-3" />
                                                            {direction.label}
                                                        </Badge>
                                                        <span className="mt-1 block text-xs text-ink-700">
                                                            {referral.referring_hospital} <span className="text-ink-400">→</span> {referral.receiving_hospital}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span className="block max-w-40 truncate text-xs text-ink-600">{referral.requested_service || '—'}</span>
                                                    </td>
                                                    <td>
                                                        <UrgencyBadge urgency={referral.urgency} label={referral.urgency_label} />
                                                    </td>
                                                    <td>
                                                        <ReferralStatusBadge status={referral.status} label={referral.status_label} />
                                                    </td>
                                                    <td>
                                                        <span className="block whitespace-nowrap text-xs text-ink-600">
                                                            {referral.submitted_at ? relativeTime(referral.submitted_at) : 'Not submitted'}
                                                        </span>
                                                        <span className="mt-0.5 block whitespace-nowrap text-[10px] text-ink-400">
                                                            {formatDateTime(referral.submitted_at || referral.created_at)}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span className="block max-w-32 truncate text-xs text-ink-600">{referral.coordinator || 'Unassigned'}</span>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            <div className="border-t border-ink-100 px-5 py-4">
                                <Pagination paginator={referrals} />
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            icon={ClipboardList}
                            title="No referrals match these filters"
                            description="Try clearing the direction, status, or search filters. New referrals will appear here as they are created."
                            action={
                                <div className="flex items-center gap-2">
                                    <Button variant="secondary" onClick={reset}>
                                        Clear filters
                                    </Button>
                                    {canCreate && (
                                        <Link href="/referrals/create" className={classNames('btn', 'btn-primary')}>
                                            <Plus className="size-4" />
                                            Create referral
                                        </Link>
                                    )}
                                </div>
                            }
                        />
                    )}
                </Card>

                <p className="text-[11px] leading-5 text-ink-400">
                    Referral numbers are safe coordination identifiers. BHN stores only the minimum information needed to route and coordinate a transfer; clinical
                    records remain in each hospital's own system.
                </p>
            </div>
        </AppLayout>
    );
}
