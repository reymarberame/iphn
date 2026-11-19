import React, { useMemo } from 'react';
import { useForm } from '@inertiajs/react';
import { Building2, Pencil } from 'lucide-react';
import { Button, Field, Modal, Select, Textarea, TextInput } from '../../components/ui';
import { classNames, formatDateTime } from '../../lib/utils';

const FACILITY_OPTIONS = [
    { value: 'primary', label: 'Primary care' },
    { value: 'secondary', label: 'Secondary' },
    { value: 'tertiary', label: 'Tertiary' },
    { value: 'specialty', label: 'Specialty' },
];

const OWNERSHIP_OPTIONS = [
    { value: 'government', label: 'Government' },
    { value: 'private', label: 'Private' },
];

const STATUS_OPTIONS = [
    { value: 'operational', label: 'Operational' },
    { value: 'limited', label: 'Limited services' },
    { value: 'inactive', label: 'Inactive' },
];

export default function EditProfileModal({ hospital, municipalities = [], services = [], isNetworkUser = false, onClose }) {
    const form = useForm({
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
        services: (hospital.service_ids ?? []).map(Number),
    });

    const municipalityGroups = useMemo(() => {
        const groups = new Map();

        municipalities.forEach((municipality) => {
            const province = municipality.province?.name ?? 'Other';
            if (!groups.has(province)) groups.set(province, []);
            groups.get(province).push(municipality);
        });

        return Array.from(groups.entries()).sort(([a], [b]) => a.localeCompare(b));
    }, [municipalities]);

    const submit = (event) => {
        event.preventDefault();

        form.patch(`/hospitals/${hospital.id}/profile`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
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

    return (
        <Modal
            open
            onClose={onClose}
            title={`Edit ${hospital.short_name || hospital.name}`}
            description="Update facility details and the services offered to the network."
            icon={Building2}
            size="xl"
            footer={
                <>
                    <Button variant="secondary" onClick={onClose} disabled={form.processing}>
                        Cancel
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        {form.processing ? (
                            <>
                                <span className="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                                Saving…
                            </>
                        ) : (
                            <>
                                <Pencil className="size-4" />
                                Save changes
                            </>
                        )}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-5">
                {!isNetworkUser && (
                    <p className="rounded-xl border border-ink-100 bg-ink-50/60 px-3 py-2 text-[11px] leading-5 text-ink-500">
                        Hospital code, ownership, operational status, and municipality are managed by network administrators. Contact them if any of those need to change.
                    </p>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Hospital name" htmlFor="profile-hospital-name" error={form.errors.name} required className="sm:col-span-2">
                        <TextInput
                            id="profile-hospital-name"
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                            error={form.errors.name}
                            placeholder="e.g. Bukidnon Provincial Medical Center"
                        />
                    </Field>

                    <Field label="Short name" htmlFor="profile-hospital-short-name" error={form.errors.short_name} hint="Used in dense tables and referral routes.">
                        <TextInput
                            id="profile-hospital-short-name"
                            value={form.data.short_name}
                            onChange={(event) => form.setData('short_name', event.target.value)}
                            error={form.errors.short_name}
                            placeholder="e.g. BPMC"
                        />
                    </Field>

                    <Field label="Hospital code" htmlFor="profile-hospital-code" error={form.errors.hospital_code} hint={isNetworkUser ? undefined : 'Managed by network administrators.'}>
                        <TextInput
                            id="profile-hospital-code"
                            value={form.data.hospital_code}
                            onChange={(event) => form.setData('hospital_code', event.target.value.toUpperCase())}
                            error={form.errors.hospital_code}
                            placeholder="e.g. BPMC-1013"
                            disabled={!isNetworkUser}
                        />
                    </Field>

                    <Field label="Municipality" htmlFor="profile-hospital-municipality" error={form.errors.municipality_id} hint={isNetworkUser ? undefined : 'Managed by network administrators.'}>
                        <Select
                            id="profile-hospital-municipality"
                            value={form.data.municipality_id}
                            onChange={(event) => form.setData('municipality_id', event.target.value)}
                            error={form.errors.municipality_id}
                            disabled={!isNetworkUser}
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

                    <Field label="Facility type" htmlFor="profile-hospital-facility-type" error={form.errors.facility_type} required>
                        <Select
                            id="profile-hospital-facility-type"
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

                    <Field label="Ownership" htmlFor="profile-hospital-ownership" error={form.errors.ownership_type} hint={isNetworkUser ? undefined : 'Managed by network administrators.'}>
                        <Select
                            id="profile-hospital-ownership"
                            value={form.data.ownership_type}
                            onChange={(event) => form.setData('ownership_type', event.target.value)}
                            error={form.errors.ownership_type}
                            disabled={!isNetworkUser}
                        >
                            {OWNERSHIP_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Operational status" htmlFor="profile-hospital-status" error={form.errors.status} hint={isNetworkUser ? undefined : 'Managed by network administrators.'}>
                        <Select
                            id="profile-hospital-status"
                            value={form.data.status}
                            onChange={(event) => form.setData('status', event.target.value)}
                            error={form.errors.status}
                            disabled={!isNetworkUser}
                        >
                            {STATUS_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Licensed bed capacity" htmlFor="profile-hospital-bed-capacity" error={form.errors.bed_capacity} hint="Reference capacity. Live availability comes from the bed board.">
                        <TextInput
                            id="profile-hospital-bed-capacity"
                            type="number"
                            min="0"
                            value={form.data.bed_capacity}
                            onChange={(event) => form.setData('bed_capacity', event.target.value)}
                            error={form.errors.bed_capacity}
                            placeholder="e.g. 300"
                        />
                    </Field>

                    <Field label="Phone" htmlFor="profile-hospital-phone" error={form.errors.phone}>
                        <TextInput
                            id="profile-hospital-phone"
                            value={form.data.phone}
                            onChange={(event) => form.setData('phone', event.target.value)}
                            error={form.errors.phone}
                            placeholder="+63 82 000 0000"
                        />
                    </Field>

                    <Field label="Email" htmlFor="profile-hospital-email" error={form.errors.email}>
                        <TextInput
                            id="profile-hospital-email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                            error={form.errors.email}
                            placeholder="referrals@hospital.ph"
                        />
                    </Field>

                    <Field label="Address" htmlFor="profile-hospital-address" error={form.errors.address} className="sm:col-span-2">
                        <TextInput
                            id="profile-hospital-address"
                            value={form.data.address}
                            onChange={(event) => form.setData('address', event.target.value)}
                            error={form.errors.address}
                            placeholder="Street, barangay, municipality"
                        />
                    </Field>
                </div>

                <Field label="Services offered" htmlFor="profile-hospital-services" error={form.errors.services} hint="Select every service the hospital can receive referrals for.">
                    <div id="profile-hospital-services" className="grid max-h-56 gap-1.5 overflow-y-auto rounded-xl border border-ink-100 bg-ink-50/40 p-3 sm:grid-cols-2">
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

                <Field label="Notes" htmlFor="profile-hospital-notes" error={form.errors.notes} hint="Operational context for network coordinators. Do not include patient information.">
                    <Textarea
                        id="profile-hospital-notes"
                        rows={3}
                        value={form.data.notes}
                        onChange={(event) => form.setData('notes', event.target.value)}
                        error={form.errors.notes}
                        placeholder="Service limitations, escalation contacts, or referral preferences"
                    />
                </Field>

                {hospital.profile_updated_at && (
                    <p className="text-[10px] text-ink-400">Last profile update: {formatDateTime(hospital.profile_updated_at)}</p>
                )}
            </form>
        </Modal>
    );
}
