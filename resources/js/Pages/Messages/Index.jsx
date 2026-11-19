import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    ArrowLeft,
    Ban,
    Check,
    CheckCheck,
    Loader2,
    MessageSquare,
    Pencil,
    Plus,
    Send,
    ShieldAlert,
    Trash2,
    X,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { Avatar, Badge, Button, Card, EmptyState, InfoCallout, Modal, PageHeader, SearchInput } from '../../components/ui';
import { setActiveConversation, dispatchChatUnread } from '../../lib/chat';
import { classNames, formatDate, formatTime, relativeTime } from '../../lib/utils';

const MANILA = 'Asia/Manila';

const dayFormatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: MANILA,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

function dayKey(value) {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : dayFormatter.format(date);
}

function dayLabel(value) {
    const key = dayKey(value);
    const now = new Date();
    const today = dayFormatter.format(now);
    const yesterday = dayFormatter.format(new Date(now.getTime() - 86_400_000));

    if (key === today) return 'Today';
    if (key === yesterday) return 'Yesterday';

    return formatDate(value);
}

function sortThreads(list) {
    return [...list].sort((a, b) => {
        const left = new Date(b.last_message_at ?? b.created_at ?? 0).getTime();
        const right = new Date(a.last_message_at ?? a.created_at ?? 0).getTime();

        return left - right || b.id - a.id;
    });
}

function errorMessage(error) {
    const data = error?.response?.data;

    return (
        data?.message
        || Object.values(data?.errors ?? {})?.[0]?.[0]
        || 'Something went wrong. Please try again.'
    );
}

function matchesContact(contact, needle) {
    if (!needle) return true;

    return [contact.name, contact.email, contact.role, contact.hospital, contact.hospital_name]
        .filter(Boolean)
        .some((value) => value.toLowerCase().includes(needle));
}

