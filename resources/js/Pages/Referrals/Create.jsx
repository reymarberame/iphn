import React, { useMemo, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BedDouble,
    Building2,
    FileText,
    MapPin,
    Paperclip,
    Search,
    Send,
    ShieldCheck,
    Stethoscope,
    Trash2,
    TriangleAlert,
    UserRound,
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
    PageHeader,
    Select,
    Textarea,
    TextInput,
} from '../../components/ui';
import {
    classNames,
    formatBytes,
    formatNumber,
    humanize,
    relativeTime,
    toneForOwnership,
} from '../../lib/utils';

export default function ReferralCreate({ referringHospitals = [], receivingHospitals = [], preselectedReceivingId = null, urgencyOptions = [] }) {
    const { reference, auth } = usePage().props;
    const [hospitalSearch, setHospitalSearch] = useState('');
    const [fileInputKey, setFileInputKey] = useState(0);

    const initialReferring = referringHospitals[0]?.id ?? '';
    const initialReceiving = receivingHospitals.some((hospital) => hospital.id === preselectedReceivingId)
        ? preselectedReceivingId
        : '';

    const form = useForm({
        referring_hospital_id: initialReferring,
        receiving_hospital_id: initialReceiving,
        patient_reference: '',
        patient_name: '',
        patient_age: '',
        patient_sex: '',
        urgency: 'routine',
        requested_service_id: '',
        referral_reason: '',
        contact_name: auth?.user?.name ?? '',
        contact_phone: auth?.user?.phone ?? '',
        documents: [],
    });

    const selectedHospital = receivingHospitals.find((hospital) => hospital.id === form.data.receiving_hospital_id);
    const referringHospital = referringHospitals.find((hospital) => hospital.id === form.data.referring_hospital_id);

    const filteredHospitals = useMemo(() => {
        const search = hospitalSearch.trim().toLowerCase();
        if (!search) return receivingHospitals;

        return receivingHospitals.filter((hospital) =>
            [hospital.name, hospital.short_name, hospital.hospital_code, hospital.location, ...(hospital.services ?? [])]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(search)),
        );
    }, [hospitalSearch, receivingHospitals]);

    const availableServices = useMemo(() => {
        const all = reference?.services ?? [];
        if (!selectedHospital) return all;
        const offered = new Set(selectedHospital.service_ids ?? []);
        return all.filter((service) => offered.has(service.id));
    }, [reference?.services, selectedHospital]);

    const chooseHospital = (id) => {
        form.setData('receiving_hospital_id', id);

        const hospital = receivingHospitals.find((item) => item.id === id);
        if (form.data.requested_service_id && hospital && !hospital.service_ids?.includes(Number(form.data.requested_service_id))) {
            form.setData('requested_service_id', '');
        }
    };

    const onFiles = (event) => {
        const files = Array.from(event.target.files ?? []).slice(0, 5);
        form.setData('documents', files);
    };

    const removeFile = (index) => {
        form.setData(
            'documents',
            form.data.documents.filter((_, fileIndex) => fileIndex !== index),
        );
        setFileInputKey((key) => key + 1);
    };

    const submit = (shouldSubmit) => {
        form.transform((data) => ({ ...data, submit: shouldSubmit }));
        form.post('/referrals', {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const erAvailable = selectedHospital?.categories?.filter((category) => category.is_er).reduce((sum, category) => sum + category.available, 0) ?? 0;

    return (
        <AppLayout>
            <Head title="New referral" />

            <div className="space-y-6">
                <div>
                    <Link href="/referrals" className="mb-4 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 hover:text-brand-700">
                        <ArrowLeft className="size-3.5" />
                        Back to referrals
                    </Link>
                    <PageHeader
                        eyebrow="Referral coordination"
                        title="Create a referral"
                        description="Share only the minimum information needed to coordinate the transfer. Clinical records remain at the referring hospital."
                        actions={<Badge tone="slate">Draft → submit → receiving response</Badge>}
                    />
                </div>

                <form onSubmit={(event) => event.preventDefault()} className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="space-y-5">
                        <Card className="p-5 sm:p-6">
                            <div className="flex items-start gap-3">
                                <span className="icon-tile bg-brand-50 text-brand-700">
                                    <Building2 className="size-5" />
                                </span>
                                <div>
                                    <h2 className="heading-3">Routing</h2>
                                    <p className="subtle mt-1">Choose the referring hospital and the facility receiving the patient.</p>
                                </div>
                            </div>

                            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                                <Field label="Referring hospital" htmlFor="referring-hospital" error={form.errors.referring_hospital_id} required>
                                    <Select
                                        id="referring-hospital"
                                        value={form.data.referring_hospital_id}
                                        onChange={(event) => form.setData('referring_hospital_id', Number(event.target.value))}
                                        error={form.errors.referring_hospital_id}
                                    >
                                        {referringHospitals.map((hospital) => (
                                            <option key={hospital.id} value={hospital.id}>
                                                {hospital.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>

                                <div className="rounded-xl bg-ink-50 p-3">
                                    <p className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Referral route</p>
                                    <p className="mt-1 truncate text-xs font-medium text-ink-700">
                                        {referringHospital?.short_name || referringHospital?.name || 'Select referring hospital'}
                                        <span className="px-1.5 text-ink-400">→</span>
                                        {selectedHospital?.short_name || selectedHospital?.name || 'Select receiving hospital'}
                                    </p>
                                    {selectedHospital && <p className="mt-1 text-[10px] text-ink-400">{selectedHospital.location}</p>}
                                </div>
                            </div>

                            <div className="mt-5">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <label className="field-label mb-0" htmlFor="receiving-search">
                                        Receiving hospital <span className="text-rose-500">*</span>
                                    </label>
                                    <div className="relative sm:w-64">
                                        <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-ink-400" />
                                        <input
                                            id="receiving-search"
                                            type="search"
                                            value={hospitalSearch}
                                            onChange={(event) => setHospitalSearch(event.target.value)}
                                            className="input py-2 pl-8 text-xs"
                                            placeholder="Search name, code, or service"
                                        />
                                    </div>
                                </div>

                                {form.errors.receiving_hospital_id && <p className="field-error">{form.errors.receiving_hospital_id}</p>}

                                {filteredHospitals.length ? (
                                    <div className="mt-2 grid max-h-80 gap-2 overflow-y-auto rounded-xl border border-ink-100 bg-ink-50/40 p-2 sm:grid-cols-2">
                                        {filteredHospitals.map((hospital) => {
                                            const selected = hospital.id === form.data.receiving_hospital_id;
                                            const hospitalEr = hospital.categories
                                                ?.filter((category) => category.is_er)
                                                .reduce((sum, category) => sum + category.available, 0);

                                            return (
                                                <button
                                                    key={hospital.id}
                                                    type="button"
                                                    onClick={() => chooseHospital(hospital.id)}
                                                    className={classNames(
                                                        'rounded-xl border bg-white p-3 text-left transition',
                                                        selected
                                                            ? 'border-brand-500 ring-2 ring-brand-500/20'
                                                            : 'border-ink-100 hover:border-brand-200 hover:bg-brand-50/20',
                                                    )}
                                                    aria-pressed={selected}
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0">
                                                            <p className="truncate text-xs font-bold text-ink-900">{hospital.short_name || hospital.name}</p>
                                                            <p className="mt-0.5 truncate text-[10px] text-ink-500">{hospital.location}</p>
                                                        </div>
                                                        <FreshnessBadge freshness={hospital.freshness} label={hospital.freshness_label} />
                                                    </div>
                                                    <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                                        <Badge tone={toneForOwnership(hospital.ownership_type)}>{humanize(hospital.ownership_type)}</Badge>
                                                        <span className="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                            <BedDouble className="size-3" />
                                                            {formatNumber(hospital.counts.available)} available
                                                        </span>
                                                        <span className="text-[10px] text-ink-400">ER {formatNumber(hospitalEr ?? 0)}</span>
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="mt-2 rounded-xl border border-ink-100 bg-ink-50 p-4 text-center text-xs text-ink-500">
                                        No receiving hospitals match “{hospitalSearch}”.
                                    </div>
                                )}
                            </div>
                        </Card>

                        <Card className="p-5 sm:p-6">
                            <div className="flex items-start gap-3">
                                <span className="icon-tile bg-brand-50 text-brand-700">
                                    <UserRound className="size-5" />
                                </span>
                                <div>
                                    <h2 className="heading-3">Patient identification</h2>
                                    <p className="subtle mt-1">Use your hospital's reference. BHN does not store a full clinical record.</p>
                                </div>
                            </div>

                            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Patient reference"
                                    htmlFor="patient-reference"
                                    error={form.errors.patient_reference}
                                    hint="Your hospital's MRN or case number."
                                    required
                                >
                                    <TextInput
                                        id="patient-reference"
                                        value={form.data.patient_reference}
                                        onChange={(event) => form.setData('patient_reference', event.target.value)}
                                        error={form.errors.patient_reference}
                                        placeholder="e.g. BPMC-2026-01842"
                                    />
                                </Field>

                                <Field label="Patient name" htmlFor="patient-name" error={form.errors.patient_name} required>
                                    <TextInput
                                        id="patient-name"
                                        value={form.data.patient_name}
                                        onChange={(event) => form.setData('patient_name', event.target.value)}
                                        error={form.errors.patient_name}
                                        placeholder="Full name"
                                    />
                                </Field>

                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="Age" htmlFor="patient-age" error={form.errors.patient_age}>
                                        <TextInput
                                            id="patient-age"
                                            type="number"
                                            min="0"
                                            max="120"
                                            value={form.data.patient_age}
                                            onChange={(event) => form.setData('patient_age', event.target.value)}
                                            error={form.errors.patient_age}
                                            placeholder="Years"
                                        />
                                    </Field>
                                    <Field label="Sex" htmlFor="patient-sex" error={form.errors.patient_sex}>
                                        <Select
                                            id="patient-sex"
                                            value={form.data.patient_sex}
                                            onChange={(event) => form.setData('patient_sex', event.target.value)}
                                            error={form.errors.patient_sex}
                                        >
                                            <option value="">Not specified</option>
                                            <option value="female">Female</option>
                                            <option value="male">Male</option>
                                            <option value="other">Other</option>
                                            <option value="undisclosed">Undisclosed</option>
                                        </Select>
                                    </Field>
                                </div>

                                <Field label="Urgency" htmlFor="urgency" error={form.errors.urgency} required>
                                    <Select id="urgency" value={form.data.urgency} onChange={(event) => form.setData('urgency', event.target.value)}>
                                        {urgencyOptions.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            </div>
                        </Card>

                        <Card className="p-5 sm:p-6">
                            <div className="flex items-start gap-3">
                                <span className="icon-tile bg-brand-50 text-brand-700">
                                    <Stethoscope className="size-5" />
                                </span>
                                <div>
                                    <h2 className="heading-3">Coordination details</h2>
                                    <p className="subtle mt-1">Give the receiving team enough context to respond safely.</p>
                                </div>
                            </div>

                            <div className="mt-5 space-y-4">
                                <Field
                                    label="Requested service"
                                    htmlFor="requested-service"
                                    error={form.errors.requested_service_id}
                                    hint={selectedHospital ? 'Only services listed by the selected hospital are shown.' : 'Select a receiving hospital first.'}
                                >
                                    <Select
                                        id="requested-service"
                                        value={form.data.requested_service_id}
                                        onChange={(event) => form.setData('requested_service_id', event.target.value)}
                                        error={form.errors.requested_service_id}
                                        disabled={!selectedHospital}
                                    >
                                        <option value="">No specific service</option>
                                        {availableServices.map((service) => (
                                            <option key={service.id} value={service.id}>
                                                {service.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>

                                <Field
                                    label="Reason for referral"
                                    htmlFor="referral-reason"
                                    error={form.errors.referral_reason}
                                    hint="Minimum 10 characters. Include the coordination context the receiving hospital needs."
                                    required
                                >
                                    <Textarea
                                        id="referral-reason"
                                        rows={5}
                                        value={form.data.referral_reason}
                                        onChange={(event) => form.setData('referral_reason', event.target.value)}
                                        error={form.errors.referral_reason}
                                        placeholder="Summarize why the patient needs transfer and what the receiving facility should prepare for."
                                    />
                                </Field>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Contact person" htmlFor="contact-name" error={form.errors.contact_name}>
                                        <TextInput
                                            id="contact-name"
                                            value={form.data.contact_name}
                                            onChange={(event) => form.setData('contact_name', event.target.value)}
                                            error={form.errors.contact_name}
                                            placeholder="Coordinating clinician or staff"
                                        />
                                    </Field>
                                    <Field label="Contact phone" htmlFor="contact-phone" error={form.errors.contact_phone}>
                                        <TextInput
                                            id="contact-phone"
                                            value={form.data.contact_phone}
                                            onChange={(event) => form.setData('contact_phone', event.target.value)}
                                            error={form.errors.contact_phone}
                                            placeholder="Reachable number"
                                        />
                                    </Field>
                                </div>
                            </div>
                        </Card>

                        <Card className="p-5 sm:p-6">
                            <div className="flex items-start gap-3">
                                <span className="icon-tile bg-brand-50 text-brand-700">
                                    <Paperclip className="size-5" />
                                </span>
                                <div>
                                    <h2 className="heading-3">Supporting documents</h2>
                                    <p className="subtle mt-1">Optional. PDF, JPG, PNG, DOC, or DOCX · maximum 5 files, 10 MB each.</p>
                                </div>
                            </div>

                            <div className="mt-5">
                                <label
                                    htmlFor="referral-documents"
                                    className="flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-ink-300 bg-ink-50/60 px-4 py-8 text-center transition hover:border-brand-400 hover:bg-brand-50/40"
                                >
                                    <FileText className="size-7 text-ink-400" />
                                    <span className="mt-2 text-xs font-semibold text-ink-700">Choose documents</span>
                                    <span className="mt-0.5 text-[10px] text-ink-400">Files are stored privately and only visible to the two hospitals.</span>
                                    <input
                                        key={fileInputKey}
                                        id="referral-documents"
                                        type="file"
                                        multiple
                                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                        onChange={onFiles}
                                        className="sr-only"
                                    />
                                </label>

                                {form.errors.documents && <p className="field-error">{form.errors.documents}</p>}

                                {form.data.documents?.length > 0 && (
                                    <ul className="mt-3 space-y-2">
                                        {form.data.documents.map((file, index) => (
                                            <li key={`${file.name}-${index}`} className="flex items-center justify-between gap-3 rounded-lg border border-ink-100 px-3 py-2">
                                                <span className="flex min-w-0 items-center gap-2">
                                                    <Paperclip className="size-3.5 shrink-0 text-ink-400" />
                                                    <span className="truncate text-xs text-ink-700">{file.name}</span>
                                                    <span className="shrink-0 text-[10px] text-ink-400">{formatBytes(file.size)}</span>
                                                </span>
                                                <button type="button" onClick={() => removeFile(index)} className="btn-icon size-7 text-rose-500" aria-label={`Remove ${file.name}`}>
                                                    <Trash2 className="size-3.5" />
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </Card>

                        <div className="flex flex-col gap-3 rounded-2xl border border-ink-100 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                            <InfoCallout tone="slate" className="flex-1 border-0 bg-transparent p-0">
                                Saving as a draft does not notify the receiving hospital. Submission does.
                            </InfoCallout>
                            <div className="flex shrink-0 items-center gap-2">
                                <Button variant="secondary" onClick={() => submit(false)} disabled={form.processing}>
                                    Save draft
                                </Button>
                                <Button onClick={() => submit(true)} disabled={form.processing}>
                                    {form.processing ? (
                                        <>
                                            <span className="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                                            Submitting…
                                        </>
                                    ) : (
                                        <>
                                            <Send className="size-4" />
                                            Submit referral
                                        </>
                                    )}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <aside className="space-y-4 xl:sticky xl:top-24 xl:self-start">
                        {selectedHospital ? (
                            <Card className="overflow-hidden">
                                <div className="bg-ink-950 p-5 text-white">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="text-[10px] font-semibold tracking-[0.14em] text-brand-300 uppercase">Receiving facility</p>
                                            <h2 className="mt-1 font-display text-lg font-bold">{selectedHospital.short_name || selectedHospital.name}</h2>
                                            <p className="mt-1 flex items-center gap-1 text-[11px] text-ink-300">
                                                <MapPin className="size-3 shrink-0" />
                                                <span className="truncate">{selectedHospital.location}</span>
                                            </p>
                                        </div>
                                        <FreshnessBadge freshness={selectedHospital.freshness} label={selectedHospital.freshness_label} />
                                    </div>
                                    <div className="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-white/[0.06] p-3 ring-1 ring-white/10">
                                        <div>
                                            <p className="text-[9px] font-semibold tracking-wide text-ink-400 uppercase">Available</p>
                                            <p className="mt-0.5 font-display text-xl font-extrabold text-brand-300">{formatNumber(selectedHospital.counts.available)}</p>
                                        </div>
                                        <div>
                                            <p className="text-[9px] font-semibold tracking-wide text-ink-400 uppercase">ER</p>
                                            <p className="mt-0.5 font-display text-xl font-extrabold text-white">{formatNumber(erAvailable)}</p>
                                        </div>
                                        <div>
                                            <p className="text-[9px] font-semibold tracking-wide text-ink-400 uppercase">Total</p>
                                            <p className="mt-0.5 font-display text-xl font-extrabold text-white">{formatNumber(selectedHospital.counts.total)}</p>
                                        </div>
                                    </div>
                                    <p className="mt-2 text-[10px] text-ink-400">
                                        Reported {relativeTime(selectedHospital.last_updated_at)} · availability is not acceptance
                                    </p>
                                </div>

                                <div className="space-y-3 p-4">
                                    {selectedHospital.categories?.slice(0, 6).map((category) => (
                                        <div key={category.id} className="flex items-center justify-between gap-2">
                                            <span className="truncate text-xs text-ink-600">{category.name}</span>
                                            <span className="shrink-0 text-[11px] font-bold text-emerald-700">
                                                {category.available} <span className="font-normal text-ink-400">/ {category.total}</span>
                                            </span>
                                        </div>
                                    ))}
                                    {selectedHospital.categories?.length === 0 && <p className="text-xs text-ink-500">No categories reported.</p>}

                                    {selectedHospital.freshness === 'stale' && (
                                        <InfoCallout tone="amber" icon={TriangleAlert}>
                                            This report is stale. Confirm capacity with the hospital before relying on it.
                                        </InfoCallout>
                                    )}

                                    <a
                                        href={`/hospitals/${selectedHospital.id}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="btn btn-secondary btn-sm w-full"
                                    >
                                        View hospital profile
                                    </a>
                                </div>
                            </Card>
                        ) : (
                            <Card>
                                <EmptyState
                                    icon={Building2}
                                    title="Choose a receiving hospital"
                                    description="Select a facility to preview its reported availability and offered services."
                                />
                            </Card>
                        )}

                        <InfoCallout tone="brand" title="What happens next" icon={ShieldCheck}>
                            The receiving hospital can accept, decline, or request more information. Only after acceptance can its authorized staff reserve a bed for this
                            referral.
                        </InfoCallout>

                        <InfoCallout tone="slate" title="Minimum necessary data">
                            Do not include full clinical narratives, imaging, or laboratory results here. Attach only coordination documents. Clinical records remain in
                            your hospital's own system.
                        </InfoCallout>
                    </aside>
                </form>
            </div>
        </AppLayout>
    );
}
