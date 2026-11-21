import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BedDouble,
    Bell,
    Building2,
    CheckCircle2,
    ChevronDown,
    ClipboardList,
    FileBarChart,
    Info,
    LayoutDashboard,
    LogOut,
    Menu,
    MessageSquare,
    ShieldCheck,
    SlidersHorizontal,
    TriangleAlert,
    Users,
    X,
} from 'lucide-react';
import { Avatar, Badge } from '../components/ui';
import CallOverlay from '../components/CallOverlay';
import { setCallController } from '../lib/callBus';
import { createCallController } from '../lib/call';
import { getActiveConversation } from '../lib/chat';
import { subscribeToChat } from '../lib/supabaseChat';
import { classNames, formatDateTime, relativeTime } from '../lib/utils';

function buildNavigation(user) {
    const can = (permission) => user?.permissions?.includes(permission);
    const hasHospital = user?.has_hospital_membership;

    const groups = [
        {
            label: 'Network',
            items: [
                { label: 'Dashboard', href: '/', section: 'Overview', icon: LayoutDashboard },
                { label: 'Hospital directory', href: '/hospitals', section: 'Network', icon: Building2 },
            ],
        },
        {
            label: 'Communication',
            items: [{ label: 'Messages', href: '/messages', section: 'Communication', icon: MessageSquare }],
        },
    ];

    const operations = [];
    if (can('beds.view')) {
        operations.push({ label: 'Bed board', href: '/beds', section: 'Operations', icon: BedDouble });
    }
    if (can('referrals.view') && hasHospital) {
        operations.push({ label: 'Referrals', href: '/referrals', section: 'Operations', icon: ClipboardList });
    }
    if (can('transfers.view') && hasHospital) {
        operations.push({ label: 'Transfers', href: '/transfers', section: 'Operations', icon: ArrowLeftRight });
    }
    if (can('reports.view') || can('reports.national')) {
        operations.push({ label: 'Reports', href: '/reports', section: 'Insights', icon: FileBarChart });
    }

    if (operations.length) {
        groups.push({ label: 'Operations', items: operations });
    }

    const admin = [];
    if (can('hospitals.manage') || can('hospitals.register')) {
        admin.push({ label: 'Hospitals', href: '/admin/hospitals', section: 'Administration', icon: Building2 });
    }
    if (can('users.manage')) {
        admin.push({ label: 'User accounts', href: '/admin/users', section: 'Administration', icon: Users });
    }
    if (can('audit.view')) {
        admin.push({ label: 'Audit trail', href: '/admin/audit', section: 'Administration', icon: ShieldCheck });
    }
    if (can('settings.manage') && user?.is_network_user) {
        admin.push({ label: 'Platform settings', href: '/admin/settings', section: 'Administration', icon: SlidersHorizontal });
    }

    if (admin.length) {
        groups.push({ label: 'Administration', items: admin });
    }

    return groups;
}

function isActive(url, href) {
    const path = url.split('?')[0];
    if (href === '/') return path === '/';
    return path === href || path.startsWith(`${href}/`);
}

function Toast({ toast, onDismiss }) {
    useEffect(() => {
        const timer = window.setTimeout(() => onDismiss(toast.id), 6500);
        return () => window.clearTimeout(timer);
    }, [toast.id, onDismiss]);

    const styles = {
        success: { box: 'border-emerald-200 bg-white', icon: 'bg-emerald-50 text-emerald-600', Icon: CheckCircle2 },
        error: { box: 'border-rose-200 bg-white', icon: 'bg-rose-50 text-rose-600', Icon: TriangleAlert },
        info: { box: 'border-sky-200 bg-white', icon: 'bg-sky-50 text-sky-600', Icon: Info },
    };
    const style = styles[toast.type] ?? styles.info;

    return (
        <div
            className={classNames(
                'pointer-events-auto flex w-full items-start gap-3 rounded-xl border p-3.5 shadow-[var(--shadow-lift)] animate-fade-up',
                style.box,
            )}
            role="status"
        >
            <span className={classNames('flex size-8 shrink-0 items-center justify-center rounded-lg', style.icon)}>
                <style.Icon className="size-4.5" />
            </span>
            <p className="min-w-0 flex-1 pt-1 text-sm leading-5 font-medium text-ink-800">{toast.message}</p>
            <button type="button" onClick={() => onDismiss(toast.id)} className="btn-icon size-7 shrink-0" aria-label="Dismiss">
                <X className="size-4" />
            </button>
        </div>
    );
}

