(() => {
    const app = document.querySelector('.support-agent-app');
    if (!app) return;

    const list = app.querySelector('.support-conversation-list');
    const count = app.querySelector('.support-inbox-count');
    const threadHeader = app.querySelector('.support-thread-header');
    const threadMessages = app.querySelector('.support-thread-messages');
    const placeholder = app.querySelector('.support-chat-placeholder');
    const replyForm = app.querySelector('.support-reply-form');
    const replyInput = replyForm.querySelector('textarea');
    const closedNote = app.querySelector('.support-thread-closed');
    const toast = document.querySelector('.support-agent-toast');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const base = app.dataset.apiBase;
    let conversations = [];
    let selected = null;
    let threadUuid = null;
    let threadLastId = 0;
    let filter = 'all';
    let loadingThread = false;
    let lastMessages = new Map();
    let toastTimer;

    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
    const initials = value => String(value || '?').trim().slice(0, 1).toUpperCase();
    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(options.headers || {}) }
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'That action could not be completed.');
        return data;
    };
    const notify = message => {
        toast.textContent = message;
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 3000);
    };
    const dateLabel = value => {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.valueOf())) return '';
        const now = new Date();
        return date.toDateString() === now.toDateString()
            ? date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
            : date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    };
    const showSelected = yes => {
        app.classList.toggle('has-selected', yes);
        placeholder.hidden = yes;
        threadHeader.hidden = !yes;
        threadMessages.hidden = !yes;
        replyForm.hidden = !yes;
        if (!yes) closedNote.hidden = true;
    };
    const renderList = () => {
        count.textContent = String(conversations.length);
        const visible = conversations.filter(item => filter !== 'unread' || Number(item.unread) > 0);
        if (!visible.length) {
            list.innerHTML = `<div class="support-inbox-empty">${filter === 'unread' ? 'You’re all caught up. New unread chats will appear here.' : 'No open chats right now. New player conversations will show up here.'}</div>`;
            return;
        }
        list.innerHTML = visible.map(item => `<button class="support-conversation${selected === item.uuid ? ' is-active' : ''}" type="button" data-uuid="${escape(item.uuid)}">
            <span class="support-conversation__avatar">${escape(initials(item.name))}</span>
            <span class="support-conversation__copy"><span class="support-conversation__top"><strong>${escape(item.name)}</strong><time class="support-conversation__time">${escape(dateLabel(item.updated_at))}</time></span>
            <span class="support-conversation__preview">${escape(item.last_message)}</span><span class="support-conversation__bottom">#${escape(item.ticket)} · ${item.assigned ? 'Assigned to you' : 'Waiting for an agent'}${Number(item.unread) ? `<b class="support-unread-count">${Number(item.unread)}</b>` : ''}</span></span></button>`).join('');
    };
    const loadList = async () => {
        try {
            const data = await request(app.dataset.listUrl);
            const previousUnread = new Map(conversations.map(item => [item.uuid, Number(item.unread)]));
            conversations = data.conversations || [];
            renderList();
            if (selected) {
                const current = conversations.find(item => item.uuid === selected);
                if (!current) { selected = null; showSelected(false); return; }
                const before = previousUnread.get(selected) || 0;
                if (Number(current.unread) > before && document.hidden && 'Notification' in window && Notification.permission === 'granted') {
                    new Notification('New Winxpot support message', { body: `${current.name}: ${current.last_message}` });
                }
            }
        } catch (error) { notify(error.message); }
    };
    const appendMessage = (message, shouldScroll = true) => {
        if (!message || lastMessages.has(String(message.id))) return;
        const nearBottom = threadMessages.scrollHeight - threadMessages.scrollTop - threadMessages.clientHeight < 90;
        lastMessages.set(String(message.id), true);
        threadLastId = Math.max(threadLastId, Number(message.id) || 0);
        const own = ['agent', 'admin'].includes(message.sender);
        const row = document.createElement('article');
        row.className = `support-agent-message${own ? ' is-agent' : ''}`;
        const name = message.sender === 'bot' ? 'Winxpot Assistant' : (own ? 'You' : selectedConversationName());
        row.innerHTML = `<span class="support-agent-message__name">${escape(name)}</span><div class="support-agent-message__bubble"></div><time class="support-agent-message__time">${escape(message.time || '')}</time>`;
        row.querySelector('.support-agent-message__bubble').textContent = message.body || '';
        threadMessages.append(row);
        if (shouldScroll && nearBottom) threadMessages.scrollTop = threadMessages.scrollHeight;
    };
    const selectedConversationName = () => conversations.find(item => item.uuid === selected)?.name || 'Player';
    const loadThread = async (announce = false) => {
        if (!selected || loadingThread) return;
        loadingThread = true;
        const uuid = selected;
        try {
            if (threadUuid !== uuid) {
                threadUuid = uuid;
                threadLastId = 0;
                lastMessages = new Map();
                threadMessages.replaceChildren();
            }
            const data = await request(`${base}/${encodeURIComponent(uuid)}/messages?after=${threadLastId}`);
            if (selected !== uuid) return;
            const conversation = data.conversation;
            app.querySelector('.support-thread-name').textContent = conversation.name || 'Website visitor';
            app.querySelector('.support-thread-email').textContent = conversation.email || 'Winxpot player';
            app.querySelector('.support-thread-ticket').textContent = `Ticket #${conversation.ticket}`;
            app.querySelector('.support-thread-avatar').textContent = initials(conversation.name);
            const claim = app.querySelector('.support-claim-button');
            claim.hidden = conversation.assigned;
            app.querySelector('.support-close-button').hidden = !conversation.assigned;
            const closed = conversation.status === 'closed';
            replyForm.hidden = closed;
            closedNote.hidden = !closed;
            (data.messages || []).forEach(message => appendMessage(message));
            if (announce) replyInput.focus({ preventScroll: true });
        } catch (error) { notify(error.message); }
        finally { loadingThread = false; }
    };

    list.addEventListener('click', async event => {
        const button = event.target.closest('[data-uuid]');
        if (!button) return;
        selected = button.dataset.uuid;
        showSelected(true);
        renderList();
        await loadThread(true);
        await loadList();
    });
    app.querySelector('.support-inbox-tabs').addEventListener('click', event => {
        const button = event.target.closest('[data-filter]');
        if (!button) return;
        filter = button.dataset.filter;
        app.querySelectorAll('.support-inbox-tabs button').forEach(tab => tab.classList.toggle('is-active', tab === button));
        renderList();
    });
    app.querySelector('.support-mobile-back').addEventListener('click', () => { selected = null; showSelected(false); renderList(); });
    app.querySelector('.support-claim-button').addEventListener('click', async event => {
        if (!selected) return;
        event.currentTarget.disabled = true;
        try {
            await request(`${base}/${encodeURIComponent(selected)}/claim`, { method: 'POST', body: '{}' });
            await loadThread(); await loadList(); notify('This chat is now in your care.');
        } catch (error) { notify(error.message); }
        finally { event.currentTarget.disabled = false; }
    });
    app.querySelector('.support-close-button').addEventListener('click', async () => {
        if (!selected) return;
        try {
            await request(`${base}/${encodeURIComponent(selected)}/close`, { method: 'POST', body: '{}' });
            await loadThread(); await loadList(); notify('Ticket closed.');
        } catch (error) { notify(error.message); }
    });
    replyForm.addEventListener('submit', async event => {
        event.preventDefault();
        const message = replyInput.value.trim();
        if (!message || !selected) return;
        const button = replyForm.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const data = await request(`${base}/${encodeURIComponent(selected)}/messages`, { method: 'POST', body: JSON.stringify({ message }) });
            replyInput.value = '';
            appendMessage(data.message);
            await loadList();
        } catch (error) { notify(error.message); }
        finally { button.disabled = false; replyInput.focus({ preventScroll: true }); }
    });
    replyInput.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); replyForm.requestSubmit(); }
    });
    app.querySelector('.support-notify-toggle').addEventListener('click', async event => {
        if (!('Notification' in window)) { notify('Desktop notifications are not supported in this browser.'); return; }
        const permission = await Notification.requestPermission();
        event.currentTarget.textContent = permission === 'granted' ? 'Alerts on' : 'Enable alerts';
        notify(permission === 'granted' ? 'Desktop alerts are enabled.' : 'Browser alerts were not enabled.');
    });

    loadList();
    setInterval(async () => { await loadList(); if (selected) await loadThread(); }, 4000);
})();
