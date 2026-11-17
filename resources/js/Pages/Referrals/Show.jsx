import React, { useMemo, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    BedDouble,
    Building2,
    CalendarClock,
    CheckCircle2,
    ClipboardCheck,
    ClipboardList,
    Clock3,
    Download,
    FileText,
    Mail,
    MapPin,
    MessageSquareWarning,
    Paperclip,
    Phone,
    Search,
    Send,
    ShieldCheck,
    Stethoscope,
    Truck,
    UserRound,
    XCircle,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import {
    Badge,
    Button,
    Card,
    EmptyState,
    Field,
    FreshnessBadge,
    InfoCallout,
    KeyValue,
    Modal,
    PageHeader,
    ReferralStatusBadge,
    Select,
    Textarea,
    TextInput,
    TransferStatusBadge,
    UrgencyBadge,
    Avatar,
} from '../../components/ui';
import {
    classNames,
    formatBytes,
    formatDateTime,
    formatNumber,
    fromDateTimeInput,
    humanize,
    relativeTime,
    toDateTimeInput,
} from '../../lib/utils';

const STATUS_TERMINAL = ['declined', 'completed', 'cancelled', 'expired'];

const ACTION_ENDPOINTS = {
    accept: 'accept',
    decline: 'decline',
    request: 'request-information',
    cancel: 'cancel',
    reserve: 'reserve-bed',
    transfer: 'transfer',
    departure: 'departure',
    arrival: 'arrival',
    complete: 'complete',
    coordinator: 'assign-coordinator',
};

const EMPTY_ACTION = {
    response_note: '',
    coordinator_id: '',
    decline_reason: '',
    note: '',
    reason: '',
    bed_id: '',
    transport_type: 'ambulance',
    vehicle_reference: '',
    escort_contact: '',
    departure_at: '',
    estimated_arrival_at: '',
    arrival_at: '',
    notes: '',
};

function HospitalSummary({ role, hospital, tone }) {
    if (!hospital) {
        return null;
    }

    return (
        <div className="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-[10px] font-semibold tracking-[0.14em] text-ink-500 uppercase">{role}</p>
                    <p className="mt-1 truncate text-sm font-bold text-ink-900">{hospital.short_name || hospital.name}</p>
                    <p className="mt-0.5 truncate text-[11px] text-ink-500">{hospital.name}</p>
                </div>
                <Badge tone={tone}>{hospital.hospital_code}</Badge>
            </div>
            <div className="mt-3 space-y-1.5 text-[11px] text-ink-600">
                <p className="flex items-start gap-1.5">
                    <MapPin className="mt-0.5 size-3 shrink-0 text-ink-400" />
                    <span className="min-w-0">{hospital.location || hospital.address || 'Location not recorded'}</span>
                </p>
                {hospital.phone && (
                    <p className="flex items-center gap-1.5">
                        <Phone className="size-3 shrink-0 text-ink-400" />
                        <a href={`tel:${hospital.phone}`} className="truncate hover:text-brand-700">
                            {hospital.phone}
                        </a>
                    </p>
                )}
                {hospital.email && (
                    <p className="flex items-center gap-1.5">
                        <Mail className="size-3 shrink-0 text-ink-400" />
                        <a href={`mailto:${hospital.email}`} className="truncate hover:text-brand-700">
                            {hospital.email}
                        </a>
                    </p>
                )}
            </div>
        </div>
    );
}

function durationLabel(from, to) {
    if (!from || !to) return null;

    const minutes = Math.round(Math.abs(new Date(to).getTime() - new Date(from).getTime()) / 60000);
    if (Number.isNaN(minutes)) return null;
    if (minutes < 60) return `${minutes} min`;

    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    return remainder ? `${hours} hr ${remainder} min` : `${hours} hr`;
}

function TransferSteps({ status }) {
    const steps = [
        { key: 'scheduled', label: 'Scheduled' },
        { key: 'in_transit', label: 'In transit' },
        { key: 'arrived', label: 'Arrived' },
        { key: 'completed', label: 'Completed' },
    ];
    const order = { scheduled: 0, ready: 0, in_transit: 1, arrived: 2, completed: 3, cancelled: -1 };
    const current = order[status] ?? 0;

    return (
        <ol className="grid grid-cols-4 gap-1" aria-label="Transfer progress">
            {steps.map((step, index) => {
                const done = current > index;
                const active = current === index;
                const cancelled = current === -1;

                return (
                    <li key={step.key} className="min-w-0">
                        <div
                            className={classNames(
                                'h-1.5 rounded-full',
                                cancelled ? 'bg-rose-200' : done || active ? 'bg-brand-500' : 'bg-ink-100',
                            )}
                        />
                        <p
                            className={classNames(
                                'mt-1.5 truncate text-[10px] font-semibold',
                                active ? 'text-brand-700' : done ? 'text-ink-700' : 'text-ink-400',
                            )}
                        >
                            {step.label}
                        </p>
                    </li>
                );
            })}
        </ol>
    );
}

export default function ReferralShow({
    referral,
    abilities = {},
    receivingAvailability,
    availableBeds = [],
    coordinators = [],
    transportOptions = [],
}) {
    const [modal, setModal] = useState(null);
    const [bedSearch, setBedSearch] = useState('');

    const actionForm = useForm({ ...EMPTY_ACTION });

    const documentForm = useForm({ document: null });

    const terminal = STATUS_TERMINAL.includes(referral.status);
    const reservation = referral.reservation;
    const transfer = referral.transfer;

    const filteredBeds = useMemo(() => {
        const search = bedSearch.trim().toLowerCase();
        if (!search) return availableBeds;

        return availableBeds.filter((bed) =>
            [bed.bed_code, bed.category, bed.ward, bed.room]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(search)),
        );
    }, [availableBeds, bedSearch]);

    const bedGroups = useMemo(() => {
        const groups = new Map();

        filteredBeds.forEach((bed) => {
            const key = bed.category ?? 'Uncategorized';
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(bed);
        });

        return Array.from(groups.entries());
    }, [filteredBeds]);

    const erAvailable = receivingAvailability?.categories
        ?.filter((category) => category.is_er)
        .reduce((sum, category) => sum + category.available, 0);

    const openModal = (kind) => {
        actionForm.clearErrors();
        setBedSearch('');

        const defaults = {};

        if (kind === 'accept') {
            defaults.response_note = '';
            defaults.coordinator_id = referral.coordinator?.id ?? coordinators[0]?.id ?? '';
        }

        if (kind === 'coordinator') {
            defaults.coordinator_id = referral.coordinator?.id ?? coordinators[0]?.id ?? '';
        }

        if (kind === 'transfer') {
            defaults.transport_type = transportOptions[0]?.value ?? 'ambulance';
            defaults.vehicle_reference = '';
            defaults.escort_contact = referral.contact_phone ?? '';
            defaults.departure_at = '';
            defaults.estimated_arrival_at = '';
            defaults.notes = '';
        }

        if (kind === 'departure') {
            defaults.departure_at = toDateTimeInput(new Date());
            defaults.vehicle_reference = transfer?.vehicle_reference ?? '';
            defaults.escort_contact = transfer?.escort_contact ?? '';
            defaults.notes = '';
        }

        if (kind === 'arrival') {
            defaults.arrival_at = toDateTimeInput(new Date());
            defaults.notes = '';
        }

        actionForm.setData({ ...EMPTY_ACTION, transport_type: transportOptions[0]?.value ?? 'ambulance', ...defaults });
        setModal(kind);
    };

    const closeModal = () => {
        setModal(null);
        actionForm.clearErrors();
    };

    const postDirect = (endpoint) => {
        actionForm.transform((data) => data);
        actionForm.post(`/referrals/${referral.id}/${endpoint}`, {
            preserveScroll: true,
        });
    };

    const submitModal = () => {
        if (!modal) return;

        actionForm.transform((data) => {
            if (['transfer', 'departure', 'arrival'].includes(modal)) {
                return {
                    ...data,
                    departure_at: fromDateTimeInput(data.departure_at),
                    estimated_arrival_at: fromDateTimeInput(data.estimated_arrival_at),
                    arrival_at: fromDateTimeInput(data.arrival_at),
                };
            }

            return data;
        });

        actionForm.post(`/referrals/${referral.id}/${ACTION_ENDPOINTS[modal]}`, {
            preserveScroll: true,
            onSuccess: () => setModal(null),
        });
    };

    const uploadDocument = (event) => {
        event.preventDefault();
        documentForm.post(`/referrals/${referral.id}/documents`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => documentForm.reset('document'),
        });
    };

    const hasDecisionActions =
        abilities.can_submit ||
        abilities.can_accept ||
        abilities.can_decline ||
        abilities.can_request_information ||
        abilities.can_reserve_bed ||
        abilities.can_schedule_transfer ||
        abilities.can_record_departure ||
        (abilities.can_confirm_arrival && transfer?.status === 'in_transit') ||
        abilities.can_complete_transfer ||
        (abilities.is_receiving && coordinators.length > 0 && !terminal);

    const modalMeta = {
        accept: {
            title: 'Accept referral',
            description: 'Confirm the receiving hospital can accept this patient and assign a coordinator.',
            confirm: 'Accept referral',
            icon: ClipboardCheck,
            variant: 'primary',
        },
        decline: {
            title: 'Decline referral',
            description: 'The referring hospital will be notified. A clear reason is required.',
            confirm: 'Decline referral',
            icon: XCircle,
            variant: 'danger',
        },
        request: {
            title: 'Request more information',
            description: 'Ask the referring hospital for the details needed to make a safe decision.',
            confirm: 'Send request',
            icon: MessageSquareWarning,
            variant: 'warn',
        },
        cancel: {
            title: 'Cancel referral',
            description: 'This closes the referral and releases any active bed hold. This cannot be undone.',
            confirm: 'Cancel referral',
            icon: XCircle,
            variant: 'danger',
        },
        reserve: {
            title: 'Reserve a bed',
            description: 'The bed is held exclusively for this referral until the reservation expires.',
            confirm: 'Reserve bed',
            icon: BedDouble,
            variant: 'primary',
        },
        transfer: {
            title: 'Schedule transfer',
            description: 'Record how the patient will move between hospitals. The referral status becomes transfer scheduled.',
            confirm: 'Schedule transfer',
            icon: Truck,
            variant: 'primary',
        },
        departure: {
            title: 'Record departure',
            description: 'Mark the patient as in transit. The receiving hospital will be notified.',
            confirm: 'Record departure',
            icon: Truck,
            variant: 'primary',
        },
        arrival: {
            title: 'Confirm arrival',
            description: 'Confirm the patient reached the receiving hospital. An active bed hold becomes occupied.',
            confirm: 'Confirm arrival',
            icon: CheckCircle2,
            variant: 'primary',
        },
        complete: {
            title: 'Complete transfer',
            description: 'Close the referral coordination record. IPHN does not store a clinical record.',
            confirm: 'Complete transfer',
            icon: CheckCircle2,
            variant: 'primary',
        },
        coordinator: {
            title: 'Assign coordinator',
            description: 'Choose an active member of the receiving hospital to coordinate this referral.',
            confirm: 'Assign coordinator',
            icon: UserRound,
            variant: 'primary',
        },
    };

    const meta = modal ? modalMeta[modal] : null;
    const MetaIcon = meta?.icon;

    const renderModalContent = () => {
        if (!modal) return null;

        if (modal === 'accept') {
            return (
                <div className="space-y-4">
                    <Field label="Response note" htmlFor="accept-note" error={actionForm.errors.response_note} hint="Optional. Shared with the referring hospital.">
                        <Textarea
                            id="accept-note"
                            rows={3}
                            value={actionForm.data.response_note}
                            onChange={(event) => actionForm.setData('response_note', event.target.value)}
                            error={actionForm.errors.response_note}
                            placeholder="Bed category, admission instructions, or coordination notes"
                        />
                    </Field>
                    <Field
                        label="Coordinator"
                        htmlFor="accept-coordinator"
                        error={actionForm.errors.coordinator_id}
                        hint="Defaults to you. Only active receiving-hospital members can be assigned."
                    >
                        <Select
                            id="accept-coordinator"
                            value={actionForm.data.coordinator_id}
                            onChange={(event) => actionForm.setData('coordinator_id', event.target.value)}
                            error={actionForm.errors.coordinator_id}
                        >
                            {coordinators.map((coordinator) => (
                                <option key={coordinator.id} value={coordinator.id}>
                                    {coordinator.name} · {coordinator.email}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>
            );
        }

        if (modal === 'decline') {
            return (
                <Field label="Decline reason" htmlFor="decline-reason" error={actionForm.errors.decline_reason} required>
                    <Textarea
                        id="decline-reason"
                        rows={4}
                        value={actionForm.data.decline_reason}
                        onChange={(event) => actionForm.setData('decline_reason', event.target.value)}
                        error={actionForm.errors.decline_reason}
                        placeholder="Explain why this hospital cannot accept the referral"
                    />
                </Field>
            );
        }

        if (modal === 'request') {
            return (
                <Field label="Information required" htmlFor="request-note" error={actionForm.errors.note} required>
                    <Textarea
                        id="request-note"
                        rows={4}
                        value={actionForm.data.note}
                        onChange={(event) => actionForm.setData('note', event.target.value)}
                        error={actionForm.errors.note}
                        placeholder="List the additional details the referring team should provide"
                    />
                </Field>
            );
        }

        if (modal === 'cancel') {
            return (
                <Field label="Cancellation reason" htmlFor="cancel-reason" error={actionForm.errors.reason} required>
                    <Textarea
                        id="cancel-reason"
                        rows={4}
                        value={actionForm.data.reason}
                        onChange={(event) => actionForm.setData('reason', event.target.value)}
                        error={actionForm.errors.reason}
                        placeholder="Reason for cancelling the referral"
                    />
                </Field>
            );
        }

        if (modal === 'reserve') {
            return (
                <div className="space-y-3">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
                        <input
                            type="search"
                            value={bedSearch}
                            onChange={(event) => setBedSearch(event.target.value)}
                            className="input pl-10"
                            placeholder="Search bed code, category, ward, or room"
                            aria-label="Search available beds"
                        />
                    </div>

                    {actionForm.errors.bed_id && <p className="field-error">{actionForm.errors.bed_id}</p>}

                    {bedGroups.length ? (
                        <div className="max-h-80 space-y-3 overflow-y-auto pr-1">
                            {bedGroups.map(([category, beds]) => (
                                <div key={category}>
                                    <p className="mb-1.5 text-[10px] font-semibold tracking-wide text-ink-500 uppercase">
                                        {category} · {beds.length}
                                    </p>
                                    <div className="grid gap-1.5 sm:grid-cols-2">
                                        {beds.map((bed) => {
                                            const selected = Number(actionForm.data.bed_id) === bed.id;

                                            return (
                                                <button
                                                    key={bed.id}
                                                    type="button"
                                                    onClick={() => actionForm.setData('bed_id', bed.id)}
                                                    className={classNames(
                                                        'rounded-lg border p-2.5 text-left transition',
                                                        selected
                                                            ? 'border-brand-500 bg-brand-50/60 ring-2 ring-brand-500/20'
                                                            : 'border-ink-100 bg-white hover:border-brand-200',
                                                    )}
                                                    aria-pressed={selected}
                                                >
                                                    <span className="flex items-center justify-between gap-2">
                                                        <span className="text-xs font-bold text-ink-900">{bed.bed_code}</span>
                                                        {bed.is_er && <Badge tone="rose">ER</Badge>}
                                                    </span>
                                                    <span className="mt-1 block truncate text-[10px] text-ink-500">
                                                        {[bed.ward, bed.room].filter(Boolean).join(' · ') || bed.category}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed border-ink-200 p-6 text-center">
                            <BedDouble className="mx-auto size-6 text-ink-300" />
                            <p className="mt-2 text-xs font-semibold text-ink-700">
                                {availableBeds.length ? 'No beds match this search' : 'No available beds are reported'}
                            </p>
                            <p className="mt-1 text-[10px] text-ink-500">
                                Bed holds require an available bed at the receiving hospital.
                            </p>
                        </div>
                    )}

                    <p className="text-[10px] leading-4 text-ink-400">
                        Only active beds currently marked available are listed. Reserving changes the bed to reserved.
                    </p>
                </div>
            );
        }

        if (modal === 'transfer') {
            return (
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Transport type" htmlFor="transport-type" error={actionForm.errors.transport_type} required>
                        <Select
                            id="transport-type"
                            value={actionForm.data.transport_type}
                            onChange={(event) => actionForm.setData('transport_type', event.target.value)}
                            error={actionForm.errors.transport_type}
                        >
                            {transportOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Vehicle reference" htmlFor="vehicle-reference" error={actionForm.errors.vehicle_reference}>
                        <TextInput
                            id="vehicle-reference"
                            value={actionForm.data.vehicle_reference}
                            onChange={(event) => actionForm.setData('vehicle_reference', event.target.value)}
                            error={actionForm.errors.vehicle_reference}
                            placeholder="Plate number or ambulance call sign"
                        />
                    </Field>
                    <Field label="Escort contact" htmlFor="escort-contact" error={actionForm.errors.escort_contact}>
                        <TextInput
                            id="escort-contact"
                            value={actionForm.data.escort_contact}
                            onChange={(event) => actionForm.setData('escort_contact', event.target.value)}
                            error={actionForm.errors.escort_contact}
                            placeholder="Phone number of the escort or crew"
                        />
                    </Field>
                    <div />
                    <Field label="Planned departure" htmlFor="planned-departure" error={actionForm.errors.departure_at}>
                        <TextInput
                            id="planned-departure"
                            type="datetime-local"
                            value={actionForm.data.departure_at}
                            onChange={(event) => actionForm.setData('departure_at', event.target.value)}
                            error={actionForm.errors.departure_at}
                        />
                    </Field>
                    <Field label="Estimated arrival" htmlFor="estimated-arrival" error={actionForm.errors.estimated_arrival_at}>
                        <TextInput
                            id="estimated-arrival"
                            type="datetime-local"
                            value={actionForm.data.estimated_arrival_at}
                            onChange={(event) => actionForm.setData('estimated_arrival_at', event.target.value)}
                            error={actionForm.errors.estimated_arrival_at}
                        />
                    </Field>
                    <Field label="Notes" htmlFor="transfer-notes" error={actionForm.errors.notes} className="sm:col-span-2">
                        <Textarea
                            id="transfer-notes"
                            rows={3}
                            value={actionForm.data.notes}
                            onChange={(event) => actionForm.setData('notes', event.target.value)}
                            error={actionForm.errors.notes}
                            placeholder="Handover or logistics notes"
                        />
                    </Field>
                    <p className="text-[10px] leading-4 text-ink-400 sm:col-span-2">
                        Times are entered in Asia/Manila and stored in UTC. Only authorized referring-hospital staff can schedule a transfer.
                    </p>
                </div>
            );
        }

        if (modal === 'departure') {
            return (
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Departure time" htmlFor="departure-at" error={actionForm.errors.departure_at} required>
                        <TextInput
                            id="departure-at"
                            type="datetime-local"
                            value={actionForm.data.departure_at}
                            onChange={(event) => actionForm.setData('departure_at', event.target.value)}
                            error={actionForm.errors.departure_at}
                        />
                    </Field>
                    <Field label="Vehicle reference" htmlFor="departure-vehicle" error={actionForm.errors.vehicle_reference}>
                        <TextInput
                            id="departure-vehicle"
                            value={actionForm.data.vehicle_reference}
                            onChange={(event) => actionForm.setData('vehicle_reference', event.target.value)}
                            error={actionForm.errors.vehicle_reference}
                            placeholder="Plate number or call sign"
                        />
                    </Field>
                    <Field label="Escort contact" htmlFor="departure-escort" error={actionForm.errors.escort_contact}>
                        <TextInput
                            id="departure-escort"
                            value={actionForm.data.escort_contact}
                            onChange={(event) => actionForm.setData('escort_contact', event.target.value)}
                            error={actionForm.errors.escort_contact}
                            placeholder="Reachable phone number"
                        />
                    </Field>
                    <Field label="Notes" htmlFor="departure-notes" error={actionForm.errors.notes}>
                        <TextInput
                            id="departure-notes"
                            value={actionForm.data.notes}
                            onChange={(event) => actionForm.setData('notes', event.target.value)}
                            error={actionForm.errors.notes}
                            placeholder="Optional handover note"
                        />
                    </Field>
                </div>
            );
        }

        if (modal === 'arrival') {
            return (
                <div className="space-y-4">
                    <Field label="Arrival time" htmlFor="arrival-at" error={actionForm.errors.arrival_at} required>
                        <TextInput
                            id="arrival-at"
                            type="datetime-local"
                            value={actionForm.data.arrival_at}
                            onChange={(event) => actionForm.setData('arrival_at', event.target.value)}
                            error={actionForm.errors.arrival_at}
                        />
                    </Field>
                    <Field label="Arrival notes" htmlFor="arrival-notes" error={actionForm.errors.notes}>
                        <Textarea
                            id="arrival-notes"
                            rows={3}
                            value={actionForm.data.notes}
                            onChange={(event) => actionForm.setData('notes', event.target.value)}
                            error={actionForm.errors.notes}
                            placeholder="Optional coordination note"
                        />
                    </Field>
                    <InfoCallout tone="brand">
                        Confirming arrival consumes the active bed hold and marks the bed occupied. Clinical details are not recorded in IPHN.
                    </InfoCallout>
                </div>
            );
        }

        if (modal === 'complete') {
            return (
                <div className="space-y-3">
                    <InfoCallout tone="emerald" title="Close the coordination record" icon={CheckCircle2}>
                        The referral becomes completed and no further status changes are allowed. The patient's clinical record remains at the receiving hospital.
                    </InfoCallout>
                    <p className="text-xs text-ink-600">
                        Referral <span className="font-semibold text-ink-900">{referral.referral_number}</span> · {referral.patient_reference}
                    </p>
                </div>
            );
        }

        if (modal === 'coordinator') {
            return (
                <Field label="Coordinator" htmlFor="assign-coordinator" error={actionForm.errors.coordinator_id} required>
                    <Select
                        id="assign-coordinator"
                        value={actionForm.data.coordinator_id}
                        onChange={(event) => actionForm.setData('coordinator_id', event.target.value)}
                        error={actionForm.errors.coordinator_id}
                    >
                        {coordinators.map((coordinator) => (
                            <option key={coordinator.id} value={coordinator.id}>
                                {coordinator.name} · {coordinator.email}
                            </option>
                        ))}
                    </Select>
                </Field>
            );
        }

        return null;
    };

    return (
        <AppLayout>
            <Head title={referral.referral_number} />

            <div className="space-y-6">
                <div>
                    <Link href="/referrals" className="mb-4 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 hover:text-brand-700">
                        <ArrowLeft className="size-3.5" />
                        Back to referrals
                    </Link>

                    <PageHeader
                        eyebrow={`Referral · ${referral.public_id}`}
                        title={referral.referral_number}
                        description={`${referral.patient_reference} · ${referral.patient_name}`}
                        actions={
                            <div className="flex flex-wrap items-center gap-2">
                                <UrgencyBadge urgency={referral.urgency} label={referral.urgency_label} />
                                <ReferralStatusBadge status={referral.status} label={referral.status_label} />
                            </div>
                        }
                    />
                </div>

                {(abilities.can_submit ||
                    abilities.can_accept ||
                    abilities.can_decline ||
                    abilities.can_request_information ||
                    abilities.can_reserve_bed ||
                    abilities.can_schedule_transfer ||
                    abilities.can_record_departure ||
                    (abilities.can_confirm_arrival && transfer?.status === 'in_transit') ||
                    abilities.can_complete_transfer ||
                    (abilities.is_receiving && coordinators.length > 0 && !terminal)) && (
                    <Card className="p-4 sm:p-5">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-start gap-3">
                                <span className="icon-tile bg-brand-50 text-brand-700">
                                    <ClipboardCheck className="size-5" />
                                </span>
                                <div>
                                    <h2 className="heading-3">Next actions</h2>
                                    <p className="subtle mt-0.5">
                                        {referral.status === 'submitted'
                                            ? 'Review the referral and record the receiving hospital decision.'
                                            : referral.status === 'accepted'
                                              ? 'Hold a bed and schedule the transfer when the patient is ready.'
                                              : referral.status === 'more_information_required'
                                                ? 'The receiving hospital is waiting for additional details.'
                                                : 'Actions are limited by your hospital role and the referral status.'}
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                {abilities.can_submit && (
                                    <Button size="sm" onClick={() => postDirect('submit')} disabled={actionForm.processing}>
                                        <Send className="size-4" />
                                        Submit referral
                                    </Button>
                                )}
                                {abilities.is_receiving && referral.status === 'submitted' && (
                                    <Button variant="secondary" size="sm" onClick={() => postDirect('start-review')} disabled={actionForm.processing}>
                                        <ClipboardList className="size-4" />
                                        Start review
                                    </Button>
                                )}
                                {abilities.can_accept && (
                                    <Button size="sm" onClick={() => openModal('accept')} disabled={actionForm.processing}>
                                        <ClipboardCheck className="size-4" />
                                        Accept
                                    </Button>
                                )}
                                {abilities.can_request_information && (
                                    <Button variant="warn" size="sm" onClick={() => openModal('request')} disabled={actionForm.processing}>
                                        <MessageSquareWarning className="size-4" />
                                        Request info
                                    </Button>
                                )}
                                {abilities.can_decline && (
                                    <Button variant="danger" size="sm" onClick={() => openModal('decline')} disabled={actionForm.processing}>
                                        <XCircle className="size-4" />
                                        Decline
                                    </Button>
                                )}
                                {abilities.can_reserve_bed && (
                                    <Button size="sm" onClick={() => openModal('reserve')} disabled={actionForm.processing}>
                                        <BedDouble className="size-4" />
                                        Reserve bed
                                    </Button>
                                )}
                                {abilities.can_schedule_transfer && (
                                    <Button size="sm" onClick={() => openModal('transfer')} disabled={actionForm.processing}>
                                        <Truck className="size-4" />
                                        Schedule transfer
                                    </Button>
                                )}
                                {abilities.can_record_departure && (
                                    <Button size="sm" onClick={() => openModal('departure')} disabled={actionForm.processing}>
                                        <Truck className="size-4" />
                                        Record departure
                                    </Button>
                                )}
                                {abilities.can_confirm_arrival && transfer?.status === 'in_transit' && (
                                    <Button size="sm" onClick={() => openModal('arrival')} disabled={actionForm.processing}>
                                        <CheckCircle2 className="size-4" />
                                        Confirm arrival
                                    </Button>
                                )}
                                {abilities.can_complete_transfer && (
                                    <Button size="sm" onClick={() => openModal('complete')} disabled={actionForm.processing}>
                                        <CheckCircle2 className="size-4" />
                                        Complete transfer
                                    </Button>
                                )}
                                {abilities.is_receiving && coordinators.length > 0 && !terminal && (
                                    <Button variant="secondary" size="sm" onClick={() => openModal('coordinator')} disabled={actionForm.processing}>
                                        <UserRound className="size-4" />
                                        Assign coordinator
                                    </Button>
                                )}
                            </div>
                        </div>

                        {actionForm.errors.referral && <p className="field-error mt-3">{actionForm.errors.referral}</p>}
                        {actionForm.errors.transfer && <p className="field-error mt-3">{actionForm.errors.transfer}</p>}
                        {actionForm.errors.status && <p className="field-error mt-3">{actionForm.errors.status}</p>}
                    </Card>
                )}

                {terminal && (
                    <InfoCallout tone={referral.status === 'declined' ? 'rose' : referral.status === 'completed' ? 'emerald' : 'slate'} title={referral.status_label}>
                        {referral.status === 'declined' && referral.decline_reason
                            ? referral.decline_reason
                            : referral.status === 'completed'
                              ? 'This referral has been completed. The coordination record remains available for audit and reporting.'
                              : 'This referral is closed. No further status changes are possible.'}
                    </InfoCallout>
                )}

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="space-y-5">
                        <Card className="p-5 sm:p-6">
                            <div className="flex items-start gap-3">
                                <span className="icon-tile bg-brand-50 text-brand-700">
                                    <Building2 className="size-5" />
                                </span>
                                <div>
                                    <h2 className="heading-3">Referral route</h2>
                                    <p className="subtle mt-1">Patient-linked records are visible only to the two hospitals below.</p>
                                </div>
                            </div>

                            <div className="mt-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:items-center">
                                <HospitalSummary role="Referring hospital" hospital={referral.referring_hospital} tone="violet" />
                                <div className="hidden justify-center text-ink-300 md:flex">
                                    <ArrowRight className="size-5" />
                                </div>
                                <HospitalSummary role="Receiving hospital" hospital={referral.receiving_hospital} tone="brand" />
                            </div>

                            <dl className="mt-5 grid grid-cols-2 gap-4 border-t border-ink-100 pt-5 sm:grid-cols-4">
                                <KeyValue label="Patient reference">{referral.patient_reference}</KeyValue>
                                <KeyValue label="Age / sex">
                                    {[referral.patient_age ? `${referral.patient_age} yr` : null, referral.patient_sex ? humanize(referral.patient_sex) : null]
                                        .filter(Boolean)
                                        .join(' · ') || '—'}
                                </KeyValue>
                                <KeyValue label="Requested service">{referral.requested_service || 'No specific service'}</KeyValue>
                                <KeyValue label="Urgency">
                                    <UrgencyBadge urgency={referral.urgency} label={referral.urgency_label} />
                                </KeyValue>
                            </dl>
                        </Card>

                        <Card className="p-5 sm:p-6">
                            <div className="flex items-start gap-3">
                                <span className="icon-tile bg-brand-50 text-brand-700">
                                    <Stethoscope className="size-5" />
                                </span>
                                <div>
                                    <h2 className="heading-3">Coordination details</h2>
                                    <p className="subtle mt-1">Minimum data required to route and coordinate the transfer.</p>
                                </div>
                            </div>

                            <div className="mt-5 rounded-xl bg-ink-50/70 p-4">
                                <p className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Reason for referral</p>
                                <p className="mt-1.5 text-sm leading-6 whitespace-pre-line text-ink-800">{referral.referral_reason}</p>
                            </div>

                            {referral.response_note && (
                                <div className="mt-4">
                                    <InfoCallout tone="emerald" title="Receiving hospital response" icon={MessageSquareWarning}>
                                        <span className="whitespace-pre-line">{referral.response_note}</span>
                                    </InfoCallout>
                                </div>
                            )}

                            {referral.decline_reason && (
                                <div className="mt-4">
                                    <InfoCallout tone="rose" title="Decline reason" icon={XCircle}>
                                        <span className="whitespace-pre-line">{referral.decline_reason}</span>
                                    </InfoCallout>
                                </div>
                            )}

                            <dl className="mt-5 grid grid-cols-2 gap-4 border-t border-ink-100 pt-5 sm:grid-cols-4">
                                <KeyValue label="Contact person">{referral.contact_name || '—'}</KeyValue>
                                <KeyValue label="Contact phone">{referral.contact_phone || '—'}</KeyValue>
                                <KeyValue label="Created by">{referral.creator?.name ?? '—'}</KeyValue>
                                <KeyValue label="Coordinator">{referral.coordinator?.name ?? 'Unassigned'}</KeyValue>
                                <KeyValue label="Created">{formatDateTime(referral.created_at)}</KeyValue>
                                <KeyValue label="Submitted">{referral.submitted_at ? formatDateTime(referral.submitted_at) : 'Not submitted'}</KeyValue>
                                <KeyValue label="Accepted">{referral.accepted_at ? formatDateTime(referral.accepted_at) : '—'}</KeyValue>
                                <KeyValue label="Closed">{referral.closed_at ? formatDateTime(referral.closed_at) : '—'}</KeyValue>
                            </dl>
                        </Card>

                        {(reservation || abilities.can_reserve_bed || referral.status === 'accepted') && (
                            <Card className="overflow-hidden">
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ink-100 px-5 py-4 sm:px-6">
                                    <div className="flex items-start gap-3">
                                        <span className="icon-tile bg-amber-50 text-amber-700">
                                            <BedDouble className="size-5" />
                                        </span>
                                        <div>
                                            <h2 className="heading-3">Bed hold</h2>
                                            <p className="subtle mt-0.5">Reserved beds cannot be double-booked.</p>
                                        </div>
                                    </div>
                                    {reservation ? <Badge tone="amber" dot>Active hold</Badge> : <Badge tone="slate">Not reserved</Badge>}
                                </div>

                                <div className="p-5 sm:p-6">
                                    {reservation ? (
                                        <>
                                            <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                                <KeyValue label="Bed" mono>{reservation.bed_code}</KeyValue>
                                                <KeyValue label="Category">{reservation.category || '—'}</KeyValue>
                                                <KeyValue label="Ward">{reservation.ward || '—'}</KeyValue>
                                                <KeyValue label="Reserved">{formatDateTime(reservation.reserved_at)}</KeyValue>
                                            </dl>
                                            <p className="mt-4 flex items-center gap-1.5 text-[11px] text-ink-500">
                                                <Clock3 className="size-3.5" />
                                                Hold expires {relativeTime(reservation.expires_at)} · {formatDateTime(reservation.expires_at)}
                                            </p>
                                        </>
                                    ) : abilities.can_reserve_bed ? (
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <p className="muted max-w-xl">
                                                This referral is accepted. Place a bed hold so the receiving ward can prepare while the transfer is arranged.
                                            </p>
                                            <Button size="sm" onClick={() => openModal('reserve')} disabled={actionForm.processing}>
                                                <BedDouble className="size-4" />
                                                Reserve a bed
                                            </Button>
                                        </div>
                                    ) : (
                                        <p className="muted">
                                            No bed is currently held. Authorized receiving-hospital admission staff can reserve an available bed for this accepted referral.
                                        </p>
                                    )}
                                </div>
                            </Card>
                        )}

                        {(transfer || abilities.can_schedule_transfer) && (
                            <Card className="overflow-hidden">
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ink-100 px-5 py-4 sm:px-6">
                                    <div className="flex items-start gap-3">
                                        <span className="icon-tile bg-sky-50 text-sky-700">
                                            <Truck className="size-5" />
                                        </span>
                                        <div>
                                            <h2 className="heading-3">Transfer coordination</h2>
                                            <p className="subtle mt-0.5">Logistics only — no clinical record is stored.</p>
                                        </div>
                                    </div>
                                    {transfer ? <TransferStatusBadge status={transfer.status} label={transfer.status_label} /> : <Badge tone="slate">Not scheduled</Badge>}
                                </div>

                                <div className="p-5 sm:p-6">
                                    {transfer ? (
                                        <>
                                            <TransferSteps status={transfer.status} />

                                            <dl className="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                                                <KeyValue label="Transport">{transfer.transport_label}</KeyValue>
                                                <KeyValue label="Vehicle">{transfer.vehicle_reference || '—'}</KeyValue>
                                                <KeyValue label="Escort contact">{transfer.escort_contact || '—'}</KeyValue>
                                                <KeyValue label="Coordinator">{transfer.coordinator || '—'}</KeyValue>
                                                <KeyValue label="Departure">
                                                    {transfer.departure_at ? formatDateTime(transfer.departure_at) : 'Not departed'}
                                                </KeyValue>
                                                <KeyValue label="Estimated arrival">
                                                    {transfer.estimated_arrival_at ? formatDateTime(transfer.estimated_arrival_at) : '—'}
                                                </KeyValue>
                                                <KeyValue label="Arrived">{transfer.arrival_at ? formatDateTime(transfer.arrival_at) : '—'}</KeyValue>
                                                <KeyValue label="Travel time">{durationLabel(transfer.departure_at, transfer.arrival_at) ?? '—'}</KeyValue>
                                            </dl>

                                            {transfer.notes && (
                                                <div className="mt-4 rounded-xl bg-ink-50/70 p-3">
                                                    <p className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Notes</p>
                                                    <p className="mt-1 text-xs leading-5 whitespace-pre-line text-ink-700">{transfer.notes}</p>
                                                </div>
                                            )}

                                            <div className="mt-5 flex flex-wrap gap-2 border-t border-ink-100 pt-4">
                                                {abilities.can_record_departure && (
                                                    <Button size="sm" onClick={() => openModal('departure')} disabled={actionForm.processing}>
                                                        <Truck className="size-4" />
                                                        Record departure
                                                    </Button>
                                                )}
                                                {abilities.can_confirm_arrival && transfer.status === 'in_transit' && (
                                                    <Button size="sm" onClick={() => openModal('arrival')} disabled={actionForm.processing}>
                                                        <CheckCircle2 className="size-4" />
                                                        Confirm arrival
                                                    </Button>
                                                )}
                                                {abilities.can_complete_transfer && (
                                                    <Button size="sm" onClick={() => openModal('complete')} disabled={actionForm.processing}>
                                                        <CheckCircle2 className="size-4" />
                                                        Complete transfer
                                                    </Button>
                                                )}
                                                {transfer.status === 'scheduled' && !abilities.can_record_departure && (
                                                    <p className="text-[11px] text-ink-500">
                                                        Authorized referring-hospital staff record departure when the patient leaves.
                                                    </p>
                                                )}
                                                {transfer.status === 'in_transit' && !abilities.can_confirm_arrival && (
                                                    <p className="text-[11px] text-ink-500">
                                                        The receiving hospital confirms arrival. An active bed hold becomes occupied.
                                                    </p>
                                                )}
                                            </div>
                                        </>
                                    ) : (
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <p className="muted max-w-xl">
                                                The referral is accepted. Schedule the transfer, assign transport, and keep both hospitals aligned on the expected arrival.
                                            </p>
                                            <Button size="sm" onClick={() => openModal('transfer')} disabled={actionForm.processing}>
                                                <Truck className="size-4" />
                                                Schedule transfer
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </Card>
                        )}

                        {transfer && referral.status === 'in_transit' && (
                            <InfoCallout tone="amber" title="Patient is in transit">
                                The receiving hospital should confirm arrival as soon as the patient is received. Do not cancel a referral that is already in transit.
                            </InfoCallout>
                        )}

                        <Card className="overflow-hidden">
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ink-100 px-5 py-4 sm:px-6">
                                <div className="flex items-start gap-3">
                                    <span className="icon-tile bg-ink-100 text-ink-600">
                                        <Paperclip className="size-5" />
                                    </span>
                                    <div>
                                        <h2 className="heading-3">Supporting documents</h2>
                                        <p className="subtle mt-0.5">Private to the referring and receiving hospitals.</p>
                                    </div>
                                </div>
                                <Badge tone="slate">{referral.documents?.length ?? 0} file(s)</Badge>
                            </div>

                            <div className="p-5 sm:p-6">
                                {referral.documents?.length ? (
                                    <ul className="divide-y divide-ink-100">
                                        {referral.documents.map((document) => (
                                            <li key={document.id} className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-ink-100 text-ink-500">
                                                        <FileText className="size-4" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-xs font-semibold text-ink-900">{document.original_name}</p>
                                                        <p className="mt-0.5 text-[10px] text-ink-500">
                                                            {formatBytes(document.size_bytes)} · {document.uploaded_by || 'Unknown'} · {formatDateTime(document.created_at)}
                                                        </p>
                                                    </div>
                                                </div>
                                                <a
                                                    href={`/referrals/${referral.id}/documents/${document.id}`}
                                                    className="btn-icon shrink-0"
                                                    aria-label={`Download ${document.original_name}`}
                                                    title="Download document"
                                                >
                                                    <Download className="size-4" />
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="text-xs text-ink-500">No supporting documents have been attached.</p>
                                )}

                                <form onSubmit={uploadDocument} className="mt-5 border-t border-ink-100 pt-4">
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <input
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                            onChange={(event) => documentForm.setData('document', event.target.files?.[0] ?? null)}
                                            className="input file:mr-3 file:rounded-lg file:border-0 file:bg-ink-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink-700"
                                            aria-label="Attach a supporting document"
                                        />
                                        <Button type="submit" variant="secondary" size="sm" disabled={documentForm.processing || !documentForm.data.document}>
                                            <Paperclip className="size-4" />
                                            Attach file
                                        </Button>
                                    </div>
                                    <p className="field-hint">PDF, JPG, PNG, DOC, or DOCX · maximum 10 MB.</p>
                                    {documentForm.errors.document && <p className="field-error">{documentForm.errors.document}</p>}
                                </form>
                            </div>
                        </Card>

                        {abilities.can_cancel && (
                            <Card className="p-4">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p className="text-xs font-semibold text-ink-800">Close this referral</p>
                                        <p className="mt-0.5 text-[11px] text-ink-500">
                                            Cancellation releases an active bed hold and stops further coordination. This action is recorded in the audit trail.
                                        </p>
                                    </div>
                                    <Button variant="danger" size="sm" onClick={() => openModal('cancel')} disabled={actionForm.processing}>
                                        <XCircle className="size-4" />
                                        Cancel referral
                                    </Button>
                                </div>
                            </Card>
                        )}
                    </div>

                    <aside className="space-y-4 xl:sticky xl:top-24 xl:self-start">
                        {receivingAvailability && (
                            <Card className="overflow-hidden">
                                <div className="bg-ink-950 p-5 text-white">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="text-[10px] font-semibold tracking-[0.14em] text-brand-300 uppercase">Receiving availability</p>
                                            <h2 className="mt-1 truncate font-display text-lg font-bold">
                                                {referral.receiving_hospital?.short_name || referral.receiving_hospital?.name}
                                            </h2>
                                            <p className="mt-1 flex items-center gap-1 text-[11px] text-ink-300">
                                                <MapPin className="size-3 shrink-0" />
                                                <span className="truncate">{receivingAvailability.municipality || referral.receiving_hospital?.location}</span>
                                            </p>
                                        </div>
                                        <FreshnessBadge freshness={receivingAvailability.freshness} label={receivingAvailability.freshness_label} />
                                    </div>

                                    <div className="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-white/[0.06] p-3 ring-1 ring-white/10">
                                        <div>
                                            <p className="text-[9px] font-semibold tracking-wide text-ink-400 uppercase">Available</p>
                                            <p className="mt-0.5 font-display text-xl font-extrabold text-brand-300">
                                                {formatNumber(receivingAvailability.counts?.available)}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-[9px] font-semibold tracking-wide text-ink-400 uppercase">ER</p>
                                            <p className="mt-0.5 font-display text-xl font-extrabold text-white">{formatNumber(erAvailable ?? 0)}</p>
                                        </div>
                                        <div>
                                            <p className="text-[9px] font-semibold tracking-wide text-ink-400 uppercase">Total</p>
                                            <p className="mt-0.5 font-display text-xl font-extrabold text-white">{formatNumber(receivingAvailability.counts?.total)}</p>
                                        </div>
                                    </div>
                                    <p className="mt-2 text-[10px] text-ink-400">
                                        Reported {relativeTime(receivingAvailability.last_updated_at)} · availability is not acceptance
                                    </p>
                                </div>

                                <div className="space-y-2.5 p-4">
                                    {receivingAvailability.categories?.map((category) => (
                                        <div key={category.id} className="flex items-center justify-between gap-2">
                                            <span className="flex min-w-0 items-center gap-1.5">
                                                <span className="truncate text-xs text-ink-600">{category.name}</span>
                                                {category.is_er && <Badge tone="rose">ER</Badge>}
                                            </span>
                                            <span className="shrink-0 text-[11px] font-bold text-emerald-700">
                                                {category.available} <span className="font-normal text-ink-400">/ {category.total}</span>
                                            </span>
                                        </div>
                                    ))}

                                    {receivingAvailability.freshness === 'stale' && (
                                        <InfoCallout tone="amber" icon={MessageSquareWarning}>
                                            This availability report is stale. Confirm capacity directly before transferring.
                                        </InfoCallout>
                                    )}

                                    <Link href={`/hospitals/${referral.receiving_hospital?.id}`} className="btn btn-secondary btn-sm mt-1 w-full">
                                        View hospital profile
                                    </Link>
                                </div>
                            </Card>
                        )}

                        <Card className="p-5">
                            <div className="flex items-start gap-3">
                                <Avatar name={referral.coordinator?.name || referral.contact_name} size="md" />
                                <div className="min-w-0">
                                    <p className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Coordination contact</p>
                                    <p className="mt-0.5 truncate text-sm font-bold text-ink-900">
                                        {referral.coordinator?.name || referral.contact_name || 'Unassigned'}
                                    </p>
                                    <p className="truncate text-[11px] text-ink-500">
                                        {referral.coordinator?.email || referral.contact_phone || 'No contact recorded'}
                                    </p>
                                </div>
                            </div>
                            <dl className="mt-4 grid grid-cols-2 gap-3">
                                <KeyValue label="Created">{formatDateTime(referral.created_at)}</KeyValue>
                                <KeyValue label="Submitted">{referral.submitted_at ? formatDateTime(referral.submitted_at) : '—'}</KeyValue>
                                <KeyValue label="Accepted">{referral.accepted_at ? formatDateTime(referral.accepted_at) : '—'}</KeyValue>
                                <KeyValue label="Expires">{referral.expires_at ? formatDateTime(referral.expires_at) : '—'}</KeyValue>
                            </dl>
                        </Card>

                        <Card className="p-5">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="heading-3">Status history</h2>
                                <Badge tone="slate">{referral.status_history?.length ?? 0}</Badge>
                            </div>

                            {referral.status_history?.length ? (
                                <ol className="timeline mt-4">
                                    {referral.status_history.map((history) => (
                                        <li key={history.id} className="relative">
                                            <span className="timeline-dot" />
                                            <ReferralStatusBadge status={history.new_status} label={humanize(history.new_status)} dot={false} />
                                            {history.reason && <p className="mt-1.5 text-xs leading-5 text-ink-700">{history.reason}</p>}
                                            <p className="mt-1 text-[10px] text-ink-400">
                                                {history.changed_by} · {formatDateTime(history.changed_at)}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            ) : (
                                <EmptyState icon={Clock3} title="No status history" description="Status changes will appear here." className="px-0 py-6" />
                            )}
                        </Card>

                        <InfoCallout tone="slate" title="Privacy reminder">
                            IPHN stores coordination data only. Do not send full clinical narratives, imaging, or laboratory results through referral documents.
                        </InfoCallout>
                    </aside>
                </div>
            </div>

            <Modal
                open={!!modal}
                onClose={closeModal}
                title={meta?.title}
                description={meta?.description}
                icon={meta?.icon}
                size={modal === 'reserve' ? 'lg' : 'md'}
                footer={
                    <>
                        <Button variant="secondary" onClick={closeModal} disabled={actionForm.processing}>
                            Cancel
                        </Button>
                        <Button variant={meta?.variant ?? 'primary'} onClick={submitModal} disabled={actionForm.processing}>
                            {actionForm.processing ? (
                                <>
                                    <span className="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                                    Saving…
                                </>
                            ) : (
                                <>
                                    {MetaIcon && <MetaIcon className="size-4" />}
                                    {meta?.confirm}
                                </>
                            )}
                        </Button>
                    </>
                }
            >
                {renderModalContent()}
            </Modal>
        </AppLayout>
    );
}