function MessageBubble({
    message,
    mine,
    read,
    showStatus,
    onEdit,
    onDelete,
    editing = false,
    editDraft = '',
    onEditDraftChange,
    onSaveEdit,
    onCancelEdit,
    saving = false,
}) {
    if (editing) {
        const handleKeyDown = (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                onSaveEdit?.(event);
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                onCancelEdit?.();
            }
        };

        return (
            <div className={classNames('flex w-full', mine ? 'justify-end' : 'justify-start')}>
                <form
                    onSubmit={onSaveEdit}
                    className="w-full max-w-[min(34rem,85%)] rounded-2xl border border-brand-200 bg-white p-2.5 shadow-sm"
                >
                    <textarea
                        autoFocus
                        value={editDraft}
                        onChange={(event) => onEditDraftChange?.(event.target.value.slice(0, 2000))}
                        onKeyDown={handleKeyDown}
                        rows={2}
                        maxLength={2000}
                        aria-label="Edit message"
                        className="input max-h-40 min-h-16 resize-none text-sm"
                    />
                    <div className="mt-2 flex items-center justify-between gap-2">
                        <span className="text-[10px] text-ink-400">{editDraft.length}/2000 · Esc to cancel</span>
                        <div className="flex items-center gap-1.5">
                            <Button type="button" variant="secondary" size="sm" onClick={onCancelEdit} disabled={saving}>
                                Cancel
                            </Button>
                            <Button type="submit" size="sm" disabled={!editDraft.trim() || saving}>
                                {saving ? <Loader2 className="size-3.5 animate-spin" /> : <Check className="size-3.5" />}
                                Save
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        );
    }

    const deleted = Boolean(message.deleted);
    const canManage = mine && !deleted;

    return (
        <div className={classNames('group flex w-full', mine ? 'justify-end' : 'justify-start')}>
            <div
                className={classNames(
                    'max-w-[min(34rem,85%)] rounded-2xl px-3.5 py-2.5 text-sm leading-6 shadow-sm',
                    mine ? 'rounded-br-md' : 'rounded-bl-md',
                    deleted
                        ? mine
                            ? 'bg-brand-700/60 text-brand-50'
                            : 'bg-ink-50 text-ink-500 ring-1 ring-ink-100'
                        : mine
                          ? 'bg-brand-700 text-white'
                          : 'bg-white text-ink-800 ring-1 ring-ink-100',
                )}
            >
                {deleted ? (
                    <p className="flex items-center gap-1.5 text-xs italic">
                        <Ban className="size-3.5 shrink-0" aria-hidden="true" />
                        This message was deleted
                    </p>
                ) : (
                    <p className="whitespace-pre-wrap break-words">{message.body}</p>
                )}
                <div
                    className={classNames(
                        'mt-1 flex items-center justify-end gap-1.5 text-[10px]',
                        mine ? 'text-brand-100/80' : 'text-ink-400',
                    )}
                >
                    {canManage && (
                        <span className="mr-0.5 flex items-center gap-0.5 opacity-70 transition group-hover:opacity-100 focus-within:opacity-100">
                            <button
                                type="button"
                                onClick={() => onEdit?.(message)}
                                className={classNames(
                                    'rounded p-1 transition',
                                    mine ? 'hover:bg-white/15' : 'hover:bg-ink-100',
                                )}
                                aria-label="Edit message"
                                title="Edit message"
                            >
                                <Pencil className="size-3" />
                            </button>
                            <button
                                type="button"
                                onClick={() => onDelete?.(message)}
                                className={classNames(
                                    'rounded p-1 transition',
                                    mine ? 'hover:bg-white/15' : 'hover:bg-rose-50 hover:text-rose-600',
                                )}
                                aria-label="Delete message"
                                title="Delete message"
                            >
                                <Trash2 className="size-3" />
                            </button>
                        </span>
                    )}
                    <span>{formatTime(message.created_at)}</span>
                    {message.edited_at && !deleted && (
                        <>
                            <span aria-hidden="true">·</span>
                            <span title={`Edited ${formatTime(message.edited_at)}`}>edited</span>
                        </>
                    )}
                    {showStatus && !deleted && (
                        <>
                            <span aria-hidden="true">·</span>
                            <span className="inline-flex items-center gap-1">
                                {read && <CheckCheck className="size-3" />}
                                {read ? 'Read' : 'Sent'}
                            </span>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function MessagesIndex({
    conversations = [],
    contacts = [],
    activeConversation = null,
    messages = [],
    unreadCount = 0,
}) {
    const { auth } = usePage().props;
    const currentUser = auth?.user;

    const [threads, setThreads] = useState(() => sortThreads(conversations));
    const [activeId, setActiveId] = useState(activeConversation?.id ?? null);
    const [active, setActive] = useState(activeConversation);
    const [threadMessages, setThreadMessages] = useState(messages ?? []);
    const [search, setSearch] = useState('');
    const [pickerOpen, setPickerOpen] = useState(false);
    const [mobileThread, setMobileThread] = useState(Boolean(activeConversation));
    const [draft, setDraft] = useState('');
    const [loading, setLoading] = useState(false);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState(null);
    const [editingId, setEditingId] = useState(null);
    const [editDraft, setEditDraft] = useState('');
    const [savingEdit, setSavingEdit] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const activeIdRef = useRef(activeId);
    const scrollRef = useRef(null);
    const composerRef = useRef(null);

    useEffect(() => {
        setThreads(sortThreads(conversations));
    }, [conversations]);

    useEffect(() => {
        activeIdRef.current = activeId;
        setActiveConversation(activeId);
    }, [activeId]);

    useEffect(() => () => setActiveConversation(null), []);

    const unreadTotal = useMemo(
        () => threads.reduce((total, thread) => total + (thread.unread_count || 0), 0),
        [threads],
    );

    useEffect(() => {
        if (unreadTotal !== unreadCount) {
            dispatchChatUnread(unreadTotal);
        }
    }, [unreadTotal, unreadCount]);

    const scrollToBottom = () => {
        const element = scrollRef.current;

        if (element) {
            element.scrollTop = element.scrollHeight;
        }
    };

    const replaceThreadMessage = (message) => {
        setThreadMessages((list) =>
            list.map((item) => (item.id === message.id ? { ...item, ...message } : item)),
        );
    };

    const syncConversation = (conversation) => {
        if (!conversation?.id) return;

        setThreads((list) => sortThreads(list.map((thread) => (thread.id === conversation.id ? conversation : thread))));
        setActive((current) => (current?.id === conversation.id ? conversation : current));
    };

    const applyUpdatedMessage = (detail) => {
        const message = detail?.message;

        if (!message?.id || !message?.conversation_id) return;

        replaceThreadMessage(message);
        setThreads((list) =>
            sortThreads(
                list.map((thread) =>
                    thread.id === message.conversation_id
                        ? {
                              ...thread,
                              last_message: detail.last_message ?? thread.last_message,
                              last_message_at: detail.last_message_at ?? thread.last_message_at,
                          }
                        : thread,
                ),
            ),
        );
    };

    const applyDeletedMessage = (detail) => {
        const message = detail?.message;

        if (!message?.id || !message?.conversation_id) return;

        replaceThreadMessage(message);
        setThreads((list) =>
            sortThreads(
                list.map((thread) =>
                    thread.id === message.conversation_id
                        ? {
                              ...thread,
                              last_message: detail.last_message ?? thread.last_message,
                              last_message_at: detail.last_message_at ?? thread.last_message_at,
                              unread_count:
                                  detail.conversation_unread_counts?.[currentUser?.id] ?? thread.unread_count,
                          }
                        : thread,
                ),
            ),
        );
    };

    const startEditing = (message) => {
        if (!message || message.deleted) return;

        setEditingId(message.id);
        setEditDraft(message.body ?? '');
        setDeleteTarget(null);
        setError(null);
    };

    const cancelEditing = () => {
        setEditingId(null);
        setEditDraft('');
    };

    const saveEdit = async (event) => {
        event?.preventDefault();

        const body = editDraft.trim();

        if (!activeId || !editingId || !body || savingEdit) return;

        setSavingEdit(true);
        setError(null);

        try {
            const { data } = await axios.patch(`/messages/${activeId}/messages/${editingId}`, { body });

            replaceThreadMessage(data.message);
            syncConversation(data.conversation);
            cancelEditing();
        } catch (requestError) {
            setError(errorMessage(requestError));
        } finally {
            setSavingEdit(false);
        }
    };

    const confirmDelete = async () => {
        if (!activeId || !deleteTarget || deleting) return;

        setDeleting(true);
        setError(null);

        try {
            const { data } = await axios.delete(`/messages/${activeId}/messages/${deleteTarget.id}`);

            replaceThreadMessage(data.message);
            syncConversation(data.conversation);
            dispatchChatUnread(data.unread_count);

            if (editingId === deleteTarget.id) {
                cancelEditing();
            }

            setDeleteTarget(null);
        } catch (requestError) {
            setError(errorMessage(requestError));
        } finally {
            setDeleting(false);
        }
    };

    useEffect(() => {
        scrollToBottom();
    }, [threadMessages.length, activeId, loading]);

    useEffect(() => {
        const handleMessage = (event) => {
            const detail = event.detail;

            if (!detail?.conversation_id || !detail?.id) return;

            const senderId = detail.sender?.id;
            const mine = senderId === currentUser?.id;
            const other = mine ? detail.recipient : detail.sender;
            const isOpen = activeIdRef.current === detail.conversation_id;
            const lastMessage = {
                id: detail.id,
                conversation_id: detail.conversation_id,
                sender_id: senderId,
                body: detail.body,
                created_at: detail.created_at,
            };

            setThreads((list) => {
                const existing = list.find((thread) => thread.id === detail.conversation_id);

                if (!existing) {
                    return sortThreads([
                        {
                            id: detail.conversation_id,
                            other_user: other,
                            other_last_read_message_id: 0,
                            last_message: lastMessage,
                            last_message_at: detail.created_at,
                            unread_count: mine || isOpen ? 0 : 1,
                            created_at: detail.created_at,
                        },
                        ...list,
                    ]);
                }

                return sortThreads(
                    list.map((thread) =>
                        thread.id === detail.conversation_id
                            ? {
                                  ...thread,
                                  last_message: lastMessage,
                                  last_message_at: detail.created_at,
                                  unread_count: mine || isOpen ? 0 : (thread.unread_count || 0) + 1,
                              }
                            : thread,
                    ),
                );
            });

            if (isOpen) {
                setThreadMessages((list) =>
                    list.some((message) => message.id === detail.id) ? list : [...list, lastMessage],
                );

                if (!mine) {
                    axios
                        .post(`/messages/${detail.conversation_id}/read`)
                        .then(({ data }) => dispatchChatUnread(data.unread_count))
                        .catch(() => {});
                }
            }
        };

        const handleRead = (event) => {
            const detail = event.detail;

            if (!detail?.conversation_id || detail.reader_id === currentUser?.id) return;

            setThreads((list) =>
                list.map((thread) =>
                    thread.id === detail.conversation_id
                        ? {
                              ...thread,
                              other_last_read_message_id: Math.max(
                                  thread.other_last_read_message_id || 0,
                                  detail.last_read_message_id,
                              ),
                          }
                        : thread,
                ),
            );
            setActive((conversation) =>
                conversation && conversation.id === detail.conversation_id
                    ? {
                          ...conversation,
                          other_last_read_message_id: Math.max(
                              conversation.other_last_read_message_id || 0,
                              detail.last_read_message_id,
                          ),
                      }
                    : conversation,
            );
        };

        const handleUpdated = (event) => applyUpdatedMessage(event.detail);
        const handleDeleted = (event) => applyDeletedMessage(event.detail);

        window.addEventListener('chat:message', handleMessage);
        window.addEventListener('chat:read', handleRead);
        window.addEventListener('chat:message-updated', handleUpdated);
        window.addEventListener('chat:message-deleted', handleDeleted);

        return () => {
            window.removeEventListener('chat:message', handleMessage);
            window.removeEventListener('chat:read', handleRead);
            window.removeEventListener('chat:message-updated', handleUpdated);
            window.removeEventListener('chat:message-deleted', handleDeleted);
        };
    }, [currentUser?.id]);

    const applyConversation = (conversation, threadMessagesPayload) => {
        setThreads((list) => {
            const exists = list.some((thread) => thread.id === conversation.id);

            return sortThreads(
                exists
                    ? list.map((thread) => (thread.id === conversation.id ? conversation : thread))
                    : [conversation, ...list],
            );
        });
        setActiveId(conversation.id);
        setActive(conversation);
        setThreadMessages(threadMessagesPayload ?? []);
        setMobileThread(true);
        setPickerOpen(false);
        setSearch('');
        setError(null);
    };

    const openConversation = async (conversation) => {
        if (!conversation?.id) return;

        setActiveId(conversation.id);
        setActive(conversation);
        setThreadMessages([]);
        setMobileThread(true);
        setPickerOpen(false);
        setError(null);
        setLoading(true);

        try {
            const { data } = await axios.get(`/messages/${conversation.id}`);
            applyConversation(data.conversation, data.messages);
            window.setTimeout(scrollToBottom, 0);
        } catch (requestError) {
            setError(errorMessage(requestError));
        } finally {
            setLoading(false);
            window.setTimeout(() => composerRef.current?.focus(), 0);
        }
    };

    const startConversation = async (contact) => {
        setLoading(true);
        setError(null);

        try {
            const { data } = await axios.post('/messages', { user_id: contact.id });
            applyConversation(data.conversation, data.messages);
            window.setTimeout(() => {
                scrollToBottom();
                composerRef.current?.focus();
            }, 0);
        } catch (requestError) {
            setError(errorMessage(requestError));
        } finally {
            setLoading(false);
        }
    };

    const sendMessage = async (event) => {
        event?.preventDefault();

        const body = draft.trim();

        if (!activeId || !body || sending) return;

        setSending(true);
        setError(null);

        try {
            const { data } = await axios.post(`/messages/${activeId}/send`, { body });

            setThreadMessages((list) =>
                list.some((message) => message.id === data.message.id) ? list : [...list, data.message],
            );
            setThreads((list) => sortThreads(list.map((thread) => (thread.id === activeId ? data.conversation : thread))));
            setActive(data.conversation);
            setDraft('');
            window.setTimeout(() => {
                scrollToBottom();
                composerRef.current?.focus();
            }, 0);
        } catch (requestError) {
            setError(errorMessage(requestError));
        } finally {
            setSending(false);
        }
    };

    const handleComposerKeyDown = (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    };

    const needle = search.trim().toLowerCase();
    const existingContactIds = useMemo(
        () => new Set(threads.map((thread) => thread.other_user?.id).filter(Boolean)),
        [threads],
    );
    const filteredThreads = threads.filter((thread) => matchesContact(thread.other_user ?? {}, needle));
    const filteredContacts = contacts.filter(
        (contact) => !existingContactIds.has(contact.id) && matchesContact(contact, needle),
    );
    const activeOther = active?.other_user;
    const lastOwnMessageId = [...threadMessages].reverse().find((message) => message.sender_id === currentUser?.id)?.id;
    const otherLastRead = active?.other_last_read_message_id || 0;

    return (
        <AppLayout>
            <Head title="Messages" />

            <div className="space-y-5">
                <PageHeader
                    eyebrow="Communication"
                    title="Messages"
                    description="Real-time direct messages with any active BHN user. Conversations are private to the two participants."
                    actions={
                        <Badge tone={unreadTotal ? 'brand' : 'slate'} dot={unreadTotal > 0}>
                            {unreadTotal} unread
                        </Badge>
                    }
                />

                <InfoCallout tone="amber" title="Protect patient privacy" icon={ShieldAlert}>
                    Do not share patient names, medical record numbers, contact details, or other identifiable information in chat.
                    Use referral records and approved documents for clinical coordination.
                </InfoCallout>

                {error && (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-medium text-rose-700">
                        {error}
                    </div>
                )}

                <Card className="flex h-[calc(100dvh-22rem)] min-h-[34rem] flex-col overflow-hidden">
                    <div className="flex min-h-0 flex-1">
                        <aside
                            className={classNames(
                                'w-full shrink-0 flex-col border-r border-ink-100 lg:flex lg:w-80 xl:w-88',
                                mobileThread && active ? 'hidden lg:flex' : 'flex',
                            )}
                        >
                            <div className="flex items-center justify-between gap-2 border-b border-ink-100 px-4 py-3">
                                <div>
                                    <p className="text-sm font-bold text-ink-900">Conversations</p>
                                    <p className="text-[11px] text-ink-500">
                                        {threads.length} {threads.length === 1 ? 'chat' : 'chats'} · {unreadTotal} unread
                                    </p>
                                </div>
                                <Button
                                    variant={pickerOpen ? 'secondary' : 'primary'}
                                    size="sm"
                                    onClick={() => {
                                        setPickerOpen((open) => !open);
                                        setError(null);
                                    }}
                                >
                                    {pickerOpen ? <X className="size-4" /> : <Plus className="size-4" />}
                                    {pickerOpen ? 'Close' : 'New'}
                                </Button>
                            </div>

                            <div className="border-b border-ink-100 px-3 py-2.5">
                                <SearchInput
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder={pickerOpen ? 'Search active users…' : 'Search conversations…'}
                                />
                            </div>

                            {pickerOpen ? (
                                <div className="min-h-0 flex-1 overflow-y-auto">
                                    <p className="px-4 pt-3 pb-1 text-[10px] font-semibold tracking-[0.14em] text-ink-500 uppercase">
                                        Start a conversation
                                    </p>
                                    {filteredContacts.length ? (
                                        <ul className="divide-y divide-ink-50">
                                            {filteredContacts.map((contact) => (
                                                <li key={contact.id}>
                                                    <button
                                                        type="button"
                                                        onClick={() => startConversation(contact)}
                                                        disabled={loading}
                                                        className="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-ink-50 disabled:opacity-60"
                                                    >
                                                        <Avatar name={contact.name} size="md" />
                                                        <span className="min-w-0 flex-1">
                                                            <span className="block truncate text-sm font-semibold text-ink-900">
                                                                {contact.name}
                                                            </span>
                                                            <span className="block truncate text-[11px] text-ink-500">
                                                                {[contact.role, contact.hospital].filter(Boolean).join(' · ') || 'BHN user'}
                                                            </span>
                                                            <span className="block truncate text-[10px] text-ink-400">{contact.email}</span>
                                                        </span>
                                                        <MessageSquare className="size-4 shrink-0 text-ink-300" />
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <EmptyState
                                            icon={MessageSquare}
                                            title="No matching users"
                                            description="Only active BHN accounts are available for new conversations."
                                            className="py-10"
                                        />
                                    )}
                                </div>
                            ) : filteredThreads.length ? (
                                <ul className="min-h-0 flex-1 divide-y divide-ink-50 overflow-y-auto">
                                    {filteredThreads.map((thread) => {
                                        const unread = thread.unread_count || 0;
                                        const selected = thread.id === activeId;
                                        const previewPrefix = thread.last_message?.sender_id === currentUser?.id ? 'You: ' : '';
                                        const preview = thread.last_message
                                            ? thread.last_message.deleted
                                                ? `${previewPrefix}Message deleted`
                                                : `${previewPrefix}${thread.last_message.body}`
                                            : 'No messages yet';
                                        const previewIsDeleted = Boolean(thread.last_message?.deleted);

                                        return (
                                            <li key={thread.id}>
                                                <button
                                                    type="button"
                                                    onClick={() => openConversation(thread)}
                                                    className={classNames(
                                                        'flex w-full gap-3 px-4 py-3 text-left transition',
                                                        selected ? 'bg-brand-50/70' : 'hover:bg-ink-50',
                                                    )}
                                                >
                                                    <Avatar name={thread.other_user?.name} size="md" />
                                                    <span className="min-w-0 flex-1">
                                                        <span className="flex items-baseline justify-between gap-2">
                                                            <span
                                                                className={classNames(
                                                                    'truncate text-sm',
                                                                    unread ? 'font-bold text-ink-900' : 'font-semibold text-ink-800',
                                                                )}
                                                            >
                                                                {thread.other_user?.name ?? 'Unknown user'}
                                                            </span>
                                                            <span className="shrink-0 text-[10px] text-ink-400">
                                                                {thread.last_message_at ? relativeTime(thread.last_message_at) : ''}
                                                            </span>
                                                        </span>
                                                        <span className="block truncate text-[10px] text-ink-400">
                                                            {[thread.other_user?.role, thread.other_user?.hospital]
                                                                .filter(Boolean)
                                                                .join(' · ') || 'BHN user'}
                                                        </span>
                                                        <span className="mt-0.5 flex items-center justify-between gap-2">
                                                            <span
                                                                className={classNames(
                                                                    'truncate text-xs',
                                                                    unread ? 'font-semibold text-ink-800' : 'text-ink-500',
                                                                    previewIsDeleted && 'italic',
                                                                )}
                                                            >
                                                                {preview}
                                                            </span>
                                                            {unread > 0 && (
                                                                <span className="flex min-w-5 shrink-0 items-center justify-center rounded-full bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold text-white">
                                                                    {unread > 99 ? '99+' : unread}
                                                                </span>
                                                            )}
                                                        </span>
                                                    </span>
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            ) : (
                                <EmptyState
                                    icon={MessageSquare}
                                    title={needle ? 'No matching conversations' : 'No conversations yet'}
                                    description={
                                        needle
                                            ? 'Try a different name, role, or hospital.'
                                            : 'Start a direct message with any active user across the network.'
                                    }
                                    action={
                                        !needle ? (
                                            <Button size="sm" onClick={() => setPickerOpen(true)}>
                                                <Plus className="size-4" />
                                                New message
                                            </Button>
                                        ) : null
                                    }
                                    className="py-10"
                                />
                            )}
                        </aside>

                        <section
                            className={classNames(
                                'min-w-0 flex-1 flex-col bg-ink-50/40 lg:flex',
                                mobileThread && active ? 'flex' : 'hidden lg:flex',
                            )}
                        >
                            {active ? (
                                <>
                                    <div className="flex h-16 shrink-0 items-center gap-3 border-b border-ink-100 bg-white px-3 sm:px-4">
                                        <button
                                            type="button"
                                            onClick={() => setMobileThread(false)}
                                            className="btn-icon lg:hidden"
                                            aria-label="Back to conversations"
                                        >
                                            <ArrowLeft className="size-5" />
                                        </button>
                                        <Avatar name={activeOther?.name} size="md" />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-bold text-ink-900">
                                                {activeOther?.name ?? 'Conversation'}
                                            </p>
                                            <p className="truncate text-[11px] text-ink-500">
                                                {[activeOther?.role, activeOther?.hospital, activeOther?.email]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </p>
                                        </div>
                                        <Badge tone="slate" className="hidden sm:inline-flex">
                                            Direct message
                                        </Badge>
                                    </div>

                                    <div
                                        ref={scrollRef}
                                        className="min-h-0 flex-1 space-y-3 overflow-y-auto px-3 py-4 sm:px-5"
                                        aria-live="polite"
                                    >
                                        {loading ? (
                                            <div className="flex h-full items-center justify-center gap-2 text-xs text-ink-500">
                                                <Loader2 className="size-4 animate-spin" />
                                                Loading conversation…
                                            </div>
                                        ) : threadMessages.length ? (
                                            threadMessages.map((message, index) => {
                                                const previous = threadMessages[index - 1];
                                                const showDay = !previous || dayKey(previous.created_at) !== dayKey(message.created_at);
                                                const mine = message.sender_id === currentUser?.id;

                                                return (
                                                    <React.Fragment key={message.id}>
                                                        {showDay && (
                                                            <div className="flex items-center gap-3 py-1">
                                                                <span className="h-px flex-1 bg-ink-100" />
                                                                <span className="rounded-full bg-white px-2.5 py-0.5 text-[10px] font-semibold text-ink-500 ring-1 ring-ink-100">
                                                                    {dayLabel(message.created_at)}
                                                                </span>
                                                                <span className="h-px flex-1 bg-ink-100" />
                                                            </div>
                                                        )}
                                                        <MessageBubble
                                                            message={message}
                                                            mine={mine}
                                                            read={otherLastRead >= message.id}
                                                            showStatus={mine && message.id === lastOwnMessageId}
                                                            onEdit={startEditing}
                                                            onDelete={setDeleteTarget}
                                                            editing={editingId === message.id && !message.deleted}
                                                            editDraft={editDraft}
                                                            onEditDraftChange={setEditDraft}
                                                            onSaveEdit={saveEdit}
                                                            onCancelEdit={cancelEditing}
                                                            saving={savingEdit}
                                                        />
                                                    </React.Fragment>
                                                );
                                            })
                                        ) : (
                                            <div className="flex h-full flex-col items-center justify-center px-6 text-center">
                                                <Avatar name={activeOther?.name} size="lg" />
                                                <h3 className="mt-3 font-display text-base font-bold text-ink-900">
                                                    {activeOther?.name ?? 'This conversation'}
                                                </h3>
                                                <p className="mt-1 max-w-sm text-xs leading-5 text-ink-500">
                                                    No messages yet. Say hello and coordinate directly. Remember not to share patient-identifiable information.
                                                </p>
                                            </div>
                                        )}
                                    </div>

                                    <form onSubmit={sendMessage} className="shrink-0 border-t border-ink-100 bg-white p-3 sm:p-4">
                                        <div className="flex items-end gap-2">
                                            <textarea
                                                ref={composerRef}
                                                value={draft}
                                                onChange={(event) => setDraft(event.target.value.slice(0, 2000))}
                                                onKeyDown={handleComposerKeyDown}
                                                rows={1}
                                                maxLength={2000}
                                                placeholder={`Message ${activeOther?.name ?? ''}…`}
                                                className="input max-h-36 min-h-11 flex-1 resize-none py-3"
                                            />
                                            <Button
                                                type="submit"
                                                disabled={!draft.trim() || sending}
                                                className="h-11 shrink-0"
                                            >
                                                {sending ? (
                                                    <Loader2 className="size-4 animate-spin" />
                                                ) : (
                                                    <Send className="size-4" />
                                                )}
                                                <span className="hidden sm:inline">Send</span>
                                            </Button>
                                        </div>
                                        <div className="mt-1.5 flex items-center justify-between gap-2 text-[10px] text-ink-400">
                                            <span>Enter to send · Shift + Enter for a new line</span>
                                            <span>{draft.length}/2000</span>
                                        </div>
                                    </form>
                                </>
                            ) : (
                                <div className="flex h-full flex-col items-center justify-center px-6 py-12 text-center">
                                    <span className="flex size-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-700">
                                        <MessageSquare className="size-7" />
                                    </span>
                                    <h3 className="mt-4 font-display text-lg font-bold text-ink-900">Select a conversation</h3>
                                    <p className="mt-1.5 max-w-md text-sm leading-6 text-ink-500">
                                        Choose an existing chat, or start a direct message with any active BHN user across the network.
                                    </p>
                                    <Button className="mt-5" onClick={() => setPickerOpen(true)}>
                                        <Plus className="size-4" />
                                        New message
                                    </Button>
                                </div>
                            )}
                        </section>
                    </div>
                </Card>

                <Modal
                    open={Boolean(deleteTarget)}
                    onClose={() => (deleting ? undefined : setDeleteTarget(null))}
                    title="Delete this message?"
                    description="The message will be replaced with “This message was deleted” for both participants. This cannot be undone."
                    size="sm"
                    icon={Trash2}
                    footer={
                        <>
                            <Button variant="secondary" onClick={() => setDeleteTarget(null)} disabled={deleting}>
                                Cancel
                            </Button>
                            <Button variant="danger" onClick={confirmDelete} disabled={deleting}>
                                {deleting ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : (
                                    <Trash2 className="size-4" />
                                )}
                                Delete message
                            </Button>
                        </>
                    }
                >
                    {deleteTarget?.body && (
                        <p className="max-h-40 overflow-y-auto whitespace-pre-wrap break-words rounded-xl bg-ink-50 px-3.5 py-3 text-sm leading-6 text-ink-700 ring-1 ring-ink-100">
                            {deleteTarget.body}
                        </p>
                    )}
                </Modal>
            </div>
        </AppLayout>
    );
}
