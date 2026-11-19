let activeConversationId = null;

export function setActiveConversation(id) {
    activeConversationId = id ? Number(id) : null;
}

export function getActiveConversation() {
    return activeConversationId;
}

export function dispatchChatUnread(count) {
    if (typeof window === 'undefined') return;

    window.dispatchEvent(
        new CustomEvent('chat:unread', {
            detail: { count: Math.max(0, Number(count) || 0) },
        }),
    );
}
