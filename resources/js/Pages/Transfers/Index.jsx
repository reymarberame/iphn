import React, { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowLeftRight,
    ArrowUpRight,
    CheckCircle2,
    Clock3,
    FilterX,
    Search,
    SlidersHorizontal,
    Truck,
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
    Select,
    StatCard,
    TransferStatusBadge,
} from '../../components/ui';
import { formatDateTime, formatNumber, relativeTime } from '../../lib/utils';

const DIRECTION_META = {
    incoming: { label: 'Incoming', tone: 'sky', icon: ArrowDownLeft },
    outgoing: { label: 'Outgoing', tone: 'violet', icon: ArrowUpRight },
};

export default function TransferIndex({ transfers, filters = {}, counts = {}, statusOptions = [] }) {
    const [values, setValues] = useState({
        status: filters.status ?? '',
        search: filters.search ?? '',
    });

    useEffect(() => {
        setValues({
            status: filters.status ?? '',
            search: filters.search ?? '',
        });
    }, [filters.status, filters.search]);

    const go = (nextValues) => {
        const params = Object.fromEntries(Object.entries(nextValues).filter(([, value]) => value !== '' && value !== null));
        router.get('/transfers', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const apply = (event) => {
        event.preventDefault();
        go(values);
    };

    const reset = () => router.get('/transfers', {}, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <AppLayout>
            <Head title="Transfers" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Operations"
                    title="Transfer board"
                    description="Coordinate scheduled, in-transit, and arrived patient movements. Transfer records contain logistics only; clinical documentation stays with each hospital."
                    actions={<Badge tone="slate">Patient-linked · hospital staff only</Badge>}
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Scheduled"
                        value={formatNumber(counts.scheduled)}
                        hint="Scheduled or ready for departure"
                        icon={Clock3}
                        tone="sky"
                    />
                    <StatCard label="In transit" value={formatNumber(counts.in_transit)} hint="Patients currently moving" icon={Truck} tone="amber" />
                    <StatCard label="Arrived" value={formatNumber(counts.arrived)} hint="Awaiting completion" icon={CheckCircle2} tone="teal" />
                    <StatCard
                        label="Completed (30 days)"
                        value={formatNumber(counts.completed_last_30_days)}
                        hint="Closed coordination records"
                        icon={ArrowLeftRight}
                        tone="brand"
                    />
                </div>

                <Card className="p-4 sm:p-5">
                    <form onSubmit={apply} className="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <Field label="Search referral number" htmlFor="transfer-search" className="lg:w-80">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
                                <input
                                    id="transfer-search"
                                    type="search"
                                    value={values.search}
                                    onChange={(event) => setValues((current) => ({ ...current, search: event.target.value }))}
                                    placeholder="e.g. REF-2026-AB12CD"
                                    className="input pl-10"
                                />
                            </div>
                        </Field>

                        <Field label="Transfer status" htmlFor="transfer-status" className="lg:w-56">
                            <Select
                                id="transfer-status"
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

                        <div className="flex items-center gap-2 lg:ml-auto">
                            <Button type="button" variant="ghost" size="sm" onClick={reset}>
                                <FilterX className="size-4" />
                                Reset
                            </Button>
                            <Button type="submit" size="sm">
                                <SlidersHorizontal className="size-4" />
                                Apply filters
                            </Button>
                        </div>
                    </form>
                </Card>

                <Card className="overflow-hidden">
                    {transfers?.data?.length ? (
                        <>
                            <div className="overflow-x-auto">
                                <table className="table">
                                    <thead>
                                        <tr>
                                            <th>Transfer</th>
                                            <th>Direction</th>
                                            <th>Route</th>
                                            <th>Status</th>
                                            <th>Transport</th>
                                            <th>Departure</th>
                                            <th>ETA / arrival</th>
                                            <th>Coordinator</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {transfers.data.map((transfer) => {
                                            const direction = DIRECTION_META[transfer.is_incoming ? 'incoming' : 'outgoing'];
                                            const DirectionIcon = direction.icon;

                                            return (
                                                <tr key={transfer.id} className="group">
                                                    <td>
                                                        {transfer.referral_id ? (
                                                            <Link href={`/referrals/${transfer.referral_id}`} className="block">
                                                                <span className="block text-xs font-bold text-ink-900 group-hover:text-brand-700">
                                                                    {transfer.referral_number}
                                                                </span>
                                                                <span className="mt-0.5 block text-[10px] text-ink-500">{transfer.referral_status_label}</span>
                                                            </Link>
                                                        ) : (
                                                            <span className="text-xs text-ink-500">Referral unavailable</span>
                                                        )}
                                                    </td>
                                                    <td>
                                                        <Badge tone={direction.tone}>
                                                            <DirectionIcon className="size-3" />
                                                            {direction.label}
                                                        </Badge>
                                                    </td>
                                                    <td>
                                                        <span className="block text-xs text-ink-700">
                                                            {transfer.referring_hospital} <span className="text-ink-400">→</span> {transfer.receiving_hospital}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <TransferStatusBadge status={transfer.status} label={transfer.status_label} />
                                                    </td>
                                                    <td>
                                                        <span className="block text-xs text-ink-600">{transfer.transport_label}</span>
                                                        <span className="mt-0.5 block text-[10px] text-ink-400">{transfer.vehicle_reference || 'No vehicle reference'}</span>
                                                    </td>
                                                    <td>
                                                        <span className="block whitespace-nowrap text-xs text-ink-600">
                                                            {transfer.departure_at ? relativeTime(transfer.departure_at) : 'Not departed'}
                                                        </span>
                                                        <span className="mt-0.5 block whitespace-nowrap text-[10px] text-ink-400">
                                                            {formatDateTime(transfer.departure_at)}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        {transfer.arrival_at ? (
                                                            <>
                                                                <span className="block whitespace-nowrap text-xs font-semibold text-emerald-700">
                                                                    Arrived {relativeTime(transfer.arrival_at)}
                                                                </span>
                                                                <span className="mt-0.5 block whitespace-nowrap text-[10px] text-ink-400">
                                                                    {formatDateTime(transfer.arrival_at)}
                                                                </span>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <span className="block whitespace-nowrap text-xs text-ink-600">
                                                                    {transfer.estimated_arrival_at ? relativeTime(transfer.estimated_arrival_at) : 'No ETA'}
                                                                </span>
                                                                <span className="mt-0.5 block whitespace-nowrap text-[10px] text-ink-400">
                                                                    {formatDateTime(transfer.estimated_arrival_at)}
                                                                </span>
                                                            </>
                                                        )}
                                                    </td>
                                                    <td>
                                                        <span className="block max-w-32 truncate text-xs text-ink-600">{transfer.coordinator || '—'}</span>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            <div className="border-t border-ink-100 px-5 py-4">
                                <Pagination paginator={transfers} />
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            icon={Truck}
                            title="No transfers match these filters"
                            description="Transfers appear once an accepted referral has a scheduled movement. Clear the filters or open a referral to schedule transport."
                            action={
                                <div className="flex items-center gap-2">
                                    <Button variant="secondary" onClick={reset}>
                                        Clear filters
                                    </Button>
                                    <Link href="/referrals" className="btn btn-primary">
                                        <ArrowLeftRight className="size-4" />
                                        Open referrals
                                    </Link>
                                </div>
                            }
                        />
                    )}
                </Card>

                <p className="text-[11px] leading-5 text-ink-400">
                    Transfer status follows the referral workflow: departure requires referring-hospital authorization, while arrival and completion are confirmed by the
                    receiving hospital.
                </p>
            </div>
        </AppLayout>
    );
}
