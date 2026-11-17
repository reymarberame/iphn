import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Clock3, Mail, RotateCcw, Save, ServerCog, ShieldCheck, SlidersHorizontal, Timer } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import {
    Badge,
    Button,
    Card,
    Field,
    FreshnessBadge,
    InfoCallout,
    PageHeader,
    Panel,
    Textarea,
    TextInput,
} from '../../../components/ui';

export default function AdminSettingsIndex({ settings }) {
    const form = useForm({
        network_name: settings?.network_name ?? '',
        freshness_fresh_minutes: settings?.freshness_fresh_minutes ?? 15,
        freshness_aging_minutes: settings?.freshness_aging_minutes ?? 60,
        reservation_ttl_minutes: settings?.reservation_ttl_minutes ?? 120,
        availability_disclaimer: settings?.availability_disclaimer ?? '',
        support_email: settings?.support_email ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.put('/admin/settings', { preserveScroll: true });
    };

    const reset = () => {
        form.setData({
            network_name: settings?.network_name ?? '',
            freshness_fresh_minutes: settings?.freshness_fresh_minutes ?? 15,
            freshness_aging_minutes: settings?.freshness_aging_minutes ?? 60,
            reservation_ttl_minutes: settings?.reservation_ttl_minutes ?? 120,
            availability_disclaimer: settings?.availability_disclaimer ?? '',
            support_email: settings?.support_email ?? '',
        });
        form.clearErrors();
    };

    return (
        <AppLayout>
            <Head title="Platform settings" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Administration"
                    title="Platform settings"
                    description="Control network identity, bed-report freshness thresholds, reservation lifetime, and the availability disclaimer shown across IPHN."
                    actions={
                        <Badge tone="brand">
                            <ShieldCheck className="size-3" />
                            Network administrators only
                        </Badge>
                    }
                />

                <form onSubmit={submit} className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="space-y-5">
                        <Panel title="Network identity" description="Shown in navigation, footers, and outgoing support links." icon={ServerCog}>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Network name" htmlFor="setting-network-name" error={form.errors.network_name} required>
                                    <TextInput
                                        id="setting-network-name"
                                        value={form.data.network_name}
                                        onChange={(event) => form.setData('network_name', event.target.value)}
                                        error={form.errors.network_name}
                                    />
                                </Field>

                                <Field label="Support email" htmlFor="setting-support-email" error={form.errors.support_email} required>
                                    <TextInput
                                        id="setting-support-email"
                                        type="email"
                                        value={form.data.support_email}
                                        onChange={(event) => form.setData('support_email', event.target.value)}
                                        error={form.errors.support_email}
                                    />
                                </Field>
                            </div>
                        </Panel>

                        <Panel
                            title="Bed-report freshness"
                            description="Minutes since the most recent bed status change at each hospital."
                            icon={Clock3}
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Fresh threshold"
                                    htmlFor="setting-fresh-minutes"
                                    error={form.errors.freshness_fresh_minutes}
                                    hint="A report updated within this many minutes is shown as fresh."
                                    required
                                >
                                    <div className="relative">
                                        <TextInput
                                            id="setting-fresh-minutes"
                                            type="number"
                                            min="5"
                                            max="120"
                                            value={form.data.freshness_fresh_minutes}
                                            onChange={(event) => form.setData('freshness_fresh_minutes', event.target.value)}
                                            error={form.errors.freshness_fresh_minutes}
                                            className="pr-16"
                                        />
                                        <span className="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-xs text-ink-400">minutes</span>
                                    </div>
                                </Field>

                                <Field
                                    label="Aging threshold"
                                    htmlFor="setting-aging-minutes"
                                    error={form.errors.freshness_aging_minutes}
                                    hint="Must be greater than the fresh threshold. Beyond this, reports are stale."
                                    required
                                >
                                    <div className="relative">
                                        <TextInput
                                            id="setting-aging-minutes"
                                            type="number"
                                            min="10"
                                            max="240"
                                            value={form.data.freshness_aging_minutes}
                                            onChange={(event) => form.setData('freshness_aging_minutes', event.target.value)}
                                            error={form.errors.freshness_aging_minutes}
                                            className="pr-16"
                                        />
                                        <span className="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-xs text-ink-400">minutes</span>
                                    </div>
                                </Field>
                            </div>

                            <div className="mt-5 grid gap-2 sm:grid-cols-3">
                                <div className="rounded-xl border border-emerald-100 bg-emerald-50/50 p-3">
                                    <FreshnessBadge freshness="fresh" label="Fresh" />
                                    <p className="mt-2 text-[11px] leading-4 text-ink-600">
                                        Updated within {form.data.freshness_fresh_minutes || 0} minutes
                                    </p>
                                </div>
                                <div className="rounded-xl border border-amber-100 bg-amber-50/50 p-3">
                                    <FreshnessBadge freshness="aging" label="Aging" />
                                    <p className="mt-2 text-[11px] leading-4 text-ink-600">
                                        Updated {form.data.freshness_fresh_minutes || 0}–{form.data.freshness_aging_minutes || 0} minutes ago
                                    </p>
                                </div>
                                <div className="rounded-xl border border-rose-100 bg-rose-50/50 p-3">
                                    <FreshnessBadge freshness="stale" label="Stale" />
                                    <p className="mt-2 text-[11px] leading-4 text-ink-600">
                                        Not confirmed for more than {form.data.freshness_aging_minutes || 0} minutes
                                    </p>
                                </div>
                            </div>

                            <InfoCallout tone="amber" className="mt-4">
                                Changing thresholds applies immediately to dashboards, the directory, the bed board, and API responses. Existing bed data is not modified.
                            </InfoCallout>
                        </Panel>

                        <Panel title="Bed reservation lifetime" description="How long an accepted referral may hold a bed before the reservation expires." icon={Timer}>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Reservation lifetime"
                                    htmlFor="setting-reservation-ttl"
                                    error={form.errors.reservation_ttl_minutes}
                                    hint="Allowed range: 30 to 720 minutes (12 hours)."
                                    required
                                >
                                    <div className="relative">
                                        <TextInput
                                            id="setting-reservation-ttl"
                                            type="number"
                                            min="30"
                                            max="720"
                                            value={form.data.reservation_ttl_minutes}
                                            onChange={(event) => form.setData('reservation_ttl_minutes', event.target.value)}
                                            error={form.errors.reservation_ttl_minutes}
                                            className="pr-16"
                                        />
                                        <span className="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-xs text-ink-400">minutes</span>
                                    </div>
                                </Field>

                                <div className="rounded-xl bg-ink-50 p-3">
                                    <p className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Current interpretation</p>
                                    <p className="mt-1 text-sm font-semibold text-ink-900">
                                        {Math.floor((Number(form.data.reservation_ttl_minutes) || 0) / 60)} hr{' '}
                                        {(Number(form.data.reservation_ttl_minutes) || 0) % 60} min
                                    </p>
                                    <p className="mt-1 text-[11px] leading-4 text-ink-500">
                                        Expired holds release automatically. An accepted referral may place a new hold while no active reservation exists.
                                    </p>
                                </div>
                            </div>
                        </Panel>

                        <Panel title="Availability disclaimer" description="Displayed with availability views and referenced in referral coordination." icon={SlidersHorizontal}>
                            <Field
                                label="Disclaimer text"
                                htmlFor="setting-disclaimer"
                                error={form.errors.availability_disclaimer}
                                hint="Keep this clear: reported availability does not guarantee admission or acceptance."
                                required
                            >
                                <Textarea
                                    id="setting-disclaimer"
                                    rows={4}
                                    value={form.data.availability_disclaimer}
                                    onChange={(event) => form.setData('availability_disclaimer', event.target.value)}
                                    error={form.errors.availability_disclaimer}
                                />
                            </Field>
                        </Panel>

                        <div className="flex flex-col gap-3 rounded-2xl border border-ink-100 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-[11px] leading-5 text-ink-500">
                                Settings changes are recorded in the audit trail. Thresholds affect every user immediately.
                            </p>
                            <div className="flex shrink-0 items-center gap-2">
                                <Button type="button" variant="secondary" onClick={reset} disabled={form.processing}>
                                    <RotateCcw className="size-4" />
                                    Reset
                                </Button>
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? (
                                        <>
                                            <span className="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                                            Saving…
                                        </>
                                    ) : (
                                        <>
                                            <Save className="size-4" />
                                            Save settings
                                        </>
                                    )}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <aside className="space-y-4 xl:sticky xl:top-24 xl:self-start">
                        <Card className="overflow-hidden">
                            <div className="bg-ink-950 p-5 text-white">
                                <p className="text-[10px] font-semibold tracking-[0.14em] text-brand-300 uppercase">Current policy</p>
                                <h2 className="mt-1 font-display text-lg font-bold">{form.data.network_name || 'Inter-Provincial Hospital Network'}</h2>
                                <p className="mt-1 text-[11px] text-ink-300">Availability is manually reported by each hospital.</p>
                            </div>
                            <div className="space-y-3 p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-xs text-ink-600">Fresh</span>
                                    <Badge tone="emerald">≤ {form.data.freshness_fresh_minutes || 0} min</Badge>
                                </div>
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-xs text-ink-600">Aging</span>
                                    <Badge tone="amber">≤ {form.data.freshness_aging_minutes || 0} min</Badge>
                                </div>
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-xs text-ink-600">Stale</span>
                                    <Badge tone="rose">&gt; {form.data.freshness_aging_minutes || 0} min</Badge>
                                </div>
                                <div className="flex items-center justify-between gap-2 border-t border-ink-100 pt-3">
                                    <span className="text-xs text-ink-600">Reservation TTL</span>
                                    <Badge tone="sky">{form.data.reservation_ttl_minutes || 0} min</Badge>
                                </div>
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-xs text-ink-600">Support</span>
                                    <span className="max-w-44 truncate text-[11px] font-medium text-brand-700">{form.data.support_email || '—'}</span>
                                </div>
                            </div>
                        </Card>

                        <InfoCallout tone="brand" title="Manual availability model" icon={Clock3}>
                            IPHN never infers bed status from totals. Every availability count is derived from the status of individual beds reported by hospital staff.
                        </InfoCallout>

                        <InfoCallout tone="slate" title="Privacy boundaries">
                            Referral records contain minimum coordination data only. Platform settings cannot expand who can view patient-linked referrals.
                        </InfoCallout>
                    </aside>
                </form>
            </div>
        </AppLayout>
    );
}
