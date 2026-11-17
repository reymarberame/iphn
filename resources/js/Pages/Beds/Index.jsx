import React, { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    BedDouble,
    Building2,
    CheckSquare,
    ChevronRight,
    Clock3,
    DoorOpen,
    FilterX,
    History,
    Layers,
    Plus,
    RefreshCw,
    Search,
    Settings2,
    ShieldAlert,
    Square,
    Trash2,
    X,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import {
    Badge,
    BedStatusBadge,
    Button,
    Card,
    EmptyState,
    Field,
    FreshnessBadge,
    InfoCallout,
    Modal,
    Panel,
    Select,
    StatCard,
    TextInput,
} from '../../components/ui';
import {
    classNames,
    formatDateTime,
    formatMinutes,
    formatNumber,
    humanize,
    minutesSince,
    relativeTime,
    toneForBedStatus,
} from '../../lib/utils';

const MANUAL_STATUSES = [
    { value: 'available', label: 'Available' },
    { value: 'occupied', label: 'Occupied' },
    { value: 'cleaning', label: 'Cleaning' },
    { value: 'out_of_service', label: 'Out of service' },
];

const WARD_TYPES = [
    { value: 'er', label: 'Emergency room' },
    { value: 'icu', label: 'Intensive care' },
    { value: 'inpatient', label: 'Inpatient' },
    { value: 'maternity', label: 'Maternity' },
    { value: 'pediatric', label: 'Pediatric' },
    { value: 'isolation', label: 'Isolation' },
    { value: 'burn', label: 'Burn unit' },
    { value: 'dialysis', label: 'Dialysis' },
    { value: 'nicu', label: 'Neonatal ICU' },
    { value: 'other', label: 'Other' },
];

const STATUS_TILE = {
    available: 'border-emerald-200 bg-white hover:border-emerald-300',
    occupied: 'border-indigo-200 bg-indigo-50/40 hover:border-indigo-300',
    reserved: 'border-amber-200 bg-amber-50/50 hover:border-amber-300',
    cleaning: 'border-sky-200 bg-sky-50/40 hover:border-sky-300',
    out_of_service: 'border-rose-200 bg-rose-50/40 hover:border-rose-300',
};

const STATUS_DOT = {
    available: 'bg-emerald-500',
    occupied: 'bg-indigo-500',
    reserved: 'bg-amber-500',
    cleaning: 'bg-sky-500',
    out_of_service: 'bg-rose-500',
};

function BedFilters({ hospital, hospitals, filters, inventory, categories, onApply, onReset }) {
    const [values, setValues] = useState({
        search: filters.search ?? '',
        ward_id: filters.ward_id ?? '',
        category: filters.category ?? '',
        status: filters.status ?? '',
    });

    useEffect(() => {
        setValues({
            search: filters.search ?? '',
            ward_id: filters.ward_id ?? '',
            category: filters.category ?? '',
            status: filters.status ?? '',
        });
    }, [filters.search, filters.ward_id, filters.category, filters.status]);

    const update = (key, value) => setValues((current) => ({ ...current, [key]: value }));

    return (
        <Card className="p-4 sm:p-5">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    onApply(values);
                }}
                className="space-y-3"
            >
                <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
                    {hospitals.length > 1 && (
                        <Field label="Hospital" htmlFor="bed-hospital" className="lg:w-60">
                            <Select
                                id="bed-hospital"
                                value={hospital.id}
                                onChange={(event) =>
                                    router.get(
                                        '/beds',
                                        { hospital: event.target.value, ...Object.fromEntries(Object.entries({ ...filters, ...values }).filter(([, value]) => value)) },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {hospitals.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.short_name || item.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    )}

                    <Field label="Search bed code" htmlFor="bed-search" className="lg:w-52">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
                            <input
                                id="bed-search"
                                type="search"
                                value={values.search}
                                onChange={(event) => update('search', event.target.value)}
                                placeholder="e.g. ER-1-A"
                                className="input pl-10"
                            />
                        </div>
                    </Field>

                    <Field label="Ward" htmlFor="bed-ward" className="lg:w-52">
                        <Select id="bed-ward" value={values.ward_id} onChange={(event) => update('ward_id', event.target.value)}>
                            <option value="">All wards</option>
                            {inventory.map((ward) => (
                                <option key={ward.id} value={ward.id}>
                                    {ward.name}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Bed category" htmlFor="bed-category" className="lg:w-48">
                        <Select id="bed-category" value={values.category} onChange={(event) => update('category', event.target.value)}>
                            <option value="">All categories</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.slug}>
                                    {category.name}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Status" htmlFor="bed-status" className="lg:w-44">
                        <Select id="bed-status" value={values.status} onChange={(event) => update('status', event.target.value)}>
                            <option value="">All statuses</option>
                            {MANUAL_STATUSES.concat({ value: 'reserved', label: 'Reserved' }).map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>

                <div className="flex flex-wrap items-center justify-end gap-2 border-t border-ink-100 pt-3">
                    <Button type="button" variant="ghost" size="sm" onClick={onReset}>
                        <FilterX className="size-4" />
                        Clear filters
                    </Button>
                    <Button type="submit" size="sm">
                        Apply filters
                    </Button>
                </div>
            </form>
        </Card>
    );
}

function BedTile({ bed, canUpdate, selected, onSelect, onOpen }) {
    const reservation = bed.reservation;

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={() => onOpen(bed)}
            onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    onOpen(bed);
                }
            }}
            className={classNames(
                'group relative cursor-pointer rounded-xl border p-3 text-left transition hover:shadow-sm',
                STATUS_TILE[bed.status] ?? 'border-ink-200 bg-white',
                selected && 'ring-2 ring-brand-500 ring-offset-1',
            )}
            aria-label={`${bed.bed_code}, ${bed.status_label}`}
        >
            {canUpdate && (
                <button
                    type="button"
                    onClick={(event) => {
                        event.stopPropagation();
                        onSelect(bed.id);
                    }}
                    className="absolute top-2 right-2 flex size-6 items-center justify-center rounded-md bg-white/80 text-ink-400 opacity-0 ring-1 ring-ink-200 transition group-hover:opacity-100 focus:opacity-100"
                    aria-label={selected ? `Deselect ${bed.bed_code}` : `Select ${bed.bed_code}`}
                >
                    {selected ? <CheckSquare className="size-4 text-brand-600" /> : <Square className="size-4" />}
                </button>
            )}

            <div className="flex items-center gap-1.5">
                <span className={classNames('size-2 rounded-full', STATUS_DOT[bed.status] ?? 'bg-ink-300')} />
                <span className="font-mono text-xs font-bold text-ink-900">{bed.bed_code}</span>
            </div>
            <p className="mt-1 truncate text-[10px] font-medium text-ink-500">{bed.category?.name ?? 'Uncategorized'}</p>
            <p className="mt-0.5 truncate text-[10px] text-ink-400">
                {bed.ward?.name ?? 'No ward'} · {bed.room ?? 'No room'}
            </p>
            <div className="mt-2 flex flex-wrap items-center gap-1">
                <BedStatusBadge status={bed.status} label={bed.status_label} dot={false} />
                {reservation && (
                    <span className="inline-flex items-center gap-0.5 rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold text-amber-800">
                        Hold
                    </span>
                )}
            </div>
            <p className="mt-2 line-clamp-1 text-[9px] text-ink-400">{relativeTime(bed.updated_at)}</p>
        </div>
    );
}

function BedDetailModal({ bed, canUpdate, canManage, onClose }) {
    const page = usePage();
    const [history, setHistory] = useState([]);
    const [historyLoading, setHistoryLoading] = useState(true);
    const [confirmRemove, setConfirmRemove] = useState(false);
    const form = useForm({
        bed_id: bed.id,
        status: bed.status === 'reserved' ? 'reserved' : bed.status,
        reason: '',
        expected_updated_at: bed.updated_at ?? '',
    });

    useEffect(() => {
        const controller = new AbortController();
        setHistoryLoading(true);

        fetch(`/beds/${bed.id}/history`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) throw new Error('Unable to load history');
                return response.json();
            })
            .then((payload) => setHistory(payload.data ?? []))
            .catch((error) => {
                if (error.name !== 'AbortError') setHistory([]);
            })
            .finally(() => {
                if (!controller.signal.aborted) setHistoryLoading(false);
            });

        return () => controller.abort();
    }, [bed.id]);

    const hasReservation = Boolean(bed.reservation);
    const statusOptions = MANUAL_STATUSES.concat(hasReservation ? [{ value: 'reserved', label: 'Reserved (held)' }] : []);

    const submitStatus = (event) => {
        event.preventDefault();
        form.post('/beds/status', {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    const toggleActive = () => {
        router.patch(`/beds/${bed.id}/activation`, {}, { preserveScroll: true, onSuccess: () => onClose() });
    };

    return (
        <Modal
            open
            onClose={onClose}
            title={bed.bed_code}
            description={`${bed.category?.name ?? 'Uncategorized'} · ${bed.ward?.name ?? 'No ward'} · ${bed.room ?? 'No room'}`}
            size="lg"
            footer={
                <>
                    <Button variant="secondary" size="sm" onClick={onClose}>
                        Close
                    </Button>
                    {canUpdate && !hasReservation && (
                        <Button size="sm" onClick={submitStatus} disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save status'}
                        </Button>
                    )}
                </>
            }
        >
            <div className="space-y-5">
                <div className="flex flex-wrap items-center gap-2">
                    <BedStatusBadge status={bed.status} label={bed.status_label} />
                    <Badge tone="slate">Updated {relativeTime(bed.updated_at)}</Badge>
                    {bed.updated_by && <span className="text-[11px] text-ink-500">by {bed.updated_by}</span>}
                    {bed.reservation && <Badge tone="amber">Held for referral</Badge>}
                </div>

                {bed.notes && <InfoCallout tone="slate">{bed.notes}</InfoCallout>}

                {bed.reservation && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                        <p className="text-xs font-semibold text-amber-900">Reservation active</p>
                        <p className="mt-1 text-xs text-amber-800">
                            Held for referral{' '}
                            {bed.reservation.referral_number ? (
                                <Link href={`/referrals/${bed.reservation.referral_id}`} className="font-semibold underline">
                                    {bed.reservation.referral_number}
                                </Link>
                            ) : (
                                `#${bed.reservation.referral_id}`
                            )}
                            . Expires {formatDateTime(bed.reservation.expires_at)}. Change the status through the referral workflow.
                        </p>
                    </div>
                )}

                {canUpdate && !hasReservation && (
                    <form onSubmit={submitStatus} className="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                        <p className="text-xs font-semibold text-ink-900">Report a status change</p>
                        <p className="subtle mt-1">Reserved status is set only by accepting a referral and holding a bed.</p>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            <Field label="New status" htmlFor="bed-new-status" error={form.errors.status} required>
                                <Select
                                    id="bed-new-status"
                                    value={form.data.status}
                                    onChange={(event) => form.setData('status', event.target.value)}
                                    error={form.errors.status}
                                >
                                    {statusOptions.map((status) => (
                                        <option key={status.value} value={status.value}>
                                            {status.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field
                                label="Reason"
                                htmlFor="bed-reason"
                                error={form.errors.reason}
                                hint="Required when taking a bed out of service."
                            >
                                <TextInput
                                    id="bed-reason"
                                    value={form.data.reason}
                                    onChange={(event) => form.setData('reason', event.target.value)}
                                    error={form.errors.reason}
                                    placeholder="e.g. Terminal cleaning"
                                />
                            </Field>
                        </div>
                        <input type="hidden" value={form.data.expected_updated_at} readOnly />
                        {form.errors.bed && <p className="field-error">{form.errors.bed}</p>}
                        {form.errors.expected_updated_at && <p className="field-error">{form.errors.expected_updated_at}</p>}
                        <p className="subtle mt-2">Concurrency check: this update is tied to the report you opened.</p>
                    </form>
                )}

                {!canUpdate && <InfoCallout tone="slate">Your role has read-only access to this bed board.</InfoCallout>}

                <div>
                    <div className="mb-3 flex items-center gap-2">
                        <History className="size-4 text-ink-400" />
                        <p className="text-xs font-semibold text-ink-900">Status history</p>
                        <span className="text-[10px] text-ink-400">last 50 changes</span>
                    </div>
                    {historyLoading ? (
                        <p className="text-xs text-ink-500">Loading history…</p>
                    ) : history.length ? (
                        <ul className="max-h-64 space-y-2.5 overflow-y-auto pr-1">
                            {history.map((entry) => (
                                <li key={entry.id} className="rounded-lg border border-ink-100 px-3 py-2">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex items-center gap-1.5">
                                            {entry.old_status && (
                                                <>
                                                    <BedStatusBadge status={entry.old_status} label={entry.old_status.replace(/_/g, ' ')} dot={false} />
                                                    <ChevronRight className="size-3 text-ink-400" />
                                                </>
                                            )}
                                            <BedStatusBadge status={entry.new_status} label={entry.new_status.replace(/_/g, ' ')} dot={false} />
                                        </div>
                                        <span className="text-[10px] text-ink-400">{formatDateTime(entry.changed_at)}</span>
                                    </div>
                                    <p className="mt-1 text-[11px] text-ink-500">
                                        {entry.changed_by} {entry.reason ? `· ${entry.reason}` : ''}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-xs text-ink-500">No status changes have been recorded.</p>
                    )}
                </div>

                {canManage && (
                    <div className="border-t border-ink-100 pt-4">
                        {confirmRemove ? (
                            <div className="rounded-xl border border-rose-200 bg-rose-50 p-3">
                                <p className="text-xs font-semibold text-rose-900">Remove {bed.bed_code} from the active board?</p>
                                <p className="mt-1 text-[11px] text-rose-700">It will no longer appear in availability counts. History is retained.</p>
                                <div className="mt-3 flex gap-2">
                                    <Button variant="danger" size="sm" onClick={toggleActive}>
                                        Yes, remove bed
                                    </Button>
                                    <Button variant="secondary" size="sm" onClick={() => setConfirmRemove(false)}>
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <button
                                type="button"
                                onClick={() => setConfirmRemove(true)}
                                className="inline-flex items-center gap-1.5 text-[11px] font-semibold text-rose-600 hover:underline"
                            >
                                <Trash2 className="size-3.5" />
                                Remove from active board
                            </button>
                        )}
                    </div>
                )}
            </div>
        </Modal>
    );
}

function WardForm({ hospital }) {
    const form = useForm({ hospital_id: hospital.id, name: '', ward_type: 'inpatient', floor: '' });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/beds/wards', { preserveScroll: true, onSuccess: () => form.reset('name', 'floor') });
            }}
            className="space-y-4"
        >
            <Field label="Ward name" htmlFor="ward-name" error={form.errors.name} required>
                <TextInput
                    id="ward-name"
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    error={form.errors.name}
                    placeholder="e.g. Medical Ward B"
                />
            </Field>
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Ward type" htmlFor="ward-type" error={form.errors.ward_type} required>
                    <Select id="ward-type" value={form.data.ward_type} onChange={(event) => form.setData('ward_type', event.target.value)}>
                        {WARD_TYPES.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Floor" htmlFor="ward-floor" error={form.errors.floor}>
                    <TextInput
                        id="ward-floor"
                        value={form.data.floor}
                        onChange={(event) => form.setData('floor', event.target.value)}
                        placeholder="e.g. 3rd floor"
                    />
                </Field>
            </div>
            <Button type="submit" size="sm" disabled={form.processing}>
                <Plus className="size-4" />
                {form.processing ? 'Adding…' : 'Add ward'}
            </Button>
        </form>
    );
}

function RoomForm({ inventory }) {
    const form = useForm({ ward_id: '', room_code: '', name: '' });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/beds/rooms', { preserveScroll: true, onSuccess: () => form.reset('room_code', 'name') });
            }}
            className="space-y-4"
        >
            <Field label="Ward" htmlFor="room-ward" error={form.errors.ward_id} required>
                <Select id="room-ward" value={form.data.ward_id} onChange={(event) => form.setData('ward_id', event.target.value)} error={form.errors.ward_id}>
                    <option value="">Select a ward</option>
                    {inventory.map((ward) => (
                        <option key={ward.id} value={ward.id}>
                            {ward.name}
                        </option>
                    ))}
                </Select>
            </Field>
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Room code" htmlFor="room-code" error={form.errors.room_code} required>
                    <TextInput
                        id="room-code"
                        value={form.data.room_code}
                        onChange={(event) => form.setData('room_code', event.target.value)}
                        error={form.errors.room_code}
                        placeholder="e.g. MW-4"
                    />
                </Field>
                <Field label="Room name" htmlFor="room-name" error={form.errors.name}>
                    <TextInput
                        id="room-name"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        placeholder="Optional label"
                    />
                </Field>
            </div>
            <Button type="submit" size="sm" disabled={form.processing}>
                <Plus className="size-4" />
                {form.processing ? 'Adding…' : 'Add room'}
            </Button>
        </form>
    );
}

function BedForm({ hospital, inventory, categories }) {
    const form = useForm({
        hospital_id: hospital.id,
        room_id: '',
        bed_category_id: '',
        bed_code: '',
        notes: '',
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/beds', { preserveScroll: true, onSuccess: () => form.reset('bed_code', 'notes') });
            }}
            className="space-y-4"
        >
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Room" htmlFor="bed-room" error={form.errors.room_id} required>
                    <Select id="bed-room" value={form.data.room_id} onChange={(event) => form.setData('room_id', event.target.value)} error={form.errors.room_id}>
                        <option value="">Select a room</option>
                        {inventory.map((ward) => (
                            <optgroup key={ward.id} label={ward.name}>
                                {ward.rooms.map((room) => (
                                    <option key={room.id} value={room.id}>
                                        {room.room_code} {room.name ? `· ${room.name}` : ''}
                                    </option>
                                ))}
                            </optgroup>
                        ))}
                    </Select>
                </Field>
                <Field label="Bed category" htmlFor="bed-category-new" error={form.errors.bed_category_id} required>
                    <Select
                        id="bed-category-new"
                        value={form.data.bed_category_id}
                        onChange={(event) => form.setData('bed_category_id', event.target.value)}
                        error={form.errors.bed_category_id}
                    >
                        <option value="">Select a category</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </Select>
                </Field>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Bed code" htmlFor="bed-code-new" error={form.errors.bed_code} hint="Unique inside this hospital." required>
                    <TextInput
                        id="bed-code-new"
                        value={form.data.bed_code}
                        onChange={(event) => form.setData('bed_code', event.target.value)}
                        error={form.errors.bed_code}
                        placeholder="e.g. MW-4-D"
                    />
                </Field>
                <Field label="Notes" htmlFor="bed-notes-new" error={form.errors.notes}>
                    <TextInput
                        id="bed-notes-new"
                        value={form.data.notes}
                        onChange={(event) => form.setData('notes', event.target.value)}
                        placeholder="Optional"
                    />
                </Field>
            </div>
            <Button type="submit" size="sm" disabled={form.processing}>
                <Plus className="size-4" />
                {form.processing ? 'Adding…' : 'Add bed as available'}
            </Button>
        </form>
    );
}

function InventoryModal({ hospital, inventory, categories, onClose }) {
    const [tab, setTab] = useState('ward');
    const tabs = [
        { value: 'ward', label: 'Add ward' },
        { value: 'room', label: 'Add room' },
        { value: 'bed', label: 'Add bed' },
    ];

    return (
        <Modal open onClose={onClose} title="Inventory setup" description={`${hospital.name} · wards, rooms, and individual beds`} size="lg" footer={<Button variant="secondary" size="sm" onClick={onClose}>Done</Button>}>
            <div className="flex flex-wrap gap-1 rounded-xl bg-ink-100/80 p-1">
                {tabs.map((item) => (
                    <button
                        key={item.value}
                        type="button"
                        onClick={() => setTab(item.value)}
                        className={classNames(
                            'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                            tab === item.value ? 'bg-white text-ink-900 shadow-sm' : 'text-ink-500 hover:text-ink-800',
                        )}
                    >
                        {item.label}
                    </button>
                ))}
            </div>

            <div className="mt-5">
                {tab === 'ward' && <WardForm hospital={hospital} />}
                {tab === 'room' && <RoomForm inventory={inventory} />}
                {tab === 'bed' && <BedForm hospital={hospital} inventory={inventory} categories={categories} />}
            </div>

            <div className="mt-6 rounded-xl bg-ink-50 p-3 text-[11px] leading-5 text-ink-500">
                New beds start as <span className="font-semibold text-emerald-700">available</span>. Every change is recorded in the bed history and audit trail.
            </div>
        </Modal>
    );
}

export default function BedsIndex({
    hospital,
    hospitals = [],
    summary,
    wards = [],
    inventory = [],
    categories = [],
    filters = {},
    canUpdate = false,
    canManage = false,
    bedCount = 0,
}) {
    const page = usePage();
    const [selectedBed, setSelectedBed] = useState(null);
    const [selectedIds, setSelectedIds] = useState([]);
    const [bulkStatus, setBulkStatus] = useState('');
    const [bulkReason, setBulkReason] = useState('');
    const [inventoryOpen, setInventoryOpen] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const counts = summary.counts;
    const errors = page.props.errors ?? {};

    const totalShown = useMemo(() => wards.reduce((sum, ward) => sum + ward.beds.length, 0), [wards]);
    const availableShown = useMemo(
        () => wards.reduce((sum, ward) => sum + ward.beds.filter((bed) => bed.status === 'available').length, 0),
        [wards],
    );

    const applyFilters = (values) => {
        const params = Object.fromEntries(Object.entries({ hospital: hospital.id, ...values }).filter(([, value]) => value !== '' && value !== null));
        router.get('/beds', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const resetFilters = () => router.get('/beds', { hospital: hospital.id }, { preserveState: true, preserveScroll: true, replace: true });

    const refresh = () => {
        setRefreshing(true);
        router.reload({ preserveScroll: true, preserveState: true, onFinish: () => setRefreshing(false) });
    };

    const toggleSelected = (id) => {
        setSelectedIds((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]));
    };

    const applyBulk = () => {
        if (!bulkStatus) return;

        router.post(
            '/beds/bulk-status',
            {
                hospital_id: hospital.id,
                bed_ids: selectedIds,
                status: bulkStatus,
                reason: bulkReason || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedIds([]);
                    setBulkStatus('');
                    setBulkReason('');
                },
            },
        );
    };

    return (
        <AppLayout>
            <Head title={`Bed board · ${hospital.short_name || hospital.name}`} />

            <div className="space-y-6 pb-24">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="eyebrow">Bed availability</p>
                            <FreshnessBadge freshness={summary.freshness} label={summary.freshness_label} />
                            {!canUpdate && <Badge tone="slate">Read-only</Badge>}
                        </div>
                        <h1 className="heading-1 mt-2">{hospital.name}</h1>
                        <p className="muted mt-2">
                            {hospital.location} · <span className="font-mono text-xs">{hospital.hospital_code}</span> · {formatNumber(bedCount)} beds in current
                            view
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="secondary" size="sm" onClick={refresh} disabled={refreshing}>
                            <RefreshCw className={classNames('size-4', refreshing && 'animate-spin')} />
                            Refresh
                        </Button>
                        <Link href={`/hospitals/${hospital.id}`} className="btn btn-secondary btn-sm">
                            <Building2 className="size-4" />
                            Hospital profile
                        </Link>
                        {canManage && (
                            <Button size="sm" onClick={() => setInventoryOpen(true)}>
                                <Settings2 className="size-4" />
                                Inventory setup
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Available"
                        value={formatNumber(counts.available)}
                        hint={`${formatNumber(counts.operational)} operational of ${formatNumber(counts.total)} total`}
                        icon={BedDouble}
                        tone="emerald"
                    />
                    <StatCard label="Occupied" value={formatNumber(counts.occupied)} hint={`${formatNumber(counts.reserved)} reserved holds`} icon={Layers} tone="indigo" />
                    <StatCard label="Cleaning" value={formatNumber(counts.cleaning)} hint="Temporarily unavailable" icon={Clock3} tone="sky" />
                    <StatCard label="Out of service" value={formatNumber(counts.out_of_service)} hint={`${formatNumber(counts.total)} beds configured`} icon={ShieldAlert} tone="rose" />
                </div>

                <InfoCallout tone={summary.freshness === 'stale' ? 'amber' : 'brand'} title={summary.freshness === 'stale' ? 'This bed board is stale' : 'Manual reporting'}>
                    Last reported {relativeTime(summary.last_updated_at)}
                    {summary.last_updated_by ? ` by ${summary.last_updated_by}` : ''}
                    {summary.last_updated_at && ` (${formatMinutes(minutesSince(summary.last_updated_at))})`}. Availability is not a confirmed admission; reserve beds
                    through the referral workflow.
                </InfoCallout>

                <BedFilters
                    hospital={hospital}
                    hospitals={hospitals}
                    filters={filters}
                    inventory={inventory}
                    categories={categories}
                    onApply={applyFilters}
                    onReset={resetFilters}
                />

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-xs text-ink-500">
                        Showing <span className="font-semibold text-ink-800">{formatNumber(totalShown)}</span> beds ·{' '}
                        <span className="font-semibold text-emerald-700">{formatNumber(availableShown)}</span> available in view
                    </p>
                    <div className="flex flex-wrap items-center gap-3 text-[10px] text-ink-500">
                        {['available', 'occupied', 'reserved', 'cleaning', 'out_of_service'].map((status) => (
                            <span key={status} className="inline-flex items-center gap-1.5">
                                <span className={classNames('size-2 rounded-full', STATUS_DOT[status])} />
                                {humanize(status)}
                            </span>
                        ))}
                    </div>
                </div>

                {wards.length ? (
                    <div className="space-y-5">
                        {wards.map((ward) => {
                            const available = ward.beds.filter((bed) => bed.status === 'available').length;

                            return (
                                <Card key={ward.id}>
                                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ink-100 px-5 py-3.5">
                                        <div className="flex items-center gap-3">
                                            <span className="icon-tile size-9 bg-ink-100 text-ink-600">
                                                <DoorOpen className="size-4" />
                                            </span>
                                            <div>
                                                <h2 className="heading-3">{ward.name}</h2>
                                                <p className="subtle capitalize">{humanize(ward.ward_type)} · {formatNumber(ward.beds.length)} beds</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge tone="emerald">{formatNumber(available)} available</Badge>
                                            {canUpdate && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        const ids = ward.beds.map((bed) => bed.id);
                                                        const allSelected = ids.every((id) => selectedIds.includes(id));
                                                        setSelectedIds((current) =>
                                                            allSelected ? current.filter((id) => !ids.includes(id)) : Array.from(new Set([...current, ...ids])),
                                                        );
                                                    }}
                                                >
                                                    {ward.beds.every((bed) => selectedIds.includes(bed.id)) ? 'Clear ward' : 'Select ward'}
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2.5 p-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                                        {ward.beds.map((bed) => (
                                            <BedTile
                                                key={bed.id}
                                                bed={bed}
                                                canUpdate={canUpdate}
                                                selected={selectedIds.includes(bed.id)}
                                                onSelect={toggleSelected}
                                                onOpen={setSelectedBed}
                                            />
                                        ))}
                                    </div>
                                </Card>
                            );
                        })}
                    </div>
                ) : (
                    <Card>
                        <EmptyState
                            icon={BedDouble}
                            title="No beds match these filters"
                            description="Try clearing the ward, category, or status filter."
                            action={
                                <Button variant="secondary" onClick={resetFilters}>
                                    Clear filters
                                </Button>
                            }
                        />
                    </Card>
                )}

                {canUpdate && selectedIds.length > 0 && (
                    <div className="sticky bottom-4 z-30">
                        <Card className="border-brand-200 p-4 shadow-[var(--shadow-lift)]">
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <p className="text-xs font-bold text-ink-900">{formatNumber(selectedIds.length)} beds selected</p>
                                    <p className="subtle mt-0.5">Beds with active reservations are skipped automatically.</p>
                                </div>
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                                    <Field label="Set status" htmlFor="bulk-status" className="sm:w-44">
                                        <Select id="bulk-status" value={bulkStatus} onChange={(event) => setBulkStatus(event.target.value)}>
                                            <option value="">Choose…</option>
                                            {MANUAL_STATUSES.map((status) => (
                                                <option key={status.value} value={status.value}>
                                                    {status.label}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                    <Field label="Reason" htmlFor="bulk-reason" className="sm:w-56">
                                        <TextInput
                                            id="bulk-reason"
                                            value={bulkReason}
                                            onChange={(event) => setBulkReason(event.target.value)}
                                            placeholder={bulkStatus === 'out_of_service' ? 'Required for OOS' : 'Optional note'}
                                        />
                                    </Field>
                                    <div className="flex items-center gap-2">
                                        <Button size="sm" onClick={applyBulk} disabled={!bulkStatus}>
                                            Apply
                                        </Button>
                                        <Button variant="ghost" size="sm" onClick={() => setSelectedIds([])}>
                                            <X className="size-4" />
                                            Clear
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            {(errors.status || errors.reason || errors.bed_ids) && (
                                <p className="field-error mt-2">{errors.status || errors.reason || errors.bed_ids}</p>
                            )}
                        </Card>
                    </div>
                )}
            </div>

            {selectedBed && (
                <BedDetailModal
                    key={selectedBed.id}
                    bed={selectedBed}
                    canUpdate={canUpdate}
                    canManage={canManage}
                    onClose={() => setSelectedBed(null)}
                />
            )}

            {inventoryOpen && (
                <InventoryModal hospital={hospital} inventory={inventory} categories={categories} onClose={() => setInventoryOpen(false)} />
            )}
        </AppLayout>
    );
}
