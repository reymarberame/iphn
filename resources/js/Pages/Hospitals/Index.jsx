import React, { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BedDouble,
    Building2,
    FilterX,
    MapPin,
    Search,
    SlidersHorizontal,
    Stethoscope,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import {
    Badge,
    Button,
    CapacityBar,
    Card,
    EmptyState,
    Field,
    FreshnessBadge,
    PageHeader,
    Select,
} from '../../components/ui';
import {
    classNames,
    formatNumber,
    facilityLevel,
    humanize,
    relativeTime,
    toneForHospitalStatus,
    toneForOwnership,
} from '../../lib/utils';

const OWNERSHIP_OPTIONS = [
    { value: 'government', label: 'Government' },
    { value: 'private', label: 'Private' },
];

export default function HospitalIndex({ hospitals = [], filters = {}, referringHospitals = [] }) {
    const { reference } = usePage().props;
    const regions = reference?.regions ?? [];

    const blankValues = {
        search: filters.search ?? '',
        region_id: filters.region_id ?? '',
        province_id: filters.province_id ?? '',
        ownership_type: filters.ownership_type ?? '',
        category: filters.category ?? '',
        service_id: filters.service_id ?? '',
    };

    const [values, setValues] = useState(blankValues);

    useEffect(() => {
        setValues({
            search: filters.search ?? '',
            region_id: filters.region_id ?? '',
            province_id: filters.province_id ?? '',
            ownership_type: filters.ownership_type ?? '',
            category: filters.category ?? '',
            service_id: filters.service_id ?? '',
        });
    }, [filters.search, filters.region_id, filters.province_id, filters.ownership_type, filters.category, filters.service_id]);

    const apply = (event) => {
        event.preventDefault();
        const params = Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '' && value !== null));
        router.get('/hospitals', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const reset = () => router.get('/hospitals', {}, { preserveState: true, preserveScroll: true, replace: true });

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

    const provinces = regions.find((region) => String(region.id) === String(values.region_id))?.provinces ?? [];
    const canRefer = referringHospitals.length > 0;

    return (
        <AppLayout>
            <Head title="Hospital directory" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Network directory"
                    title="Find the right receiving hospital"
                    description="Browse participating facilities, compare reported bed capacity, and start a referral when your hospital needs to transfer a patient."
                    actions={
                        <>
                            <Badge tone="brand">{formatNumber(hospitals.length)} facilities in view</Badge>
                            <Link href="/" className="btn btn-secondary btn-sm">
                                Network dashboard
                                <ArrowRight className="size-3.5" />
                            </Link>
                        </>
                    }
                />

                <Card className="p-4 sm:p-5">
                    <form onSubmit={apply} className="space-y-3">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
                            <Field label="Search" htmlFor="hospital-search" className="lg:w-64">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
                                    <input
                                        id="hospital-search"
                                        type="search"
                                        value={values.search}
                                        onChange={(event) => update('search', event.target.value)}
                                        placeholder="Hospital name or code"
                                        className="input pl-10"
                                    />
                                </div>
                            </Field>

                            <Field label="Region" htmlFor="hospital-region" className="lg:w-44">
                                <Select id="hospital-region" value={values.region_id} onChange={(event) => update('region_id', event.target.value)}>
                                    <option value="">All regions</option>
                                    {regions.map((region) => (
                                        <option key={region.id} value={region.id}>
                                            {region.short_name || region.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <Field label="Province" htmlFor="hospital-province" className="lg:w-48">
                                <Select
                                    id="hospital-province"
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

                            <Field label="Ownership" htmlFor="hospital-ownership" className="lg:w-36">
                                <Select
                                    id="hospital-ownership"
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

                            <Field label="Bed category" htmlFor="hospital-category" className="lg:w-48">
                                <Select id="hospital-category" value={values.category} onChange={(event) => update('category', event.target.value)}>
                                    <option value="">Any category</option>
                                    {(reference?.categories ?? []).map((category) => (
                                        <option key={category.id} value={category.slug}>
                                            {category.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        </div>

                        <div className="flex flex-col gap-3 border-t border-ink-100 pt-3 sm:flex-row sm:items-end sm:justify-between">
                            <Field label="Service" htmlFor="hospital-service" className="sm:w-72">
                                <Select id="hospital-service" value={values.service_id} onChange={(event) => update('service_id', event.target.value)}>
                                    <option value="">Any service</option>
                                    {(reference?.services ?? []).map((service) => (
                                        <option key={service.id} value={service.id}>
                                            {service.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <div className="flex items-center gap-2">
                                <Button type="button" variant="ghost" size="sm" onClick={reset}>
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

                {hospitals.length ? (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {hospitals.map((hospital) => {
                            const categories = hospital.categories ?? [];
                            const erAvailable = categories.filter((category) => category.is_er).reduce((sum, category) => sum + category.available, 0);
                            const services = hospital.services ?? [];

                            return (
                                <Card
                                    key={hospital.hospital_id}
                                    className="flex flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-[var(--shadow-lift)]"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <Badge tone={toneForOwnership(hospital.ownership_type)}>{humanize(hospital.ownership_type)}</Badge>
                                            <Badge tone={toneForHospitalStatus(hospital.status)} dot>
                                                {humanize(hospital.status)}
                                            </Badge>
                                        </div>
                                        <FreshnessBadge freshness={hospital.freshness} label={hospital.freshness_label} />
                                    </div>

                                    <div className="mt-3">
                                        <p className="text-[10px] font-semibold tracking-wide text-ink-400 uppercase">
                                            {hospital.hospital_code} · {facilityLevel(hospital.facility_type)}
                                        </p>
                                        <Link
                                            href={`/hospitals/${hospital.hospital_id}`}
                                            className="mt-1 block font-display text-base font-bold text-ink-950 hover:text-brand-700"
                                        >
                                            {hospital.hospital_name}
                                        </Link>
                                        <p className="mt-1.5 flex items-center gap-1.5 text-xs text-ink-500">
                                            <MapPin className="size-3.5 shrink-0" />
                                            <span className="truncate">{hospital.location}</span>
                                        </p>
                                    </div>

                                    <div className="mt-4 rounded-xl bg-ink-50/80 p-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="flex items-center gap-1.5 text-xs font-semibold text-ink-700">
                                                <BedDouble className="size-4 text-emerald-600" />
                                                Available beds
                                            </span>
                                            <span className="text-[10px] text-ink-400">ER: {formatNumber(erAvailable)}</span>
                                        </div>
                                        <CapacityBar available={hospital.counts.available} total={hospital.counts.total} className="mt-2" />
                                        <div className="mt-2.5 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-ink-500">
                                            <span>{formatNumber(hospital.counts.occupied)} occupied</span>
                                            <span>{formatNumber(hospital.counts.reserved)} reserved</span>
                                            <span>{formatNumber(hospital.counts.cleaning)} cleaning</span>
                                            <span>{formatNumber(hospital.counts.out_of_service)} OOS</span>
                                        </div>
                                    </div>

                                    {categories.length > 0 && (
                                        <div className="mt-3 flex flex-wrap gap-1.5">
                                            {categories.slice(0, 4).map((category) => (
                                                <span
                                                    key={category.id}
                                                    className={classNames(
                                                        'inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10px] font-medium',
                                                        category.available > 0
                                                            ? 'border-emerald-100 bg-emerald-50 text-emerald-700'
                                                            : 'border-ink-100 bg-ink-50 text-ink-500',
                                                    )}
                                                >
                                                    {category.name}
                                                    <span className="font-bold">{category.available}</span>
                                                </span>
                                            ))}
                                            {categories.length > 4 && (
                                                <span className="inline-flex items-center rounded-md bg-ink-100 px-1.5 py-0.5 text-[10px] font-medium text-ink-500">
                                                    +{categories.length - 4}
                                                </span>
                                            )}
                                        </div>
                                    )}

                                    <div className="mt-3 min-h-10">
                                        {services.length ? (
                                            <div className="flex flex-wrap gap-1.5">
                                                {services.slice(0, 3).map((service) => (
                                                    <span key={service} className="rounded-md bg-ink-50 px-1.5 py-0.5 text-[10px] text-ink-500 ring-1 ring-ink-100">
                                                        {service}
                                                    </span>
                                                ))}
                                                {services.length > 3 && (
                                                    <span className="rounded-md px-1 py-0.5 text-[10px] font-medium text-ink-400">
                                                        +{services.length - 3} more
                                                    </span>
                                                )}
                                            </div>
                                        ) : (
                                            <p className="flex items-center gap-1 text-[10px] text-ink-400">
                                                <Stethoscope className="size-3" />
                                                No services listed
                                            </p>
                                        )}
                                    </div>

                                    <div className="mt-auto flex items-center justify-between gap-2 border-t border-ink-100 pt-4">
                                        <span className="text-[10px] text-ink-400">Reported {relativeTime(hospital.last_updated_at)}</span>
                                        <div className="flex items-center gap-1.5">
                                            <Link href={`/hospitals/${hospital.hospital_id}`} className="btn btn-ghost btn-sm">
                                                View
                                            </Link>
                                            {canRefer && hospital.can_refer_here && (
                                                <Link
                                                    href={`/referrals/create?receiving_hospital=${hospital.hospital_id}`}
                                                    className="btn btn-primary btn-sm"
                                                >
                                                    Refer
                                                    <ArrowRight className="size-3.5" />
                                                </Link>
                                            )}
                                        </div>
                                    </div>
                                </Card>
                            );
                        })}
                    </div>
                ) : (
                    <Card>
                        <EmptyState
                            icon={Building2}
                            title="No hospitals match these filters"
                            description="Try a different region, ownership type, or bed category. Hospitals with inactive statuses are hidden by default."
                            action={
                                <Button variant="secondary" onClick={reset}>
                                    Clear filters
                                </Button>
                            }
                        />
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
