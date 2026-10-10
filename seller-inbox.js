(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const sellerId = document.querySelector('meta[name="seller-id"]')?.content || '';
  let conversations = [];
  let activeId = '';

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[char]);

  async function request(data) {
    const response = await fetch('api/messages.php', {
      method: data ? 'POST' : 'GET',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        ...(data ? { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf } : {})
      },
      body: data ? JSON.stringify(data) : undefined
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Could not load messages.');
    return result;
  }

  function drawConversation() {
    const conversation = conversations.find((item) => item.id === activeId);
    const log = document.getElementById('messageLog');
    const input = document.getElementById('replyInput');
    const send = document.getElementById('replyButton');
    if (!log || !input || !send) return;

    input.disabled = !conversation;
    send.disabled = !conversation;
    document.getElementById('contactName').textContent = conversation?.buyerName || 'Choose a conversation';
    document.getElementById('contactAvatar').textContent = conversation ? (conversation.buyerName || 'B').slice(0, 1).toUpperCase() : '🐾';
    document.getElementById('contactPet').textContent = conversation
      ? `${conversation.petName} · ${conversation.listing?.breed || ''} · ${conversation.listing?.age ?? ''} years · ${conversation.listing?.sex || ''} · Pickup: ${conversation.listing?.pickupLocation || 'confirm together'}`
      : 'Buyer and pet details appear here';

    if (!conversation) {
      log.innerHTML = '<p class="empty">Choose a buyer to read and reply to their message.</p>';
      return;
    }

    log.innerHTML = (conversation.messages || []).map((message) => {
      const sentBySeller = message.senderId === sellerId;
      const sentAt = new Date(message.sentAt);
      const time = Number.isNaN(sentAt.getTime()) ? '' : sentAt.toLocaleString();
      return `<div class="inbox-bubble ${sentBySeller ? 'mine' : ''}">${escapeHtml(message.text).replace(/\n/g, '<br>')}<small>${escapeHtml(message.senderName)} · ${escapeHtml(time)}</small></div>`;
    }).join('');
    log.scrollTop = log.scrollHeight;
  }

  function drawThreads() {
    const container = document.getElementById('messageThreads');
    const notice = document.getElementById('replyNotice');
    if (!container || !notice) return;
    const waiting = conversations.filter((conversation) => conversation.messages?.at(-1)?.senderId !== sellerId).length;
    const badge = document.getElementById('messageCount');
    if (badge) badge.textContent = waiting || '';
    notice.textContent = waiting ? `${waiting} buyer${waiting === 1 ? '' : 's'} waiting for your reply` : 'All conversations up to date';
    notice.classList.toggle('pending', waiting > 0);

    if (!conversations.length) {
      container.innerHTML = '<p class="empty">No buyer messages yet. New inquiries about your pets will show up here.</p>';
      activeId = '';
      drawConversation();
      return;
    }

    if (!conversations.some((conversation) => conversation.id === activeId)) activeId = conversations[conversations.length - 1].id;
    container.innerHTML = conversations.slice().reverse().map((conversation) => {
      const latest = conversation.messages?.at(-1) || {};
      const needsReply = latest.senderId !== sellerId;
      return `<button class="inbox-thread ${conversation.id === activeId ? 'active' : ''}" data-conversation-id="${escapeHtml(conversation.id)}"><span class="stat-icon">${escapeHtml((conversation.buyerName || 'B').slice(0, 1).toUpperCase())}</span><span class="inbox-thread-copy"><b>${escapeHtml(conversation.buyerName)} · ${escapeHtml(conversation.petName)}</b><small>${escapeHtml(latest.text || 'New inquiry')}</small></span>${needsReply ? '<em>New</em>' : ''}</button>`;
    }).join('');
    container.querySelectorAll('[data-conversation-id]').forEach((button) => {
      button.addEventListener('click', () => {
        activeId = button.dataset.conversationId;
        drawThreads();
      });
    });
    drawConversation();
  }

  async function loadMessages(showError = true) {
    try {
      const result = await request();
      conversations = result.conversations || [];
      drawThreads();
    } catch (error) {
      const notice = document.getElementById('replyNotice');
      if (notice && showError) notice.textContent = error.message;
    }
  }

  const replyForm = document.getElementById('replyForm');
  replyForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const input = document.getElementById('replyInput');
    const send = document.getElementById('replyButton');
    const message = input.value.trim();
    if (!activeId || !message) return;
    send.disabled = true;
    try {
      const result = await request({ action: 'reply', conversationId: activeId, message });
      const index = conversations.findIndex((conversation) => conversation.id === activeId);
      if (index >= 0) conversations[index] = result.conversation;
      input.value = '';
      drawThreads();
    } catch (error) {
      const notice = document.getElementById('replyNotice');
      if (notice) notice.textContent = error.message;
    } finally {
      send.disabled = false;
    }
  });

  loadMessages();
  window.setInterval(() => loadMessages(false), 5000);

  // Immediate refresh when switching to Messages view
  document.querySelectorAll('[data-view="messages"]').forEach((button) => {
    button.addEventListener('click', () => loadMessages(false));
  });

  // Refresh when the tab regains focus
  window.addEventListener('focus', () => loadMessages(false));

  window.loadSellerMessages = loadMessages;
})();