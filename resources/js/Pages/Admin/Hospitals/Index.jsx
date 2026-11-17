import React, { useMemo, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    Building2,
    FilterX,
    MapPin,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    Users,
    BedDouble,
    ShieldCheck,
} from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import {
    Badge,
    Button,
    Card,
    EmptyState,
    Field,
    Modal,
    PageHeader,
    Select,
    Textarea,
    TextInput,
} from '../../../components/ui';
import {
    classNames,
    formatDateTime,
    formatNumber,
    humanize,
    toneForHospitalStatus,
    toneForOwnership,
} from '../../../lib/utils';

const OWNERSHIP_OPTIONS = [
    { value: 'government', label: 'Government' },
    { value: 'private', label: 'Private' },
];

const STATUS_OPTIONS = [
    { value: 'operational', label: 'Operational' },
    { value: 'limited', label: 'Limited services' },
    { value: 'inactive', label: 'Inactive' },
];

const FACILITY_OPTIONS = [
    { value: 'primary', label: 'Primary care' },
    { value: 'secondary', label: 'Secondary' },
    { value: 'tertiary', label: 'Tertiary' },
    { value: 'specialty', label: 'Specialty' },
];

const EMPTY_FORM = {
    municipality_id: '',
    hospital_code: '',
    name: '',
    short_name: '',
    facility_type: 'secondary',
    ownership_type: 'government',
    address: '',
    phone: '',
    email: '',
    bed_capacity: '',
    status: 'operational',
    notes: '',
    services: [],
};

