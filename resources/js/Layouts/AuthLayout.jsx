import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { BedDouble, CircleCheck, HeartPulse, MapPinned, ShieldCheck, TriangleAlert } from 'lucide-react';

const highlights = [
    {
        icon: BedDouble,
        title: 'Live bed coordination',
        body: 'Hospital-reported availability across wards, ER, ICU, and isolation beds.',
    },
    {
        icon: MapPinned,
        title: 'Inter-provincial referrals',
        body: 'One shared workflow from referral submission to confirmed arrival.',
    },
    {
        icon: ShieldCheck,
        title: 'Scoped, auditable access',
        body: 'Hospital-level permissions with a complete coordination trail.',
    },
];

export default function AuthLayout({ title, subtitle, children, footer }) {
    const { settings, flash } = usePage().props;

    return (
        <div className="min-h-screen bg-ink-50 lg:grid lg:grid-cols-[1.05fr_1fr]">
            <aside className="relative hidden overflow-hidden bg-ink-950 text-white lg:flex lg:flex-col lg:justify-between">
                <div className="absolute inset-0 opacity-70" aria-hidden="true">
                    <div className="absolute -top-32 -left-24 size-[28rem] rounded-full bg-brand-600/20 blur-3xl" />
                    <div className="absolute right-0 bottom-0 size-[32rem] translate-x-1/3 translate-y-1/3 rounded-full bg-brand-400/10 blur-3xl" />
                    <div
                        className="absolute inset-0 opacity-[0.07]"
                        style={{
                            backgroundImage:
                                'linear-gradient(to right, white 1px, transparent 1px), linear-gradient(to bottom, white 1px, transparent 1px)',
                            backgroundSize: '56px 56px',
                        }}
                    />
                </div>

                <div className="relative z-10 p-10 xl:p-14">
                    <Link href="/" className="inline-flex items-center gap-3">
                        <span className="flex size-11 items-center justify-center rounded-2xl bg-brand-500/15 ring-1 ring-brand-400/30">
                            <HeartPulse className="size-6 text-brand-300" />
                        </span>
                        <span>
                            <span className="block font-display text-lg font-extrabold tracking-tight">IPHN</span>
                            <span className="block text-[10px] font-semibold tracking-[0.18em] text-ink-400 uppercase">
                                Inter-Provincial Hospital Network
                            </span>
                        </span>
                    </Link>

                    <div className="mt-20 max-w-xl">
                        <p className="text-[11px] font-semibold tracking-[0.18em] text-brand-300 uppercase">Coordinated care, province to province</p>
                        <h2 className="mt-4 font-display text-4xl leading-[1.1] font-extrabold tracking-tight text-balance xl:text-5xl">
                            The right bed, at the right hospital, without the phone tree.
                        </h2>
                        <p className="mt-5 max-w-lg text-sm leading-7 text-ink-300">
                            A shared operational picture for participating hospitals: who has capacity, which referrals are moving, and what
                            still needs a confirmation.
                        </p>
                    </div>

                    <ul className="mt-12 space-y-5">
                        {highlights.map((item) => (
                            <li key={item.title} className="flex gap-4">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/[0.06] text-brand-300 ring-1 ring-white/10">
                                    <item.icon className="size-5" />
                                </span>
                                <div>
                                    <p className="text-sm font-semibold text-white">{item.title}</p>
                                    <p className="mt-1 max-w-md text-xs leading-5 text-ink-400">{item.body}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="relative z-10 border-t border-white/10 p-10 xl:p-14">
                    <p className="max-w-md text-[11px] leading-5 text-ink-500">
                        Availability is manually updated by each hospital. A referral is not accepted until authorized receiving-hospital staff
                        confirm it in IPHN.
                    </p>
                </div>
            </aside>

            <main className="flex min-h-screen flex-col justify-center px-4 py-10 sm:px-8 lg:px-12 xl:px-20">
                <div className="mx-auto w-full max-w-md">
                    <div className="mb-8 flex items-center gap-3 lg:hidden">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-brand-700 text-white shadow-sm">
                            <HeartPulse className="size-5" />
                        </span>
                        <div>
                            <p className="font-display text-base font-extrabold text-ink-950">IPHN</p>
                            <p className="text-[10px] font-semibold tracking-wide text-ink-500 uppercase">Inter-Provincial Hospital Network</p>
                        </div>
                    </div>

                    <div className="mb-7">
                        <h1 className="heading-1">{title}</h1>
                        {subtitle && <p className="muted mt-2">{subtitle}</p>}
                    </div>

                    <div className="card p-6 shadow-[var(--shadow-lift)] sm:p-8">
                        {flash?.success && (
                            <div className="mb-5 flex gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-xs leading-5 text-emerald-800">
                                <CircleCheck className="mt-0.5 size-4 shrink-0" />
                                <span>{flash.success}</span>
                            </div>
                        )}
                        {flash?.error && (
                            <div className="mb-5 flex gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-xs leading-5 text-rose-800">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                <span>{flash.error}</span>
                            </div>
                        )}
                        {flash?.info && (
                            <div className="mb-5 flex gap-2.5 rounded-xl border border-sky-200 bg-sky-50 p-3.5 text-xs leading-5 text-sky-800">
                                <CircleCheck className="mt-0.5 size-4 shrink-0" />
                                <span>{flash.info}</span>
                            </div>
                        )}
                        {children}
                    </div>

                    {footer && <div className="mt-6">{footer}</div>}

                    <p className="mt-8 text-center text-[11px] leading-5 text-ink-400">
                        {settings?.network_name ?? 'Inter-Provincial Hospital Network'} · Authorized personnel only. Accounts are provisioned by network
                        administrators.
                    </p>
                </div>
            </main>
        </div>
    );
}
