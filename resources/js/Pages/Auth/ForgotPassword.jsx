import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Mail, Send } from 'lucide-react';
import AuthLayout from '../../Layouts/AuthLayout';
import { Button, Field, TextInput } from '../../components/ui';

export default function ForgotPassword() {
    const form = useForm({ email: '' });

    const submit = (event) => {
        event.preventDefault();
        form.post('/forgot-password', { preserveScroll: true });
    };

    return (
        <AuthLayout
            title="Reset your password"
            subtitle="Enter your work email and we will send a secure reset link if the account is active."
            footer={
                <p className="text-center text-xs text-ink-500">
                    <Link href="/login" className="inline-flex items-center gap-1.5 font-semibold text-brand-700 hover:underline">
                        <ArrowLeft className="size-3.5" />
                        Back to sign in
                    </Link>
                </p>
            }
        >
            <Head title="Forgot password" />

            <form onSubmit={submit} className="space-y-5">
                <Field
                    label="Email address"
                    htmlFor="email"
                    error={form.errors.email}
                    hint="Password resets are only sent to active accounts provisioned by an administrator."
                    required
                >
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

                <Button type="submit" size="lg" className="w-full" disabled={form.processing}>
                    {form.processing ? (
                        <>
                            <span className="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                            Sending reset link…
                        </>
                    ) : (
                        <>
                            <Send className="size-4" />
                            Send reset link
                        </>
                    )}
                </Button>
            </form>

            <p className="mt-5 text-center text-[11px] leading-5 text-ink-400">
                For security, this form does not reveal whether an email address belongs to an account.
            </p>
        </AuthLayout>
    );
}
