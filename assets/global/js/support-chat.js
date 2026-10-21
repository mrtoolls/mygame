(function () {
    'use strict';

    const root = document.querySelector('.winx-support-widget');
    if (!root) return;

    const launcher = root.querySelector('.winx-support-launcher');
    const panel = root.querySelector('.winx-support-panel');
    const closeButton = root.querySelector('.winx-support-close');
    const discovery = root.querySelector('.winx-support-discovery');
    const chat = root.querySelector('.winx-support-chat');
    const agentList = root.querySelector('.winx-support-agent-list');
    const assistantButton = root.querySelector('.winx-support-assistant');
    const messageList = root.querySelector('.winx-support-messages');
    const chatAgent = root.querySelector('.winx-support-chat-agent');
    const ticketLabel = root.querySelector('.winx-support-ticket');
    const form = root.querySelector('.winx-support-composer');
    const composer = form.querySelector('textarea');
    const unreadBadge = root.querySelector('.winx-support-unread');
    const storageKey = 'winxpot.support.chat.v1';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let conversation = readSaved();
    let lastId = Number(conversation?.lastId || 0);
    let unread = Number(conversation?.unread || 0);
    let pollTimer = null;
    let agentsLoaded = false;
    let seen = new Set();

    function readSaved() {
        try { return JSON.parse(localStorage.getItem(storageKey) || 'null'); } catch (_) { return null; }
    }

    function persist() {
        if (!conversation) return;
        conversation.lastId = lastId;
        conversation.unread = unread;
        localStorage.setItem(storageKey, JSON.stringify(conversation));
    }

    function setUnread(value) {
        unread = Math.max(0, value);
        unreadBadge.textContent = unread > 9 ? '9+' : String(unread);
        unreadBadge.hidden = unread === 0;
        persist();
    }

    async function request(url, options = {}) {
        const headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, options.headers || {});
        if (options.body) {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-TOKEN'] = csrf;
        }
        if (conversation?.token) headers['X-Support-Token'] = conversation.token;
        const response = await fetch(url, Object.assign({ credentials: 'same-origin', headers }, options));
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(payload.message || 'Support is temporarily unavailable. Please try again.');
            error.status = response.status;
            throw error;
        }
        return payload;
    }

    function showPanel(view) {
        panel.hidden = false;
        launcher.setAttribute('aria-expanded', 'true');
        discovery.hidden = view === 'chat';
        chat.hidden = view !== 'chat';
        // Skip the auto-focus on touch devices - it pops the on-screen keyboard immediately
        // and covers the panel before the visitor even sees it.
        if (view === 'chat' && !window.matchMedia('(pointer: coarse)').matches) composer.focus({ preventScroll: true });
    }

    function hidePanel() {
        panel.hidden = true;
        launcher.setAttribute('aria-expanded', 'false');
    }

    function renderAgents(agents) {
        agentList.replaceChildren();
        if (!agents.length) {
            const empty = document.createElement('div');
            empty.className = 'winx-support-empty';
            empty.textContent = 'Our care team is getting ready. Start with the assistant, or leave a message for an agent.';
            agentList.append(empty);
            return;
        }

        agents.forEach((agent) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'winx-support-agent';
            button.dataset.agentId = agent.id;
            const photo = document.createElement('span');
            photo.className = 'winx-support-agent__photo';
            photo.textContent = (agent.name || 'W').slice(0, 1).toUpperCase();
            if (agent.photo) {
                const image = document.createElement('img');
                image.src = agent.photo;
                image.alt = '';
                image.loading = 'lazy';
                photo.append(image);
            }
            const info = document.createElement('span');
            info.className = 'winx-support-agent__info';
            const name = document.createElement('strong'); name.textContent = agent.name;
            const title = document.createElement('small'); title.textContent = agent.title;
            info.append(name, title);
            const state = document.createElement('span'); state.className = 'winx-support-agent__online';
            state.textContent = agent.online ? 'ONLINE' : 'TEAM';
            button.append(photo, info, state);
            button.addEventListener('click', () => startChat(agent.id));
            agentList.append(button);
        });
    }

    async function loadAgents() {
        if (agentsLoaded) return;
        try {
            const data = await request(root.dataset.agentsUrl);
            renderAgents(data.agents || []);
            agentsLoaded = true;
        } catch (_) {
            const error = document.createElement('div');
            error.className = 'winx-support-empty';
            error.textContent = 'We could not load the agent list. You can still start with the assistant.';
            agentList.replaceChildren(error);
        }
    }

    async function restoreSignedInChat() {
        if (root.dataset.authenticated !== '1' || conversation) return;
        try {
            const data = await request(root.dataset.activeUrl);
            if (!data.conversation || conversation) return;
            conversation = Object.assign(data.conversation, { lastId: 0, unread: Number(data.unread_count || 0) });
            lastId = 0;
            setUnread(data.unread_count || 0);
            persist();
            addResumeButton();
            beginPolling();
        } catch (_) { /* A later visit can retry without interrupting the page. */ }
    }

    function addResumeButton() {
        if (!conversation || root.querySelector('.winx-support-resume')) return;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'winx-support-assistant winx-support-resume';
        button.innerHTML = '<span class="winx-support-assistant__icon">↗</span><span><strong>Continue your previous chat</strong><small>Pick up where you left off</small></span><b>›</b>';
        button.addEventListener('click', resumeChat);
        assistantButton.before(button);
    }

    function showInlineError(error) {
        const node = document.createElement('div');
        node.className = 'winx-support-error';
        node.textContent = error.message || 'Something went wrong. Please try again.';
        messageList.append(node);
        messageList.scrollTop = messageList.scrollHeight;
    }

    function renderMessage(message, isNew = false) {
        if (!message || seen.has(String(message.id))) return;
        seen.add(String(message.id));
        lastId = Math.max(lastId, Number(message.id) || 0);
        const row = document.createElement('div');
        row.className = 'winx-support-message' + (message.sender === 'user' ? ' is-user' : '');

        if (message.sender !== 'user') {
            const avatar = document.createElement('span'); avatar.className = 'winx-support-message__avatar';
            avatar.textContent = message.sender === 'bot' ? '✦' : (message.sender_name || 'W').slice(0, 1).toUpperCase();
            if (message.sender_photo) {
                const image = document.createElement('img'); image.src = message.sender_photo; image.alt = ''; avatar.replaceChildren(image);
            }
            row.append(avatar);
        }

        const content = document.createElement('div'); content.className = 'winx-support-message__content';
        const bubble = document.createElement('div'); bubble.className = 'winx-support-message__bubble'; bubble.textContent = message.body || '';
        content.append(bubble);
        const meta = document.createElement('div'); meta.className = 'winx-support-message__meta';
        meta.textContent = `${message.sender_name || (message.sender === 'user' ? 'You' : 'Support')} · ${formatTime(message.time)}`;
        content.append(meta);

        const suggestions = message.metadata?.suggestions || [];
        if (suggestions.length && message.sender === 'bot') {
            const choices = document.createElement('div'); choices.className = 'winx-support-suggestions';
            suggestions.forEach((label) => {
                const choice = document.createElement('button'); choice.type = 'button'; choice.className = 'winx-support-suggestion';
                choice.textContent = label;
                choice.addEventListener('click', () => sendMessage(label));
                choices.append(choice);
            });
            content.append(choices);
        }

        row.append(content);
        if (message.sender === 'user') {
            const avatar = document.createElement('span'); avatar.className = 'winx-support-message__avatar'; avatar.textContent = 'You'; row.append(avatar);
        }
        messageList.append(row);
        messageList.scrollTop = messageList.scrollHeight;
        if (isNew && message.sender !== 'user' && (panel.hidden || chat.hidden)) setUnread(unread + 1);
        persist();
    }

    function formatTime(value) {
        if (!value) return 'now';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? 'now' : date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    function updateTicketInfo(data = {}) {
        chatAgent.textContent = data.agent || conversation?.agent || root.dataset.botName || 'Winxpot Assistant';
        ticketLabel.textContent = data.ticket ? `Ticket #${data.ticket}` : '';
        const closed = data.status === 'closed';
        composer.disabled = closed;
        form.querySelector('button[type="submit"]').disabled = closed;
        composer.placeholder = closed ? 'This support chat is closed' : 'Write a message…';
    }

    async function startChat(agentId = null) {
        const details = root.querySelector('.winx-support-contact');
        const body = {};
        if (agentId) body.agent_id = Number(agentId);
        if (root.dataset.authenticated !== '1' && details) {
            body.name = details.querySelector('[name="visitor_name"]').value.trim();
            body.email = details.querySelector('[name="visitor_email"]').value.trim();
        }
        assistantButton.disabled = true;
        try {
            const data = await request(root.dataset.startUrl, { method: 'POST', body: JSON.stringify(body) });
            conversation = Object.assign(data.conversation, { lastId: 0, unread: 0 });
            lastId = 0; unread = 0; seen = new Set(); messageList.replaceChildren();
            (data.messages || []).forEach((message) => renderMessage(message));
            updateTicketInfo(data.conversation);
            setUnread(0); persist(); showPanel('chat'); beginPolling();
        } catch (error) {
            showPanel('discovery');
            const message = document.createElement('div'); message.className = 'winx-support-empty'; message.textContent = error.message;
            agentList.prepend(message);
        } finally { assistantButton.disabled = false; }
    }

    async function resumeChat() {
        if (!conversation) return;
        showPanel('chat');
        try {
            const data = await request(`${root.dataset.messagesUrl}/${encodeURIComponent(conversation.uuid)}/messages?after=0&mark_read=1`);
            seen = new Set(); lastId = 0; messageList.replaceChildren();
            (data.messages || []).forEach((message) => renderMessage(message));
            updateTicketInfo({ status: data.status, agent: data.agent?.name, ticket: conversation.ticket });
            setUnread(0); persist(); beginPolling();
        } catch (error) {
            conversation = null; localStorage.removeItem(storageKey); lastId = 0; seen.clear();
            showPanel('discovery'); addResumeButton();
            const errorBox = document.createElement('div'); errorBox.className = 'winx-support-empty'; errorBox.textContent = error.message; agentList.prepend(errorBox);
        }
    }

    async function sendMessage(text) {
        if (!conversation || !text.trim()) return;
        const body = text.trim();
        composer.value = '';
        composer.style.height = 'auto';
        try {
            const data = await request(`${root.dataset.messagesUrl}/${encodeURIComponent(conversation.uuid)}/messages`, { method: 'POST', body: JSON.stringify({ message: body }) });
            (data.messages || []).forEach((message) => renderMessage(message));
            updateTicketInfo({ status: data.status });
        } catch (error) { showInlineError(error); }
        persist();
    }

    async function poll() {
        if (!conversation || document.visibilityState === 'hidden') return;
        try {
            const markRead = !panel.hidden && !chat.hidden ? 1 : 0;
            const data = await request(`${root.dataset.messagesUrl}/${encodeURIComponent(conversation.uuid)}/messages?after=${lastId}&mark_read=${markRead}`);
            (data.messages || []).forEach((message) => renderMessage(message, true));
            updateTicketInfo({ status: data.status, agent: data.agent?.name });
            if (typeof data.unread_count === 'number') setUnread(data.unread_count);
            else if (markRead) setUnread(0);
            persist();
        } catch (error) {
            if (error.status === 404) { conversation = null; localStorage.removeItem(storageKey); }
        }
    }

    function beginPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(poll, 3500);
    }

    launcher.addEventListener('click', async () => {
        if (panel.hidden) {
            showPanel('discovery');
            await loadAgents();
            addResumeButton();
            poll();
        } else hidePanel();
    });
    closeButton.addEventListener('click', hidePanel);
    assistantButton.addEventListener('click', () => startChat());
    root.querySelector('.winx-support-back').addEventListener('click', async () => { showPanel('discovery'); await loadAgents(); });
    panel.addEventListener('click', (event) => {
        if (event.target.matches('.winx-support-suggestion')) return;
    });
    form.addEventListener('submit', (event) => { event.preventDefault(); sendMessage(composer.value); });
    composer.addEventListener('input', () => { composer.style.height = 'auto'; composer.style.height = `${Math.min(composer.scrollHeight, 90)}px`; });
    composer.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); }
    });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !panel.hidden) hidePanel(); });

    setUnread(unread);
    if (conversation) beginPolling();
    else restoreSignedInChat();
})();