export default function AppLayout({ children }) {
    const page = usePage();
    const { auth, chat, flash, notifications, settings, realtime, calls } = page.props;
    const user = auth?.user;
    const url = page.url;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [notificationOpen, setNotificationOpen] = useState(false);
    const [userOpen, setUserOpen] = useState(false);
    const [chatUnread, setChatUnread] = useState(chat?.unread_count ?? 0);
    const [toasts, setToasts] = useState([]);
    const [callState, setCallState] = useState({ status: 'idle' });
    const [callMedia, setCallMedia] = useState({ local: null, remote: null });
    const notificationRef = useRef(null);
    const userRef = useRef(null);
    const callControllerRef = useRef(null);

    const navigation = useMemo(() => buildNavigation(user), [user]);
    const flatItems = useMemo(() => navigation.flatMap((group) => group.items), [navigation]);
    const current =
        [...flatItems].sort((a, b) => b.href.length - a.href.length).find((item) => isActive(url, item.href)) ??
        (url.split('?')[0] === '/notifications' ? { section: 'Inbox', label: 'Notifications' } : null);

    useEffect(() => {
        if (!user?.id) return undefined;

        const controller = createCallController({
            userId: user.id,
            iceServers: calls?.ice_servers || [],
            signalUrl: '/calls/signal',
            onState: setCallState,
            onMedia: setCallMedia,
            onError: (message) => setToasts((current) => [
                ...current,
                { id: `call-${Date.now()}`, type: 'error', message },
            ]),
        });

        callControllerRef.current = controller;
        setCallController(controller);

        return () => {
            controller.destroy();
            callControllerRef.current = null;
            setCallController(null);
        };
    }, [user?.id]);

    useEffect(() => {
        setSidebarOpen(false);
        setNotificationOpen(false);
        setUserOpen(false);
    }, [url]);

    useEffect(() => {
        if (!flash) return;

        const incoming = ['success', 'error', 'info']
            .filter((type) => flash[type])
            .map((type) => ({
                id: `${type}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
                type,
                message: flash[type],
            }));

        if (incoming.length) {
            setToasts((currentToasts) => [...currentToasts.slice(-2), ...incoming]);
        }
    }, [flash]);

    useEffect(() => {
        const onClick = (event) => {
            if (notificationRef.current && !notificationRef.current.contains(event.target)) {
                setNotificationOpen(false);
            }
            if (userRef.current && !userRef.current.contains(event.target)) {
                setUserOpen(false);
            }
        };

        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    useEffect(() => {
        setChatUnread(chat?.unread_count ?? 0);
    }, [chat?.unread_count]);

    useEffect(() => {
        const onUnread = (event) => setChatUnread(Math.max(0, Number(event.detail?.count) || 0));

        window.addEventListener('chat:unread', onUnread);
        return () => window.removeEventListener('chat:unread', onUnread);
    }, []);

    useEffect(() => {
        if (!user?.id || !realtime?.topic) {
            return undefined;
        }

        const handleMessage = (event) => {
            window.dispatchEvent(new CustomEvent('chat:message', { detail: event }));

            const isOpen =
                window.location.pathname.startsWith('/messages')
                && getActiveConversation() === Number(event.conversation_id);

            if (event.sender?.id !== user.id && !isOpen) {
                setChatUnread((count) => count + 1);
            }
        };

        const handleRead = (event) => {
            window.dispatchEvent(new CustomEvent('chat:read', { detail: event }));
        };

        const handleMessageUpdated = (event) => {
            window.dispatchEvent(new CustomEvent('chat:message-updated', { detail: event }));
        };

        const handleMessageDeleted = (event) => {
            window.dispatchEvent(new CustomEvent('chat:message-deleted', { detail: event }));

            const count = event.unread_counts?.[user.id];

            if (count !== undefined) {
                setChatUnread(Math.max(0, Number(count) || 0));
            }
        };

        return subscribeToChat({
            ...realtime,
            handlers: {
                'message.sent': handleMessage,
                'conversation.read': handleRead,
                'message.updated': handleMessageUpdated,
                'message.deleted': handleMessageDeleted,
                'call.signal': (payload) => callControllerRef.current?.onSignal(payload),
            },
        });
    }, [user?.id, realtime?.topic]);

    const dismissToast = (id) => setToasts((currentToasts) => currentToasts.filter((toast) => toast.id !== id));

    const markAllRead = () => {
        router.post('/notifications/read-all', {}, { preserveScroll: true });
    };

    const openNotification = (item) => {
        if (!item.read_at) {
            router.post(`/notifications/${item.id}/read`, {}, {
                preserveScroll: true,
                onSuccess: () => router.visit(item.url),
            });
            return;
        }

        router.visit(item.url);
    };

    const logout = () => {
        router.post('/logout');
    };

    const sidebar = (
        <div className="flex h-full flex-col bg-ink-950 text-white">
            <div className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-white/10 px-5">
                <Link href="/" className="flex min-w-0 items-center gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white ring-1 ring-white/20">
                        <img
                            src="/images/bukidnon-seal.jpg"
                            alt="Province of Bukidnon official seal"
                            className="size-10 object-cover"
                        />
                    </span>
                    <span className="min-w-0">
                        <span className="block truncate font-display text-sm font-extrabold tracking-tight">BHN</span>
                        <span className="block truncate text-[10px] font-medium tracking-wide text-ink-400 uppercase">
                            Care coordination
                        </span>
                    </span>
                </Link>
                <button
                    type="button"
                    onClick={() => setSidebarOpen(false)}
                    className="btn-icon text-ink-400 hover:bg-white/10 hover:text-white lg:hidden"
                    aria-label="Close navigation"
                >
                    <X className="size-5" />
                </button>
            </div>

            <div className="border-b border-white/10 px-4 py-4">
                <div className="rounded-xl bg-white/[0.06] p-3 ring-1 ring-white/10">
                    <p className="text-[10px] font-semibold tracking-[0.14em] text-brand-300/90 uppercase">Your scope</p>
                    <p className="mt-1 truncate text-sm font-semibold text-white">{user?.scope_label}</p>
                    <p className="mt-0.5 truncate text-[11px] text-ink-400">{user?.roles?.join(' · ') || 'No role assigned'}</p>
                </div>
            </div>

            <nav className="no-scrollbar flex-1 overflow-y-auto px-3 py-4" aria-label="Primary">
                {navigation.map((group) => (
                    <div key={group.label} className="mb-6 last:mb-0">
                        <p className="mb-2 px-3 text-[10px] font-semibold tracking-[0.16em] text-ink-500 uppercase group-first:mt-0">
                            {group.label}
                        </p>
                        <ul className="space-y-1">
                            {group.items.map((item) => {
                                const active = isActive(url, item.href);

                                return (
                                    <li key={item.href}>
                                        <Link
                                            href={item.href}
                                            className={classNames('nav-link', active && 'nav-link-active')}
                                            aria-current={active ? 'page' : undefined}
                                        >
                                            <item.icon className={classNames('size-4.5 shrink-0', active ? 'text-brand-300' : 'text-ink-500')} />
                                            <span className="truncate">{item.label}</span>
                                            {item.href === '/messages' && chatUnread > 0 && (
                                                <span className="ml-auto flex min-w-5 items-center justify-center rounded-full bg-brand-500 px-1.5 py-0.5 text-[10px] font-bold text-white">
                                                    {chatUnread > 99 ? '99+' : chatUnread}
                                                </span>
                                            )}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>

            <div className="shrink-0 border-t border-white/10 p-3">
                <div className="flex items-center gap-3 rounded-xl px-2 py-2">
                    <Avatar name={user?.name} size="md" className="bg-brand-600" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-white">{user?.name}</p>
                        <p className="truncate text-[11px] text-ink-400">{user?.email}</p>
                    </div>
                    <button
                        type="button"
                        onClick={logout}
                        className="btn-icon text-ink-400 hover:bg-white/10 hover:text-white"
                        title="Sign out"
                        aria-label="Sign out"
                    >
                        <LogOut className="size-4" />
                    </button>
                </div>
                <p className="px-2 pt-2 text-[10px] leading-4 text-ink-500">
                    Availability is manually reported. Confirm every referral with the receiving hospital.
                </p>
            </div>
        </div>
    );

    return (
        <div className="min-h-screen bg-ink-50">
            <aside className="fixed inset-y-0 left-0 z-50 hidden w-72 lg:block">{sidebar}</aside>

            {sidebarOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div
                        className="absolute inset-0 bg-ink-950/50 backdrop-blur-[2px]"
                        onClick={() => setSidebarOpen(false)}
                        aria-hidden="true"
                    />
                    <aside className="absolute inset-y-0 left-0 w-[min(18rem,85vw)] animate-fade-in">{sidebar}</aside>
                </div>
            )}

            <div className="lg:pl-72">
                <header className="sticky top-0 z-40 border-b border-ink-100 bg-white/90 backdrop-blur">
                    <div className="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(true)}
                            className="btn-icon lg:hidden"
                            aria-label="Open navigation"
                        >
                            <Menu className="size-5" />
                        </button>

                        <div className="min-w-0 flex-1">
                            <p className="truncate text-[10px] font-semibold tracking-[0.14em] text-ink-500 uppercase">
                                {current?.section ?? 'Bukidnon Hospital Network'}
                            </p>
                            <p className="truncate font-display text-sm font-bold text-ink-900">
                                {current?.label ?? 'Network overview'}
                            </p>
                        </div>

                        <div className="flex items-center gap-1 sm:gap-2">
                            <Badge tone={user?.is_network_user ? 'brand' : 'teal'} className="hidden sm:inline-flex">
                                {user?.is_network_user ? 'Network scope' : 'Hospital scope'}
                            </Badge>

                            <Link
                                href="/messages"
                                className="btn-icon relative"
                                aria-label={`Messages${chatUnread > 0 ? `, ${chatUnread} unread` : ''}`}
                            >
                                <MessageSquare className="size-5" />
                                {chatUnread > 0 && (
                                    <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-brand-600 px-1 text-[9px] font-bold text-white">
                                        {chatUnread > 9 ? '9+' : chatUnread}
                                    </span>
                                )}
                            </Link>

                            <div className="relative" ref={notificationRef}>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setNotificationOpen((open) => !open);
                                        setUserOpen(false);
                                    }}
                                    className="btn-icon relative"
                                    aria-label="Notifications"
                                    aria-expanded={notificationOpen}
                                >
                                    <Bell className="size-5" />
                                    {notifications?.unread_count > 0 && (
                                        <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-bold text-white">
                                            {notifications.unread_count > 9 ? '9+' : notifications.unread_count}
                                        </span>
                                    )}
                                </button>

                                {notificationOpen && (
                                    <div className="absolute right-0 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-[var(--shadow-lift)] animate-fade-up">
                                        <div className="flex items-center justify-between border-b border-ink-100 px-4 py-3">
                                            <div>
                                                <p className="text-sm font-bold text-ink-900">Notifications</p>
                                                <p className="text-[11px] text-ink-500">
                                                    {notifications?.unread_count || 0} unread
                                                </p>
                                            </div>
                                            {notifications?.unread_count > 0 && (
                                                <button type="button" onClick={markAllRead} className="text-[11px] font-semibold text-brand-700 hover:underline">
                                                    Mark all read
                                                </button>
                                            )}
                                        </div>
                                        <div className="max-h-80 overflow-y-auto">
                                            {notifications?.items?.length ? (
                                                notifications.items.map((item) => (
                                                    <button
                                                        key={item.id}
                                                        type="button"
                                                        onClick={() => openNotification(item)}
                                                        className={classNames(
                                                            'flex w-full gap-3 border-b border-ink-50 px-4 py-3 text-left transition last:border-0 hover:bg-ink-50',
                                                            !item.read_at && 'bg-brand-50/40',
                                                        )}
                                                    >
                                                        <span
                                                            className={classNames(
                                                                'mt-1.5 size-2 shrink-0 rounded-full',
                                                                item.read_at ? 'bg-ink-200' : 'bg-brand-500',
                                                            )}
                                                        />
                                                        <span className="min-w-0">
                                                            <span className="block truncate text-xs font-semibold text-ink-900">{item.title}</span>
                                                            <span className="mt-0.5 line-clamp-2 block text-[11px] leading-4 text-ink-500">
                                                                {item.message}
                                                            </span>
                                                            <span className="mt-1 block text-[10px] text-ink-400">
                                                                {relativeTime(item.created_at)}
                                                            </span>
                                                        </span>
                                                    </button>
                                                ))
                                            ) : (
                                                <p className="px-4 py-8 text-center text-xs text-ink-500">No notifications yet.</p>
                                            )}
                                        </div>
                                        <Link
                                            href="/notifications"
                                            className="block border-t border-ink-100 bg-ink-50/60 px-4 py-2.5 text-center text-xs font-semibold text-brand-700 hover:bg-ink-100"
                                        >
                                            View all notifications
                                        </Link>
                                    </div>
                                )}
                            </div>

                            <div className="relative" ref={userRef}>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setUserOpen((open) => !open);
                                        setNotificationOpen(false);
                                    }}
                                    className="flex items-center gap-2 rounded-xl py-1 pr-1 pl-1 transition hover:bg-ink-100 sm:pr-2"
                                    aria-label="Account menu"
                                    aria-expanded={userOpen}
                                >
                                    <Avatar name={user?.name} size="md" />
                                    <span className="hidden min-w-0 text-left sm:block">
                                        <span className="block max-w-36 truncate text-xs font-semibold text-ink-900">{user?.name}</span>
                                        <span className="block max-w-36 truncate text-[10px] text-ink-500">{user?.scope_label}</span>
                                    </span>
                                    <ChevronDown className="hidden size-4 text-ink-400 sm:block" />
                                </button>

                                {userOpen && (
                                    <div className="absolute right-0 mt-2 w-64 overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-[var(--shadow-lift)] animate-fade-up">
                                        <div className="border-b border-ink-100 bg-ink-50/60 px-4 py-3">
                                            <p className="truncate text-sm font-bold text-ink-900">{user?.name}</p>
                                            <p className="truncate text-[11px] text-ink-500">{user?.email}</p>
                                            <p className="mt-1.5 text-[10px] font-medium text-brand-700">{user?.roles?.join(' · ')}</p>
                                        </div>
                                        <div className="p-2">
                                            <Link href="/notifications" className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-xs font-medium text-ink-700 hover:bg-ink-50">
                                                <Bell className="size-4 text-ink-400" />
                                                Notifications
                                            </Link>
                                            {user?.last_login_at && (
                                                <p className="px-3 py-2 text-[10px] leading-4 text-ink-400">
                                                    Last sign-in {formatDateTime(user.last_login_at)}
                                                </p>
                                            )}
                                            <button
                                                type="button"
                                                onClick={logout}
                                                className="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50"
                                            >
                                                <LogOut className="size-4" />
                                                Sign out
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 sm:py-8 lg:px-8">{children}</main>

                <footer className="mx-auto w-full max-w-[1500px] px-4 pb-8 sm:px-6 lg:px-8">
                    <div className="flex flex-col gap-2 border-t border-ink-100 pt-5 text-[11px] text-ink-400 sm:flex-row sm:items-center sm:justify-between">
                        <p>{settings?.network_name ?? 'Bukidnon Hospital Network'} · BHN demo environment</p>
                        <p>
                            Support: <a href={`mailto:${settings?.support_email ?? 'support@bhn.test'}`} className="font-medium text-ink-500 hover:text-brand-700">{settings?.support_email ?? 'support@bhn.test'}</a>
                        </p>
                    </div>
                </footer>
            </div>

            <div className="pointer-events-none fixed top-20 right-4 z-[100] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2 sm:right-6" aria-live="polite">
                {toasts.map((toast) => (
                    <Toast key={toast.id} toast={toast} onDismiss={dismissToast} />
                ))}
            </div>

            <CallOverlay
                call={callState}
                media={callMedia}
                onAccept={(video) => callControllerRef.current?.acceptCall(video)}
                onDecline={() => callControllerRef.current?.declineCall()}
                onHangup={() => callControllerRef.current?.hangup()}
                onToggleMute={() => callControllerRef.current?.toggleMute()}
                onToggleCamera={() => callControllerRef.current?.toggleCamera()}
            />
        </div>
    );
}
