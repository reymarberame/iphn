import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Eye, EyeOff, LockKeyhole, Mail } from 'lucide-react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, TextInput } from '../../components/ui';

export default function ResetPassword({ token, email }) {
    const [showPassword, setShowPassword] = useState(false);
    const form = useForm({
        token,
        email: email || '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post('/reset-password', { preserveScroll: true });
    };

    return (
        <AuthLayout
            title="Choose a new password"
            subtitle="Your reset link is single-use. Choose a password with at least 10 characters, mixed case, and a number."
            footer={
                <p className="text-center text-xs text-ink-500">
                    <Link href="/login" className="inline-flex items-center gap-1.5 font-semibold text-brand-700 hover:underline">
                        <ArrowLeft className="size-3.5" />
                        Back to sign in
                    </Link>
                </p>
            }
        >
            <Head title="Reset password" />

            <form onSubmit={submit} className="space-y-5">
                <input type="hidden" name="token" value={form.data.token} />

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
                            autoComplete="username"
                            required
                        />
                    </div>
                </Field>

                <Field label="New password" htmlFor="password" error={form.errors.password} required>
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
                            placeholder="Minimum 10 characters"
                            autoComplete="new-password"
                            autoFocus
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

                <Field label="Confirm new password" htmlFor="password_confirmation" error={form.errors.password_confirmation} required>
                    <div className="relative">
                        <LockKeyhole className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
                        <TextInput
                            id="password_confirmation"
                            type={showPassword ? 'text' : 'password'}
                            name="password_confirmation"
                            value={form.data.password_confirmation}
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                            error={form.errors.password_confirmation}
                            className="pl-10"
                            autoComplete="new-password"
                            required
                        />
                    </div>
                </Field>

                <Button type="submit" size="lg" className="w-full" disabled={form.processing}>
                    {form.processing ? (
                        <>
                            <span className="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                            Saving password…
                        </>
                    ) : (
                        'Save new password'
                    )}
                </Button>
            </form>
        </AuthLayout>
    );
}