export default function AdminHospitalIndex({ hospitals = [], municipalities = [], services = [], canCreate = false, isNetworkUser = false }) {
    const [search, setSearch] = useState('');
    const [regionFilter, setRegionFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [modal, setModal] = useState(null);
    const [editing, setEditing] = useState(null);

    const form = useForm({ ...EMPTY_FORM });

    const regions = useMemo(
        () => Array.from(new Set(hospitals.map((hospital) => hospital.region).filter(Boolean))).sort(),
        [hospitals],
    );

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        return hospitals.filter((hospital) => {
            if (regionFilter && hospital.region !== regionFilter) return false;
            if (statusFilter && hospital.status !== statusFilter) return false;
            if (!term) return true;

            return [hospital.name, hospital.short_name, hospital.hospital_code, hospital.location, hospital.municipality, hospital.province]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(term));
        });
    }, [hospitals, search, regionFilter, statusFilter]);

    const municipalityGroups = useMemo(() => {
        const groups = new Map();

        municipalities.forEach((municipality) => {
            const province = municipality.province?.name ?? 'Other';
            if (!groups.has(province)) groups.set(province, []);
            groups.get(province).push(municipality);
        });

        return Array.from(groups.entries()).sort(([a], [b]) => a.localeCompare(b));
    }, [municipalities]);

    const openCreate = () => {
        form.clearErrors();
        form.setData({ ...EMPTY_FORM, municipality_id: hospitals[0]?.municipality_id ?? '' });
        setEditing(null);
        setModal('create');
    };

    const openEdit = (hospital) => {
        form.clearErrors();
        form.setData({
            municipality_id: hospital.municipality_id ?? '',
            hospital_code: hospital.hospital_code ?? '',
            name: hospital.name ?? '',
            short_name: hospital.short_name ?? '',
            facility_type: hospital.facility_type ?? 'secondary',
            ownership_type: hospital.ownership_type ?? 'government',
            address: hospital.address ?? '',
            phone: hospital.phone ?? '',
            email: hospital.email ?? '',
            bed_capacity: hospital.bed_capacity ?? '',
            status: hospital.status ?? 'operational',
            notes: hospital.notes ?? '',
            services: hospital.service_ids ?? [],
        });
        setEditing(hospital);
        setModal('edit');
    };

    const closeModal = () => {
        setModal(null);
        setEditing(null);
        form.clearErrors();
    };

    const submit = (event) => {
        event.preventDefault();

        if (modal === 'edit' && editing) {
            form.patch(`/admin/hospitals/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => closeModal(),
            });
            return;
        }

        form.post('/admin/hospitals', {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    };

    const toggleService = (serviceId) => {
        const selected = new Set(form.data.services.map(Number));

        if (selected.has(serviceId)) {
            selected.delete(serviceId);
        } else {
            selected.add(serviceId);
        }

        form.setData('services', Array.from(selected));
    };

    const resetFilters = () => {
        setSearch('');
        setRegionFilter('');
        setStatusFilter('');
    };

    const hasFilters = search !== '' || regionFilter !== '' || statusFilter !== '';

    return (
        <AppLayout>
            <Head title="Hospital administration" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Administration"
                    title="Hospital profiles"
                    description="Maintain network facility records, locations, service listings, and operational status. Bed capacity is reported separately on the bed board."
                    actions={
                        canCreate ? (
                            <Button onClick={openCreate}>
                                <Plus className="size-4" />
                                Register hospital
                            </Button>
                        ) : (
                            <Badge tone="slate">Profile editing only</Badge>
                        )
                    }
                />

                {!isNetworkUser && (
                    <p className="text-[11px] leading-5 text-ink-400">
                        Hospital-scoped administrators can update their facility profile but cannot change the hospital code, ownership, status, or municipality. Those fields are
                        managed by network administrators.
                    </p>
                )}

                <Card className="p-4 sm:p-5">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <Field label="Search" htmlFor="admin-hospital-search" className="lg:w-80">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
                                <input
                                    id="admin-hospital-search"
                                    type="search"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Name, code, municipality, or province"
                                    className="input pl-10"
                                />
                            </div>
                        </Field>

                        <Field label="Region" htmlFor="admin-hospital-region" className="lg:w-52">
                            <Select id="admin-hospital-region" value={regionFilter} onChange={(event) => setRegionFilter(event.target.value)}>
                                <option value="">All regions</option>
                                {regions.map((region) => (
                                    <option key={region} value={region}>
                                        {region}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field label="Status" htmlFor="admin-hospital-status" className="lg:w-44">
                            <Select id="admin-hospital-status" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
                                <option value="">All statuses</option>
                                {STATUS_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <div className="flex items-center gap-2 lg:ml-auto">
                            <Badge tone="slate">{filtered.length} of {hospitals.length}</Badge>
                            <Button variant="ghost" size="sm" onClick={resetFilters} disabled={!hasFilters}>
                                <FilterX className="size-4" />
                                Reset
                            </Button>
                        </div>
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    {filtered.length ? (
                        <div className="overflow-x-auto">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Hospital</th>
                                        <th>Location</th>
                                        <th>Type / ownership</th>
                                        <th>Status</th>
                                        <th>Beds</th>
                                        <th>Staff</th>
                                        <th>Services</th>
                                        <th aria-label="Actions" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.map((hospital) => (
                                        <tr key={hospital.id}>
                                            <td>
                                                <div className="flex items-start gap-2.5">
                                                    <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">
                                                        <Building2 className="size-4" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="max-w-56 truncate text-xs font-bold text-ink-900">{hospital.name}</p>
                                                        <p className="mt-0.5 flex flex-wrap items-center gap-1.5 text-[10px] text-ink-500">
                                                            <span className="font-mono">{hospital.hospital_code}</span>
                                                            {hospital.short_name && <span>· {hospital.short_name}</span>}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span className="flex items-start gap-1.5 text-xs text-ink-600">
                                                    <MapPin className="mt-0.5 size-3 shrink-0 text-ink-400" />
                                                    <span className="max-w-44">{hospital.location || '—'}</span>
                                                </span>
                                            </td>
                                            <td>
                                                <div className="flex flex-col items-start gap-1">
                                                    <Badge tone="slate">{humanize(hospital.facility_type)}</Badge>
                                                    <Badge tone={toneForOwnership(hospital.ownership_type)}>{hospital.ownership_label}</Badge>
                                                </div>
                                            </td>
                                            <td>
                                                <Badge tone={toneForHospitalStatus(hospital.status)} dot>
                                                    {hospital.status_label}
                                                </Badge>
                                            </td>
                                            <td>
                                                <span className="flex items-center gap-1 text-xs font-semibold text-ink-800">
                                                    <BedDouble className="size-3.5 text-ink-400" />
                                                    {formatNumber(hospital.beds_count)}
                                                </span>
                                                <span className="mt-0.5 block text-[10px] text-ink-400">
                                                    {hospital.bed_capacity ? `Licensed ${formatNumber(hospital.bed_capacity)}` : 'No licensed capacity set'}
                                                </span>
                                            </td>
                                            <td>
                                                <span className="flex items-center gap-1 text-xs text-ink-700">
                                                    <Users className="size-3.5 text-ink-400" />
                                                    {formatNumber(hospital.users_count)}
                                                </span>
                                            </td>
                                            <td>
                                                <Badge tone="slate">{hospital.service_ids?.length ?? 0}</Badge>
                                            </td>
                                            <td>
                                                <div className="flex items-center justify-end gap-1">
                                                    <Link href={`/hospitals/${hospital.id}`} className="btn-icon" title="View profile" aria-label={`View ${hospital.name}`}>
                                                        <ShieldCheck className="size-4" />
                                                    </Link>
                                                    <Button variant="secondary" size="sm" onClick={() => openEdit(hospital)}>
                                                        <Pencil className="size-3.5" />
                                                        Edit
                                                    </Button>
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
                            title={hospitals.length ? 'No hospitals match these filters' : 'No hospitals in scope'}
                            description={
                                hospitals.length
                                    ? 'Try clearing the search, region, or status filter.'
                                    : 'Register the first hospital in your administration scope.'
                            }
                            action={
                                hospitals.length ? (
                                    <Button variant="secondary" onClick={resetFilters}>
                                        Clear filters
                                    </Button>
                                ) : canCreate ? (
                                    <Button onClick={openCreate}>
                                        <Plus className="size-4" />
                                        Register hospital
                                    </Button>
                                ) : null
                            }
                        />
                    )}
                </Card>

                <p className="text-[11px] leading-5 text-ink-400">
                    Profile changes are recorded in the audit trail with before and after values. Hospital codes must be unique across the network.
                </p>
            </div>

            <Modal
                open={modal !== null}
                onClose={closeModal}
                title={modal === 'edit' ? `Edit ${editing?.short_name || editing?.name}` : 'Register a hospital'}
                description={
                    modal === 'edit'
                        ? 'Update facility details and the services offered to the network.'
                        : 'Create a new facility record. Bed inventory is configured separately on the bed board.'
                }
                icon={Building2}
                size="xl"
                footer={
                    <>
                        <Button variant="secondary" onClick={closeModal} disabled={form.processing}>
                            Cancel
                        </Button>
                        <Button onClick={submit} disabled={form.processing}>
                            {form.processing ? (
                                <>
                                    <span className="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                                    Saving…
                                </>
                            ) : modal === 'edit' ? (
                                <>
                                    <Pencil className="size-4" />
                                    Save changes
                                </>
                            ) : (
                                <>
                                    <Plus className="size-4" />
                                    Register hospital
                                </>
                            )}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Hospital name" htmlFor="hospital-name" error={form.errors.name} required className="sm:col-span-2">
                            <TextInput
                                id="hospital-name"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                error={form.errors.name}
                                placeholder="e.g. Davao Regional Medical Center"
                            />
                        </Field>

                        <Field label="Short name" htmlFor="hospital-short-name" error={form.errors.short_name} hint="Used in dense tables and referral routes.">
                            <TextInput
                                id="hospital-short-name"
                                value={form.data.short_name}
                                onChange={(event) => form.setData('short_name', event.target.value)}
                                error={form.errors.short_name}
                                placeholder="e.g. DRMC"
                            />
                        </Field>

                        <Field label="Hospital code" htmlFor="hospital-code" error={form.errors.hospital_code} required>
                            <TextInput
                                id="hospital-code"
                                value={form.data.hospital_code}
                                onChange={(event) => form.setData('hospital_code', event.target.value.toUpperCase())}
                                error={form.errors.hospital_code}
                                placeholder="e.g. DRMC-1101"
                                disabled={!isNetworkUser && modal === 'edit'}
                            />
                        </Field>

                        <Field label="Municipality" htmlFor="hospital-municipality" error={form.errors.municipality_id} required>
                            <Select
                                id="hospital-municipality"
                                value={form.data.municipality_id}
                                onChange={(event) => form.setData('municipality_id', event.target.value)}
                                error={form.errors.municipality_id}
                                disabled={!isNetworkUser && modal === 'edit'}
                            >
                                <option value="">Select municipality</option>
                                {municipalityGroups.map(([province, items]) => (
                                    <optgroup key={province} label={province}>
                                        {items.map((municipality) => (
                                            <option key={municipality.id} value={municipality.id}>
                                                {municipality.name}
                                            </option>
                                        ))}
                                    </optgroup>
                                ))}
                            </Select>
                        </Field>

                        <Field label="Facility type" htmlFor="hospital-facility-type" error={form.errors.facility_type} required>
                            <Select
                                id="hospital-facility-type"
                                value={form.data.facility_type}
                                onChange={(event) => form.setData('facility_type', event.target.value)}
                                error={form.errors.facility_type}
                            >
                                {FACILITY_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field label="Ownership" htmlFor="hospital-ownership" error={form.errors.ownership_type} required>
                            <Select
                                id="hospital-ownership"
                                value={form.data.ownership_type}
                                onChange={(event) => form.setData('ownership_type', event.target.value)}
                                error={form.errors.ownership_type}
                                disabled={!isNetworkUser && modal === 'edit'}
                            >
                                {OWNERSHIP_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field label="Operational status" htmlFor="hospital-status" error={form.errors.status} required>
                            <Select
                                id="hospital-status"
                                value={form.data.status}
                                onChange={(event) => form.setData('status', event.target.value)}
                                error={form.errors.status}
                                disabled={!isNetworkUser && modal === 'edit'}
                            >
                                {STATUS_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field label="Licensed bed capacity" htmlFor="hospital-bed-capacity" error={form.errors.bed_capacity} hint="Reference capacity. Live availability comes from the bed board.">
                            <TextInput
                                id="hospital-bed-capacity"
                                type="number"
                                min="0"
                                value={form.data.bed_capacity}
                                onChange={(event) => form.setData('bed_capacity', event.target.value)}
                                error={form.errors.bed_capacity}
                                placeholder="e.g. 300"
                            />
                        </Field>

                        <Field label="Phone" htmlFor="hospital-phone" error={form.errors.phone}>
                            <TextInput
                                id="hospital-phone"
                                value={form.data.phone}
                                onChange={(event) => form.setData('phone', event.target.value)}
                                error={form.errors.phone}
                                placeholder="+63 82 000 0000"
                            />
                        </Field>

                        <Field label="Email" htmlFor="hospital-email" error={form.errors.email}>
                            <TextInput
                                id="hospital-email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                error={form.errors.email}
                                placeholder="referrals@hospital.ph"
                            />
                        </Field>

                        <Field label="Address" htmlFor="hospital-address" error={form.errors.address} className="sm:col-span-2">
                            <TextInput
                                id="hospital-address"
                                value={form.data.address}
                                onChange={(event) => form.setData('address', event.target.value)}
                                error={form.errors.address}
                                placeholder="Street, barangay, municipality"
                            />
                        </Field>
                    </div>

                    <Field label="Services offered" htmlFor="hospital-services" error={form.errors.services} hint="Select every service the hospital can receive referrals for.">
                        <div id="hospital-services" className="grid max-h-56 gap-1.5 overflow-y-auto rounded-xl border border-ink-100 bg-ink-50/40 p-3 sm:grid-cols-2">
                            {services.map((service) => {
                                const checked = form.data.services.map(Number).includes(service.id);

                                return (
                                    <label
                                        key={service.id}
                                        className={classNames(
                                            'flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-2 text-xs transition',
                                            checked ? 'border-brand-300 bg-white text-brand-800' : 'border-transparent text-ink-600 hover:bg-white',
                                        )}
                                    >
                                        <input type="checkbox" checked={checked} onChange={() => toggleService(service.id)} className="checkbox" />
                                        <span className="truncate">{service.name}</span>
                                    </label>
                                );
                            })}
                        </div>
                    </Field>

                    <Field label="Notes" htmlFor="hospital-notes" error={form.errors.notes} hint="Operational context for network coordinators. Do not include patient information.">
                        <Textarea
                            id="hospital-notes"
                            rows={3}
                            value={form.data.notes}
                            onChange={(event) => form.setData('notes', event.target.value)}
                            error={form.errors.notes}
                            placeholder="Service limitations, escalation contacts, or referral preferences"
                        />
                    </Field>

                    {modal === 'edit' && editing?.profile_updated_at && (
                        <p className="text-[10px] text-ink-400">Last profile update: {formatDateTime(editing.profile_updated_at)}</p>
                    )}
                </form>
            </Modal>
        </AppLayout>
    );
}
