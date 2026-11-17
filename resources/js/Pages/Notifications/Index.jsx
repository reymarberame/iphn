import React from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bell, BedDouble, CheckCheck, CheckCircle2, ClipboardList, ExternalLink, Info, MailOpen, TriangleAlert } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Button, Card, EmptyState, PageHeader, Pagination } from '../../components/ui';
import { classNames, formatDateTime, relativeTime } from '../../lib/utils';

const TYPE_META = {
    referral: { icon: ClipboardList, label: 'Referral', tone: 'brand' },
    reservation: { icon: BedDouble, label: 'Bed hold', tone: 'amber' },
    stale: { icon: TriangleAlert, label: 'Availability', tone: 'rose' },
    system: { icon: Info, label: 'System', tone: 'slate' },
};

export default function NotificationIndex({ notifications }) {
    const { notifications: shared, auth } = usePage().props;
    const unreadCount = shared?.unread_count ?? 0;
    const canOpenReferrals = auth?.user?.has_hospital_membership;

    const markAllRead = () => {
        router.post('/notifications/read-all', {}, { preserveScroll: true });
    };

    const markRead = (notification) => {
        router.post(`/notifications/${notification.id}/read`, {}, { preserveScroll: true });
    };

    const open = (notification) => {
        if (!notification.read_at) {
            router.post(
                `/notifications/${notification.id}/read`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => router.visit(notification.url || '/notifications'),
                },
            );
            return;
        }

        router.visit(notification.url || '/notifications');
    };

    return (
        <AppLayout>
            <Head title="Notifications" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Inbox"
                    title="Notifications"
                    description="Referral decisions, bed holds, and availability reminders addressed to your account."
                    actions={
                        <div className="flex items-center gap-2">
                            <Badge tone={unreadCount ? 'brand' : 'slate'} dot={unreadCount > 0}>
                                {unreadCount} unread
                            </Badge>
                            <Button variant="secondary" size="sm" onClick={markAllRead} disabled={!unreadCount}>
                                <CheckCheck className="size-4" />
                                Mark all read
                            </Button>
                        </div>
                    }
                />

                <Card className="overflow-hidden">
                    {notifications?.data?.length ? (
                        <>
                            <ul className="divide-y divide-ink-100">
                                {notifications.data.map((notification) => {
                                    const meta = TYPE_META[notification.type] ?? TYPE_META.system;
                                    const Icon = meta.icon;
                                    const unread = !notification.read_at;

                                    return (
                                        <li key={notification.id}>
                                            <div
                                                className={classNames(
                                                    'flex items-start gap-3 px-4 py-4 transition hover:bg-ink-50/70 sm:px-5',
                                                    unread && 'bg-brand-50/30',
                                                )}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() => open(notification)}
                                                    className="flex min-w-0 flex-1 items-start gap-3 text-left"
                                                >
                                                    <span
                                                        className={classNames(
                                                            'mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl',
                                                            meta.tone === 'rose'
                                                                ? 'bg-rose-50 text-rose-600'
                                                                : meta.tone === 'amber'
                                                                  ? 'bg-amber-50 text-amber-700'
                                                                  : meta.tone === 'brand'
                                                                    ? 'bg-brand-50 text-brand-700'
                                                                    : 'bg-ink-100 text-ink-500',
                                                        )}
                                                    >
                                                        <Icon className="size-5" />
                                                    </span>

                                                    <span className="min-w-0 flex-1">
                                                        <span className="flex flex-wrap items-center gap-2">
                                                            <span className={classNames('text-sm', unread ? 'font-bold text-ink-950' : 'font-semibold text-ink-800')}>
                                                                {notification.title}
                                                            </span>
                                                            {unread && <span className="size-1.5 rounded-full bg-brand-500" aria-label="Unread" />}
                                                            <Badge tone={meta.tone} className="uppercase">
                                                                {meta.label}
                                                            </Badge>
                                                        </span>
                                                        <span className="mt-1 block text-xs leading-5 text-ink-600">{notification.message}</span>
                                                        <span className="mt-1.5 flex flex-wrap items-center gap-2 text-[10px] text-ink-400">
                                                            <span>{relativeTime(notification.created_at)}</span>
                                                            <span aria-hidden="true">·</span>
                                                            <span>{formatDateTime(notification.created_at)}</span>
                                                            {notification.read_at && (
                                                                <>
                                                                    <span aria-hidden="true">·</span>
                                                                    <span className="inline-flex items-center gap-1">
                                                                        <MailOpen className="size-3" />
                                                                        Read
                                                                    </span>
                                                                </>
                                                            )}
                                                        </span>
                                                    </span>
                                                </button>

                                                <span className="ml-1 flex shrink-0 items-center gap-1">
                                                    {unread ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => markRead(notification)}
                                                            className="btn-icon size-8"
                                                            title="Mark as read"
                                                            aria-label={`Mark ${notification.title} as read`}
                                                        >
                                                            <CheckCircle2 className="size-4" />
                                                        </button>
                                                    ) : (
                                                        <ExternalLink className="size-4 text-ink-300" aria-hidden="true" />
                                                    )}
                                                </span>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>

                            <div className="border-t border-ink-100 px-5 py-4">
                                <Pagination paginator={notifications} />
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            icon={Bell}
                            title="You're all caught up"
                            description="Referral updates, bed holds, and availability reminders will appear here."
                            action={
                                canOpenReferrals ? (
                                    <Link href="/referrals" className="btn btn-secondary">
                                        <ClipboardList className="size-4" />
                                        Open referrals
                                    </Link>
                                ) : undefined
                            }
                        />
                    )}
                </Card>

                <p className="text-[11px] leading-5 text-ink-400">
                    Notifications are generated automatically for the two hospitals involved in a referral. They never contain full clinical details; open the referral record
                    for coordination context.
                </p>
            </div>
        </AppLayout>
    );
}
