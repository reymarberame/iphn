import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, KeyRound, LockKeyhole, Mail, ShieldCheck } from 'lucide-react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, InfoCallout, TextInput } from '../../components/ui';

export default function Login({ demoAccounts = [], demoPassword = null, status }) {
    const [showPassword, setShowPassword] = useState(false);
    const [showDemo, setShowDemo] = useState(false);
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event) => {
        event.preventDefault();
        form.post('/login', { preserveScroll: true });
    };

    const useDemoAccount = (email) => {
        form.setData({ email, password: demoPassword ?? '', remember: false });
        form.clearErrors();
    };

    return (
        <AuthLayout
            title="Sign in to BHN"
            subtitle="Use the account provisioned by your network administrator."
            footer={
                <div className="flex flex-col items-center gap-2 text-center">
                    <p className="text-xs text-ink-500">
                        Need an account?{' '}
                        <span className="font-semibold text-ink-700">Ask your hospital or network administrator.</span>
                    </p>
                    <Link href="/forgot-password" className="text-xs font-semibold text-brand-700 hover:underline">
                        Forgot your password?
                    </Link>
                </div>
            }
        >
            <Head title="Sign in" />

            {status && (
                <InfoCallout tone="emerald" className="mb-5">
                    {status}
                </InfoCallout>
            )}

            <form onSubmit={submit} className="space-y-5">
                <Field label="Email address" htmlFor="email" error={form.errors.email} required>
                    <div className="relative">
                        <Mail className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                            error={form.errors.email}
                            className="pl-10"
                            placeholder="you@hospital.test"
                            autoComplete="username"
                            autoFocus
                            required
                        />
                    </div>
                </Field>

                <Field label="Password" htmlFor="password" error={form.errors.password} required>
                    <div className="relative">
                        <LockKeyhole className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
                        <TextInput
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            name="password"
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                            error={form.errors.password}
                            className="pr-11 pl-10"
                            placeholder="Enter your password"
                            autoComplete="current-password"
                            required
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword((visible) => !visible)}
                            className="absolute top-1/2 right-2 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-ink-400 transition hover:bg-ink-100 hover:text-ink-700"
                            aria-label={showPassword ? 'Hide password' : 'Show password'}
                        >
                            {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                        </button>
                    </div>
                </Field>

                <div className="flex items-center justify-between gap-3">
                    <label htmlFor="remember" className="flex cursor-pointer items-center gap-2 text-xs font-medium text-ink-600">
                        <input
                            id="remember"
                            type="checkbox"
                            checked={form.data.remember}
                            onChange={(event) => form.setData('remember', event.target.checked)}
                            className="checkbox"
                        />
                        Keep me signed in
                    </label>
                    <span className="flex items-center gap-1.5 text-[11px] text-ink-400">
                        <ShieldCheck className="size-3.5" />
                        Encrypted session
                    </span>
                </div>

                <Button type="submit" size="lg" className="w-full" disabled={form.processing}>
                    {form.processing ? (
                        <>
                            <span className="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                            Signing in…
                        </>
                    ) : (
                        <>
                            Sign in
                            <ArrowRight className="size-4" />
                        </>
                    )}
                </Button>
            </form>

            {demoAccounts.length > 0 && (
                <div className="mt-6 border-t border-ink-100 pt-5">
                    <button
                        type="button"
                        onClick={() => setShowDemo((open) => !open)}
                        className="flex w-full items-center justify-between gap-3 text-left"
                        aria-expanded={showDemo}
                    >
                        <span className="flex items-center gap-2 text-xs font-semibold text-ink-700">
                            <KeyRound className="size-4 text-brand-600" />
                            Demo accounts
                        </span>
                        <span className="text-[11px] font-medium text-brand-700">{showDemo ? 'Hide' : 'Show list'}</span>
                    </button>

                    {showDemo && (
                        <div className="mt-3 space-y-2 animate-fade-in">
                            <p className="text-[11px] leading-5 text-ink-500">
                                {demoPassword ? (
                                    <>
                                        All demo accounts use the password{' '}
                                        <code className="rounded bg-ink-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-ink-700">{demoPassword}</code>
                                        . Select an account to fill the form.
                                    </>
                                ) : (
                                    'Select an account to fill the email field, then enter its password.'
                                )}
                            </p>
                            <div className="max-h-64 space-y-1.5 overflow-y-auto rounded-xl border border-ink-100 bg-ink-50/60 p-2">
                                {demoAccounts.map((account) => (
                                    <button
                                        key={account.email}
                                        type="button"
                                        onClick={() => useDemoAccount(account.email)}
                                        className="flex w-full items-start justify-between gap-2 rounded-lg bg-white px-3 py-2 text-left ring-1 ring-ink-100 transition hover:ring-brand-300"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate text-[11px] font-semibold text-ink-800">{account.name}</span>
                                            <span className="block truncate text-[10px] text-ink-500">{account.email}</span>
                                        </span>
                                        <span className="shrink-0 rounded-full bg-ink-100 px-2 py-0.5 text-[9px] font-semibold text-ink-600">
                                            {account.context}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </AuthLayout>
    );
}
