import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    BedDouble,
    Building2,
    CalendarClock,
    Clock3,
    Mail,
    MapPin,
    Phone,
    ShieldCheck,
    Stethoscope,
    Users,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import {
    Badge,
    Button,
    CapacityBar,
    Card,
    EmptyState,
    FreshnessBadge,
    InfoCallout,
    Panel,
    StatCard,
} from '../../components/ui';
import {
    formatDateTime,
    formatMinutes,
    formatNumber,
    humanize,
    minutesSince,
    relativeTime,
    toneForHospitalStatus,
    toneForOwnership,
} from '../../lib/utils';

export default function HospitalShow({ hospital, availability, referringHospitals = [], canManageBeds = false }) {
    const counts = availability.counts;
    const categories = availability.categories ?? [];
    const wards = availability.wards ?? [];
    const erCategory = categories.filter((category) => category.is_er);
    const erAvailable = erCategory.reduce((sum, category) => sum + category.available, 0);
    const canRefer = referringHospitals.length > 0;
    const minutes = minutesSince(availability.last_updated_at);

    return (
        <AppLayout>
            <Head title={`${hospital.short_name || hospital.name} profile`} />

            <div className="space-y-6">
                <div>
                    <Link href="/hospitals" className="mb-4 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 hover:text-brand-700">
                        <ArrowLeft className="size-3.5" />
                        Hospital directory
                    </Link>

                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge tone={toneForOwnership(hospital.ownership_type)}>{hospital.ownership_label}</Badge>
                                <Badge tone={toneForHospitalStatus(hospital.status)} dot>
                                    {hospital.status_label}
                                </Badge>
                                <FreshnessBadge freshness={availability.freshness} label={availability.freshness_label} />
                                <span className="font-mono text-[11px] text-ink-400">{hospital.hospital_code}</span>
                            </div>
                            <h1 className="heading-1 mt-2 text-balance">{hospital.name}</h1>
                            <p className="mt-2 flex items-center gap-1.5 text-sm text-ink-500">
                                <MapPin className="size-4 shrink-0" />
                                {hospital.address || [hospital.municipality, hospital.province, hospital.region].filter(Boolean).join(', ')}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {canRefer && (
                                <Link href={`/referrals/create?receiving_hospital=${hospital.id}`} className="btn btn-primary btn-sm">
                                    Refer a patient
                                    <ArrowRight className="size-4" />
                                </Link>
                            )}
                            {canManageBeds && (
                                <Link href={`/beds?hospital=${hospital.id}`} className="btn btn-secondary btn-sm">
                                    <BedDouble className="size-4" />
                                    Manage bed board
                                </Link>
                            )}
                        </div>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Available beds"
                        value={formatNumber(counts.available)}
                        hint={`${formatNumber(counts.operational)} operational of ${formatNumber(counts.total)} total`}
                        icon={BedDouble}
                        tone="emerald"
                    />
                    <StatCard
                        label="Available ER beds"
                        value={formatNumber(erAvailable)}
                        hint={erCategory.length ? `${erCategory.length} emergency categor${erCategory.length === 1 ? 'y' : 'ies'}` : 'No ER category configured'}
                        icon={Stethoscope}
                        tone="rose"
                    />
                    <StatCard label="Occupied" value={formatNumber(counts.occupied)} hint={`${formatNumber(counts.reserved)} reserved holds`} icon={Users} tone="indigo" />
                    <StatCard
                        label="Last report"
                        value={availability.last_updated_at ? relativeTime(availability.last_updated_at) : 'No report'}
                        hint={availability.last_updated_by ? `By ${availability.last_updated_by}` : 'No updater recorded'}
                        icon={Clock3}
                        tone="sky"
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <div className="space-y-4 xl:col-span-2">
                        <Panel title="Availability by bed category" description="Counts derive directly from the status of each active bed." bodyClassName="p-0">
                            {categories.length ? (
                                <div className="overflow-x-auto">
                                    <table className="table table-compact">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th className="w-48">Available</th>
                                                <th className="text-right">Total</th>
                                                <th className="text-right">Occupied</th>
                                                <th className="text-right">Reserved</th>
                                                <th className="text-right">Cleaning</th>
                                                <th className="text-right">OOS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {categories.map((category) => (
                                                <tr key={category.id}>
                                                    <td>
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium text-ink-900">{category.name}</span>
                                                            {category.is_er && <Badge tone="rose">ER</Badge>}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <CapacityBar available={category.available} total={category.total} />
                                                    </td>
                                                    <td className="text-right font-medium">{formatNumber(category.total)}</td>
                                                    <td className="text-right">{formatNumber(category.occupied)}</td>
                                                    <td className="text-right">{formatNumber(category.reserved)}</td>
                                                    <td className="text-right">{formatNumber(category.cleaning)}</td>
                                                    <td className="text-right">{formatNumber(category.out_of_service)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <EmptyState icon={BedDouble} title="No bed inventory reported" description="This hospital has not configured bed categories yet." />
                            )}
                        </Panel>

                        <Panel title="Ward availability" description="Where the reported capacity sits inside the hospital." bodyClassName="p-0">
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
                                <EmptyState icon={BedDouble} title="No wards configured" description="Ward-level availability has not been reported." />
                            )}
                        </Panel>

                        <Panel title="Services" description="Services listed by this facility. Availability must still be confirmed by the hospital.">
                            {hospital.services?.length ? (
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {hospital.services.map((service) => (
                                        <div key={service.id} className="rounded-xl border border-ink-100 p-3">
                                            <p className="flex items-center gap-2 text-xs font-semibold text-ink-900">
                                                <Stethoscope className="size-4 text-brand-600" />
                                                {service.name}
                                            </p>
                                            {service.description && <p className="subtle mt-1.5">{service.description}</p>}
                                            {service.availability_notes && (
                                                <p className="mt-1.5 rounded-lg bg-amber-50 px-2 py-1 text-[10px] leading-4 text-amber-800">
                                                    {service.availability_notes}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <EmptyState icon={Stethoscope} title="No services listed" description="This hospital has not published its service list." />
                            )}
                        </Panel>

                        {hospital.notes && (
                            <Panel title="Hospital notes">
                                <p className="text-sm leading-6 text-ink-600">{hospital.notes}</p>
                            </Panel>
                        )}
                    </div>

                    <div className="space-y-4">
                        <Panel title="Contact & profile">
                            <dl className="space-y-4">
                                <div className="flex gap-3">
                                    <span className="icon-tile size-9 bg-ink-100 text-ink-500">
                                        <MapPin className="size-4" />
                                    </span>
                                    <div className="min-w-0">
                                        <dt className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Address</dt>
                                        <dd className="mt-0.5 text-xs leading-5 text-ink-700">
                                            {[hospital.address, hospital.municipality, hospital.province].filter(Boolean).join(', ') || 'Not provided'}
                                        </dd>
                                    </div>
                                </div>
                                <div className="flex gap-3">
                                    <span className="icon-tile size-9 bg-ink-100 text-ink-500">
                                        <Phone className="size-4" />
                                    </span>
                                    <div className="min-w-0">
                                        <dt className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Phone</dt>
                                        <dd className="mt-0.5 text-xs text-ink-700">
                                            {hospital.phone ? (
                                                <a href={`tel:${hospital.phone}`} className="hover:text-brand-700">
                                                    {hospital.phone}
                                                </a>
                                            ) : (
                                                'Not provided'
                                            )}
                                        </dd>
                                    </div>
                                </div>
                                <div className="flex gap-3">
                                    <span className="icon-tile size-9 bg-ink-100 text-ink-500">
                                        <Mail className="size-4" />
                                    </span>
                                    <div className="min-w-0">
                                        <dt className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Email</dt>
                                        <dd className="mt-0.5 truncate text-xs text-ink-700">
                                            {hospital.email ? (
                                                <a href={`mailto:${hospital.email}`} className="hover:text-brand-700">
                                                    {hospital.email}
                                                </a>
                                            ) : (
                                                'Not provided'
                                            )}
                                        </dd>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3 border-t border-ink-100 pt-3">
                                    <div>
                                        <dt className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Facility</dt>
                                        <dd className="mt-0.5 text-xs font-medium text-ink-700">{humanize(hospital.facility_type)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Bed capacity</dt>
                                        <dd className="mt-0.5 text-xs font-medium text-ink-700">{formatNumber(hospital.bed_capacity)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Province</dt>
                                        <dd className="mt-0.5 text-xs font-medium text-ink-700">{hospital.province || '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Region</dt>
                                        <dd className="mt-0.5 text-xs font-medium text-ink-700">{hospital.region || '—'}</dd>
                                    </div>
                                </div>
                            </dl>
                        </Panel>

                        <Panel title="Report freshness" description="Availability is only as good as the last confirmed report.">
                            <div className="flex items-center justify-between gap-2">
                                <FreshnessBadge freshness={availability.freshness} label={availability.freshness_label} />
                                <span className="text-[11px] text-ink-500">{formatMinutes(minutes)}</span>
                            </div>
                            <p className="mt-3 text-xs leading-5 text-ink-500">
                                {availability.last_updated_at
                                    ? `Last confirmed ${formatDateTime(availability.last_updated_at)}${availability.last_updated_by ? ` by ${availability.last_updated_by}` : ''}.`
                                    : 'No bed update has been recorded for this hospital.'}
                            </p>
                            {availability.is_stale && (
                                <InfoCallout tone="amber" className="mt-3">
                                    This report is stale. Confirm directly with the hospital before committing a transfer.
                                </InfoCallout>
                            )}
                        </Panel>

                        {canRefer ? (
                            <Card className="overflow-hidden bg-ink-950 p-5 text-white">
                                <p className="text-[10px] font-semibold tracking-[0.14em] text-brand-300 uppercase">Referral coordination</p>
                                <h3 className="mt-2 font-display text-lg font-bold">Need to transfer a patient here?</h3>
                                <p className="mt-1.5 text-xs leading-5 text-ink-300">
                                    Availability is not acceptance. Submit a referral and wait for authorized receiving-hospital staff to respond.
                                </p>
                                <Link
                                    href={`/referrals/create?receiving_hospital=${hospital.id}`}
                                    className="btn btn-primary btn-sm mt-4 w-full"
                                >
                                    Start a referral
                                    <ArrowRight className="size-3.5" />
                                </Link>
                            </Card>
                        ) : (
                            <InfoCallout tone="slate" title="Referral access required">
                                Your account can browse availability but does not hold a referral-capable hospital role.
                            </InfoCallout>
                        )}

                        <InfoCallout tone="slate" title="Data integrity" icon={ShieldCheck}>
                            Counts are calculated from individual bed records. BHN never accepts a manually entered hospital-wide total.
                        </InfoCallout>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
