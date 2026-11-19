import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Compass, Gauge, LockKeyhole, ServerCrash, TimerOff, TriangleAlert } from 'lucide-react';

const STATUS_META = {
    403: {
        title: 'You do not have access to this area',
        description: 'Your role or hospital scope does not permit this action. If you believe this is a mistake, contact your administrator.',
        icon: LockKeyhole,
        tone: 'bg-amber-50 text-amber-700',
    },
    404: {
        title: 'This page could not be found',
        description: 'The record may have been moved, completed, or removed, or the address may be incorrect.',
        icon: Compass,
        tone: 'bg-sky-50 text-sky-700',
    },
    419: {
        title: 'Your session expired',
        description: 'For your security, the form session timed out. Sign in again and repeat the action.',
        icon: TimerOff,
        tone: 'bg-amber-50 text-amber-700',
    },
    429: {
        title: 'Too many requests',
        description: 'Please wait a moment before trying again.',
        icon: Gauge,
        tone: 'bg-violet-50 text-violet-700',
    },
    500: {
        title: 'Something went wrong',
        description: 'The platform could not complete this request. The issue may be temporary; try again or contact support.',
        icon: ServerCrash,
        tone: 'bg-rose-50 text-rose-700',
    },
    503: {
        title: 'Service temporarily unavailable',
        description: 'BHN is briefly unavailable for maintenance. Please try again shortly.',
        icon: ServerCrash,
        tone: 'bg-rose-50 text-rose-700',
    },
};

export default function ErrorPage({ status = 500, message = null }) {
    const { auth, settings } = usePage().props;
    const user = auth?.user;
    const meta = STATUS_META[status] ?? {
        title: 'Request could not be completed',
        description: 'An unexpected error occurred while processing this request.',
        icon: TriangleAlert,
        tone: 'bg-rose-50 text-rose-700',
    };
    const Icon = meta.icon;

    return (
        <>
            <Head title={`Error ${status}`} />

            <div className="flex min-h-screen flex-col bg-ink-50">
                <header className="border-b border-ink-100 bg-white">
                    <div className="mx-auto flex h-16 w-full max-w-5xl items-center justify-between gap-3 px-4 sm:px-6">
                        <Link href={user ? '/' : '/login'} className="flex items-center gap-3">
                            <span className="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white ring-1 ring-ink-100">
                                <img
                                    src="/images/bukidnon-seal.jpg"
                                    alt="Province of Bukidnon official seal"
                                    className="size-9 object-cover"
                                />
                            </span>
                            <span>
                                <span className="block font-display text-sm font-extrabold tracking-tight text-ink-950">BHN</span>
                                <span className="block text-[10px] font-medium tracking-wide text-ink-500 uppercase">Care coordination</span>
                            </span>
                        </Link>
                        <span className="text-[11px] font-medium text-ink-400">{settings?.network_name ?? 'Bukidnon Hospital Network'}</span>
                    </div>
                </header>

                <main className="flex flex-1 items-center justify-center px-4 py-12 sm:px-6">
                    <div className="w-full max-w-xl">
                        <div className="card overflow-hidden text-center">
                            <div className="px-6 pt-10 pb-8 sm:px-10">
                                <span className={`mx-auto flex size-14 items-center justify-center rounded-2xl ${meta.tone}`}>
                                    <Icon className="size-7" />
                                </span>
                                <p className="mt-5 font-display text-5xl font-extrabold tracking-tight text-ink-950">{status}</p>
                                <h1 className="mt-2 text-balance font-display text-xl font-bold text-ink-900">{meta.title}</h1>
                                <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-500">{message || meta.description}</p>

                                {message && message !== meta.description && (
                                    <div className="mt-4 rounded-xl border border-ink-100 bg-ink-50 px-4 py-3 text-xs leading-5 text-ink-600">{meta.description}</div>
                                )}

                                <div className="mt-7 flex flex-col justify-center gap-2 sm:flex-row">
                                    <Link href={user ? '/' : '/login'} className="btn btn-primary">
                                        {user ? 'Return to dashboard' : 'Go to sign in'}
                                    </Link>
                                    <button type="button" onClick={() => window.history.back()} className="btn btn-secondary">
                                        <ArrowLeft className="size-4" />
                                        Go back
                                    </button>
                                </div>
                            </div>

                            <div className="border-t border-ink-100 bg-ink-50/60 px-6 py-4 text-[10px] leading-4 text-ink-400 sm:px-10">
                                If this keeps happening, contact{' '}
                                <a href={`mailto:${settings?.support_email ?? 'support@bhn.test'}`} className="font-semibold text-brand-700 hover:underline">
                                    {settings?.support_email ?? 'support@bhn.test'}
                                </a>
                                . Do not include patient information in support requests.
                            </div>
                        </div>

                        <p className="mt-4 text-center text-[10px] text-ink-400">
                            Availability is manually reported. Confirm every referral with the receiving hospital.
                        </p>
                    </div>
                </main>
            </div>
        </>
    );
}
