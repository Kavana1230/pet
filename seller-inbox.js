(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const sellerId = document.querySelector('meta[name="seller-id"]')?.content || '';
  let conversations = [];
  let activeId = '';

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[char]);

  async function request(endpoint, data = null, method = 'POST') {
    const response = await fetch(endpoint, {
      method: data ? method : 'GET',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        ...(data ? { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf } : {})
      },
      body: data ? JSON.stringify(data) : undefined
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Operation failed');
    return result;
  }

  function drawConversation() {
    const conversation = conversations.find((item) => item.id === activeId);
    const log = document.getElementById('messageLog');
    const input = document.getElementById('replyInput');
    const send = document.getElementById('replyButton');
    let actionBar = document.getElementById('inboxActionBar');
    if (!actionBar) {
      actionBar = document.createElement('div');
      actionBar.id = 'inboxActionBar';
      actionBar.className = 'app-action-bar';
      const head = document.querySelector('.inbox-chat-head');
      if (head) head.after(actionBar);
    }

    if (!log || !input || !send) return;

    input.disabled = !conversation;
    send.disabled = !conversation;
    document.getElementById('contactName').textContent = conversation?.buyerName || 'Choose a conversation';
    document.getElementById('contactAvatar').textContent = conversation ? (conversation.buyerName || 'B').slice(0, 1).toUpperCase() : '🐾';
    
    const petInfo = conversation?.listing || {};
    document.getElementById('contactPet').textContent = conversation
      ? `${conversation.petName} · ${petInfo.breed || ''} · ${petInfo.age ?? ''} yrs · ${petInfo.sex || ''} · Pickup: ${petInfo.pickupLocation || 'confirm together'}`
      : 'Buyer and pet details appear here';

    if (!conversation) {
      actionBar.style.display = 'none';
      log.innerHTML = '<p class="empty">Choose a buyer to read and reply to their message.</p>';
      return;
    }

    // Render Application Workflow Bar (Stage 1 Bug C)
    const status = conversation.status || 'pending';
    actionBar.style.display = 'flex';
    let statusText = '';
    let actionsHtml = '';

    if (status === 'pending') {
      statusText = '<span style="color:#b45309;">⏳ Adoption inquiry pending your review</span>';
      actionsHtml = `
        <button class="btn sm success" id="sellerAcceptBtn">✓ Accept</button>
        <button class="btn sm danger" id="sellerRejectBtn">✕ Decline</button>
      `;
    } else if (status === 'accepted') {
      statusText = '<span style="color:#16815d;">✅ Accepted by you · Meeting not yet confirmed</span>';
      actionsHtml = `
        <button class="btn sm" style="background:#1769d2;color:white;" id="sellerConfirmBtn">📅 Confirm meeting</button>
        <button class="btn sm danger" id="sellerRejectBtn">Decline</button>
      `;
    } else if (status === 'meeting_confirmed') {
      statusText = '<span style="color:#16815d;">🤝 Meeting confirmed · Arrange a time in chat · Listing is reserved</span>';
      actionsHtml = `
        <button class="btn sm success" id="sellerCompleteBtn">🎉 Mark Adoption Complete</button>
        <button class="btn sm" style="background:#fff;color:#7056df;border:1px solid #7056df;" id="sellerReopenBtn">↩ Reopen Listing</button>
      `;
    } else if (status === 'rejected') {
      statusText = '<span style="color:#d32f2f;">✕ Application declined · Conversation closed</span>';
      actionsHtml = '';
    } else if (status === 'completed') {
      statusText = '<span style="color:#16815d;">🎉 Pet adopted! Happy new home.</span>';
      actionsHtml = '';
    } else {
      statusText = `<span>Status: ${escapeHtml(status)}</span>`;
    }

    actionBar.innerHTML = `
      <div>${statusText}</div>
      <div class="actions">${actionsHtml}</div>
    `;
    // Hook workflow buttons
    document.getElementById('sellerAcceptBtn')?.addEventListener('click', async () => {
      try {
        const res = await request('api/applications.php', { action: 'accept', conversationId: activeId });
        window.say?.(res.message);
        await loadMessages(false);
      } catch (err) {
        window.say?.(err.message);
      }
    });

    document.getElementById('sellerRejectBtn')?.addEventListener('click', async () => {
      if (!confirm('Are you sure you want to decline this application? This conversation will become read-only.')) return;
      try {
        const res = await request('api/applications.php', { action: 'reject', conversationId: activeId });
        window.say?.(res.message);
        await loadMessages(false);
      } catch (err) {
        window.say?.(err.message);
      }
    });

    document.getElementById('sellerConfirmBtn')?.addEventListener('click', async () => {
      try {
        const res = await request('api/applications.php', { action: 'confirm_meeting', conversationId: activeId });
        window.say?.(res.message);
        await loadMessages(false);
      } catch (err) {
        window.say?.(err.message);
      }
    });

    document.getElementById('sellerReopenBtn')?.addEventListener('click', async () => {
      if (!confirm('Did the meeting not work out? Reopening will make this listing available in the adoption catalog again.')) return;
      try {
        const res = await request('api/applications.php', { action: 'reopen', conversationId: activeId });
        window.say?.(res.message);
        await loadMessages(false);
      } catch (err) {
        window.say?.(err.message);
      }
    });

    document.getElementById('sellerCompleteBtn')?.addEventListener('click', async () => {
      if (!confirm('Mark adoption as completed? Congratulations to the pet and adopter!')) return;
      try {
        const res = await request('api/applications.php', { action: 'complete', conversationId: activeId });
        window.say?.(res.message);
        await loadMessages(false);
      } catch (err) {
        window.say?.(err.message);
      }
    });

    log.innerHTML = (conversation.messages || []).filter((message) => !/meet-and-greet confirmed!|meeting confirmed! the pet listing has been reserved/i.test(message.text || '')).map((message) => {
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
    const waiting = conversations.filter((conversation) => Number(conversation.unreadCount || 0) > 0).length;
    const unreadMessages = conversations.reduce((total, conversation) => total + Number(conversation.unreadCount || 0), 0);
    const badge = document.getElementById('messageCount');
    if (badge) badge.textContent = unreadMessages || '';
    notice.textContent = waiting
      ? `${unreadMessages} unread message${unreadMessages === 1 ? '' : 's'} from ${waiting} buyer${waiting === 1 ? '' : 's'}`
      : 'All conversations up to date';
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
      const needsReply = Number(conversation.unreadCount || 0) > 0;
      const st = conversation.status || 'pending';
      const statusLabel = st === 'pending' ? 'Pending' : st === 'accepted' ? 'Accepted' : st === 'meeting_confirmed' ? 'Meeting' : st === 'rejected' ? 'Declined' : '';
      return `<button class="inbox-thread ${conversation.id === activeId ? 'active' : ''}" data-conversation-id="${escapeHtml(conversation.id)}"><span class="stat-icon">${escapeHtml((conversation.buyerName || 'B').slice(0, 1).toUpperCase())}</span><span class="inbox-thread-copy"><b>${escapeHtml(conversation.buyerName)} · ${escapeHtml(conversation.petName)}</b><small>${escapeHtml(latest.text || 'New inquiry')}</small></span>${needsReply ? '<em>New</em>' : statusLabel ? `<em style="background:#ede9fe;color:#7056df;">${statusLabel}</em>` : ''}</button>`;
    }).join('');
    container.querySelectorAll('[data-conversation-id]').forEach((button) => {
      button.addEventListener('click', () => {
        activeId = button.dataset.conversationId;
        drawThreads();
        markRead(activeId).catch((error) => {
          notice.textContent = error.message;
        });
      });
    });
    drawConversation();
  }

  async function markRead(conversationId) {
    const result = await request('api/messages.php', { action: 'read', conversationId });
    const index = conversations.findIndex((conversation) => conversation.id === conversationId);
    if (index >= 0) conversations[index] = result.conversation;
    drawThreads();
  }

  async function loadMessages(showError = true) {
    try {
      const result = await request('api/messages.php', null, 'GET');
      conversations = result.conversations || [];
      drawThreads();
      const active = conversations.find((conversation) => conversation.id === activeId);
      if (document.getElementById('messages')?.classList.contains('active') && Number(active?.unreadCount || 0) > 0) {
        await markRead(activeId);
      }
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
      const result = await request('api/messages.php', { action: 'reply', conversationId: activeId, message });
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
  window.setInterval(() => loadMessages(false), 7000);
  window.loadSellerMessages = loadMessages;
})();
