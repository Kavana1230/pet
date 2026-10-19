let pets = [];
let favorites = new Set();
let applications = [];
let messages = [];
let conversations = [];
let activeConversationId = '';
let selectedPet = null;
let messagingError = '';
let profile = {};
let settings = {};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

async function apiRequest(url, options = {}) {
  const headers = new Headers(options.headers || {});
  headers.set('Accept', 'application/json');
  if (options.method && options.method !== 'GET') {
    headers.set('Content-Type', 'application/json');
    headers.set('X-CSRF-Token', csrfToken());
  }

  const response = await fetch(url, { credentials: 'same-origin', ...options, headers });
  let result;
  try {
    result = await response.json();
  } catch {
    result = { success: false, message: 'The server returned an unexpected response.' };
  }

  if (response.status === 401) {
    window.location.href = 'login.php';
    throw new Error('Your session ended. Please sign in again.');
  }
  if (!response.ok || result.success === false) {
    throw new Error(result.message || 'The request could not be completed.');
  }
  return result;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[character]);
}

function showToast(message) {
  const toast = document.getElementById('toast');
  const toastMessage = document.getElementById('toastMessage');
  if (!toast || !toastMessage) return;

  toastMessage.textContent = message;
  toast.classList.add('show');
  clearTimeout(showToast.timeoutId);
  showToast.timeoutId = setTimeout(() => toast.classList.remove('show'), 2800);
}

function formatDate(dateString) {
  if (!dateString) return '—';
  const date = new Date(`${dateString}T00:00:00`);
  return Number.isNaN(date.getTime()) ? dateString : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function petByName(name) {
  return pets.find((pet) => pet.name === name);
}

function petCard(pet, compact = false) {
  const liked = favorites.has(pet.name);
  const isOwnListing = Boolean(pet.ownerId && pet.ownerId === profile.id);
  const species = String(pet.type || '').toLowerCase();
  const age = Number(pet.age);
  const ageLabel = `${escapeHtml(pet.age)} ${age === 1 ? 'year' : 'years'} old`;
  return `
    <article class="${compact ? 'dashboard-pet' : 'full-pet-card'}">
      <div class="pet-image-wrap">
        <img src="${escapeHtml(pet.image)}" alt="${escapeHtml(pet.name)}" loading="lazy">
        <button class="heart-btn${liked ? ' liked' : ''}" data-favorite="${escapeHtml(pet.name)}" aria-label="${liked ? 'Remove' : 'Add'} ${escapeHtml(pet.name)} ${liked ? 'from' : 'to'} favorites" aria-pressed="${liked}">${liked ? '♥' : '♡'}</button>
        <span class="species-chip ${escapeHtml(species)}">${escapeHtml(species)}</span>
      </div>
      <div class="${compact ? 'pet-body' : 'full-pet-card-body'}">
        <div class="pet-card-title"><h3>${escapeHtml(pet.name)}</h3><span class="pet-tag">${escapeHtml(pet.breed)}</span></div>
        <p class="pet-card-meta">${ageLabel}${pet.sex ? ` <span aria-hidden="true">·</span> ${escapeHtml(pet.sex[0].toUpperCase() + pet.sex.slice(1))}` : ''}</p>
        ${compact ? '' : `<p class="pet-card-description">${escapeHtml(pet.description || 'Ready to meet a loving new family.')}</p>`}
        ${isOwnListing
          ? '<button class="small-primary" type="button" disabled>Your listing</button>'
          : `<button class="${compact ? 'small-primary' : 'primary-btn'}" data-adopt="${escapeHtml(pet.id || pet.name)}">${compact ? `Meet ${escapeHtml(pet.name)}` : 'Meet this pet'}</button>`}
      </div>
    </article>`;
}

function filteredPets() {
  const searchValue = (document.getElementById('petSearch')?.value || document.getElementById('globalSearch')?.value || '').trim().toLowerCase();
  const typeValue = document.getElementById('typeFilter')?.value || 'all';
  const ageValue = document.getElementById('ageFilter')?.value || 'all';

  const filtered = pets.filter((pet) => {
    const matchesSearch = `${pet.name} ${pet.breed} ${pet.type} ${pet.description || ''}`.toLowerCase().includes(searchValue);
    const matchesType = typeValue === 'all' || pet.type === typeValue;
    const matchesAge = ageValue === 'all' || (ageValue === 'young' && pet.age <= 2) || (ageValue === 'adult' && pet.age >= 3);
    return matchesSearch && matchesType && matchesAge;
  });
  const sortValue = document.getElementById('petSort')?.value || 'recommended';
  if (sortValue === 'name') filtered.sort((a, b) => String(a.name).localeCompare(String(b.name)));
  if (sortValue === 'youngest') filtered.sort((a, b) => Number(a.age) - Number(b.age));
  if (sortValue === 'oldest') filtered.sort((a, b) => Number(b.age) - Number(a.age));
  if (sortValue === 'newest') filtered.sort((a, b) => Date.parse(b.createdAt || '') - Date.parse(a.createdAt || ''));
  return filtered;
}

function renderPetGrid() {
  const grid = document.getElementById('fullPetGrid');
  const favoriteGrid = document.getElementById('favoriteGrid');
  const filtered = filteredPets();
  const resultCount = document.getElementById('petResultCount');

  if (resultCount) {
    resultCount.textContent = `${filtered.length} ${filtered.length === 1 ? 'companion' : 'companions'} to meet`;
  }
  document.querySelectorAll('[data-pet-type]').forEach((button) => {
    const active = button.dataset.petType === (document.getElementById('typeFilter')?.value || 'all');
    button.classList.toggle('active', active);
    button.setAttribute('aria-pressed', String(active));
  });

  if (grid) {
    grid.innerHTML = filtered.length ? filtered.map((pet) => petCard(pet)).join('') : '<p class="empty-state">No pets match those filters. Try changing your search.</p>';
  }
  if (favoriteGrid) {
    const savedPets = pets.filter((pet) => favorites.has(pet.name));
    favoriteGrid.innerHTML = savedPets.length ? savedPets.map((pet) => petCard(pet)).join('') : '<p class="empty-state">No favorites saved yet. Tap the heart on a pet you love.</p>';
  }

  const recommendations = document.getElementById('recommendedPets');
  if (recommendations) {
    const picks = ['dog', 'cat', 'hamster'].map((species) => pets.find((pet) => pet.type === species && (!pet.ownerId || pet.ownerId !== profile.id))).filter(Boolean);
    recommendations.innerHTML = picks.map((pet) => petCard(pet, true)).join('');
  }
}

function renderApplications() {
  const tableBody = document.getElementById('applicationsTableBody');
  const recent = document.getElementById('recentApplications');
  const rows = applications.map((application) => {
    const pet = petByName(application.petName) || application;
    const status = application.status || 'Under Review';
    const statusClass = status.toLowerCase().includes('meet') || status === 'Approved' ? 'approved' : status === 'Completed' ? 'completed' : 'pending';
    const image = pet.image || 'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=140&q=80';
    return `<tr>
      <td><div class="table-pet"><img src="${escapeHtml(image)}" alt="${escapeHtml(application.petName)}" loading="lazy"><span><strong>${escapeHtml(application.petName)}</strong><small>${escapeHtml(pet.breed || 'Adoption request')}</small></span></div></td>
      <td>${escapeHtml(formatDate(application.date))}</td><td><span class="status ${statusClass}">${escapeHtml(status)}</span></td>
      <td>${escapeHtml(application.nextStep || 'Await review')}</td><td></td>
    </tr>`;
  });

  if (tableBody) tableBody.innerHTML = rows.length ? rows.slice().reverse().join('') : '<tr><td colspan="5" class="empty-state">No applications yet. Find a pet to get started.</td></tr>';
  if (recent) {
    recent.innerHTML = applications.length ? applications.slice().reverse().slice(0, 3).map((application) => {
      const pet = petByName(application.petName) || application;
      const statusClass = (application.status || '').toLowerCase().includes('meet') ? 'green-dot' : 'purple-dot';
      return `<div class="application-row">
        <div class="pet-mini"><img src="${escapeHtml(pet.image || '')}" alt="${escapeHtml(application.petName)}" loading="lazy"></div>
        <div class="application-info"><strong>${escapeHtml(application.petName)}</strong><span>${escapeHtml(pet.breed || 'Adoption request')}</span></div>
        <span class="status ${statusClass === 'green-dot' ? 'approved' : 'pending'}">${escapeHtml(application.status || 'Under Review')}</span>
        <span class="application-date">${escapeHtml(formatDate(application.date))}</span>
      </div>`;
    }).join('') : '<p class="empty-state">Your applications will appear here when you apply to meet a pet.</p>';
  }

  const applicationCount = applications.length;
  document.getElementById('applicationCount')?.replaceChildren(document.createTextNode(String(applicationCount)));
  document.getElementById('applicationBadge')?.replaceChildren(document.createTextNode(String(applicationCount)));
  const adoptionCount = applications.filter((application) => application.status === 'Completed').length;
  document.getElementById('adoptionCount')?.replaceChildren(document.createTextNode(String(adoptionCount)));
  document.getElementById('profileAdoptionCount')?.replaceChildren(document.createTextNode(String(adoptionCount)));
}

function renderActivity() {
  const activity = document.getElementById('recentActivity');
  if (!activity) return;
  const items = applications.slice().reverse().slice(0, 4);
  activity.innerHTML = items.length ? items.map((application) => `
    <div class="activity-item"><span class="activity-dot purple-dot"></span><div>
      <strong>Adoption request ${escapeHtml((application.status || 'Under Review').toLowerCase())}</strong>
      <p>You applied to meet ${escapeHtml(application.petName)}.</p><small>${escapeHtml(formatDate(application.date))}</small>
    </div></div>`).join('') : '<p class="empty-state">Your adoption updates will appear here.</p>';
}

function renderProfile() {
  const name = profile.name || '';
  const initials = name.trim().split(/\s+/).map((part) => part[0] || '').slice(0, 2).join('').toUpperCase();
  const firstName = name.trim().split(/\s+/)[0] || 'Adopter';
  document.querySelector('.user-summary strong')?.replaceChildren(document.createTextNode(name));
  document.querySelector('.avatar')?.replaceChildren(document.createTextNode(initials));
  document.querySelector('.large-avatar')?.replaceChildren(document.createTextNode(initials));
  document.querySelector('.profile-card h2')?.replaceChildren(document.createTextNode(name));
  const greeting = document.querySelector('#overview .page-heading h1');
  if (greeting) greeting.textContent = `Welcome back, ${firstName}! 👋`;
  document.getElementById('profileName').value = name;
  document.getElementById('profileEmail').value = profile.email || '';
  document.getElementById('profilePhone').value = profile.phone || '';
  document.getElementById('profileAddress').value = profile.address || '';
  const location = document.querySelector('.profile-location');
  if (location) location.textContent = profile.address ? `📍 ${profile.address}` : 'Add a city or region to your profile';
  document.getElementById('memberSince')?.replaceChildren(document.createTextNode(profile.memberSince || 'Recently'));
}

function renderMessages() {
  const chat = document.getElementById('chatMessages');
  if (!chat) return;
  const errorNotice = document.getElementById('messageError');
  if (errorNotice) {
    errorNotice.hidden = !messagingError;
    errorNotice.textContent = messagingError;
  }
  const list = document.getElementById('conversationContacts');
  const active = conversations.find((item) => item.id === activeConversationId);
  const sellerPetSelect = document.getElementById('messagePetSelect');
  const startButton = document.getElementById('startSellerConversation');
  const search = (document.getElementById('conversationSearch')?.value || '').trim().toLowerCase();
  const sellerPets = pets.filter((pet) => pet.ownerId && pet.ownerId !== profile.id && pet.status !== 'sold');
  const messageInput = document.getElementById('messageInput');
  const sendButton = document.querySelector('#messageForm button[type="submit"]');
  const listingCard = document.getElementById('chatListingCard');
  const wasNearBottom = chat.scrollHeight - chat.scrollTop - chat.clientHeight < 48;

  if (sellerPetSelect) {
    sellerPetSelect.innerHTML = sellerPets.length
      ? '<option value="">Choose a pet listed by a seller</option>' + sellerPets.map((pet) => `<option value="${escapeHtml(pet.id)}">${escapeHtml(pet.name)} · ${escapeHtml(pet.breed)}</option>`).join('')
      : '<option value="">No other members’ listings yet</option>';
    sellerPetSelect.disabled = !sellerPets.length;
    if (startButton) startButton.disabled = !sellerPets.length || !sellerPetSelect.value;
  }
  if (list) {
    const matchingConversations = conversations
      .filter((item) => {
        const otherName = item.buyerId === profile.id ? item.sellerName : item.buyerName;
        return `${otherName || ''} ${item.petName || ''} ${item.messages?.at(-1)?.text || ''}`.toLowerCase().includes(search);
      })
      .sort((a, b) => {
        const latestA = Date.parse(a.messages?.at(-1)?.sentAt || a.createdAt || '') || 0;
        const latestB = Date.parse(b.messages?.at(-1)?.sentAt || b.createdAt || '') || 0;
        return latestB - latestA;
      });
    const count = document.getElementById('conversationCount');
    if (count) count.textContent = `${conversations.length} ${conversations.length === 1 ? 'conversation' : 'conversations'}`;
    const unreadCount = conversations.reduce((total, item) => total + (item.id === activeConversationId ? 0 : Number(item.unreadCount || 0)), 0);
    const badge = document.getElementById('messageBadge');
    if (badge) badge.textContent = unreadCount ? String(unreadCount) : '';
    list.innerHTML = matchingConversations.map((item) => {
      const otherName = item.buyerId === profile.id ? item.sellerName : item.buyerName;
      const lastMessage = item.messages?.at(-1);
      const preview = (lastMessage?.text || 'Your conversation is ready').replace(/\s+/g, ' ');
      const unread = Number(item.unreadCount || 0) > 0 && item.id !== activeConversationId;
      const sentAt = lastMessage?.sentAt ? new Date(lastMessage.sentAt) : null;
      const time = sentAt && !Number.isNaN(sentAt.getTime())
        ? sentAt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
        : '';
      return `<button class="conversation${item.id === activeConversationId ? ' active' : ''}${unread ? ' unread' : ''}" data-conversation="${escapeHtml(item.id)}"><span class="message-avatar">${escapeHtml((otherName || 'P').slice(0, 1).toUpperCase())}</span><span class="conversation-copy"><span class="conversation-line"><strong>${escapeHtml(otherName || 'Paw Net member')}</strong><time>${escapeHtml(time)}</time></span><span class="conversation-pet">About ${escapeHtml(item.petName)}</span><span class="conversation-preview">${escapeHtml(preview)}</span></span>${unread ? '<span class="conversation-unread" aria-label="Unread message"></span>' : ''}</button>`;
    }).join('');
    if (!matchingConversations.length) {
      const emptyTitle = messagingError ? 'Chat temporarily unavailable' : search ? 'No chats found' : 'No conversations yet';
      const emptyMessage = messagingError
        ? 'Start MySQL in XAMPP and import database/schema.sql, then refresh this page.'
        : search
        ? 'Try searching another name or pet.'
        : sellerPets.length
          ? 'Choose one of the available listings below to message its seller.'
          : 'A different Paw Net member needs to publish an available pet before you can start a private chat.';
      list.innerHTML = `<div class="conversation-empty"><span aria-hidden="true">${search ? '⌕' : '🐾'}</span><strong>${emptyTitle}</strong><p>${emptyMessage}</p></div>`;
    }
  }
  const header = document.querySelector('.chat-header');
  if (header) {
    const otherName = active ? (active.buyerId === profile.id ? active.sellerName : active.buyerName) : '';
    header.innerHTML = `<span class="message-avatar">${active ? escapeHtml((otherName || 'P').slice(0, 1).toUpperCase()) : '🐾'}</span><span class="chat-person"><strong>${active ? escapeHtml(otherName || 'Paw Net member') : 'Select a conversation'}</strong><span>${active ? `Chatting about ${escapeHtml(active.petName)}` : 'Private chat with a pet seller'}</span></span><span class="chat-header-status"><span></span> PRIVATE CHAT</span>`;
  }
  if (listingCard) {
    if (active) {
      const listing = active.listing || {};
      listingCard.hidden = false;
      listingCard.innerHTML = `<img src="${escapeHtml(listing.image || '')}" alt=""><span><small>ABOUT THIS PET</small><strong>${escapeHtml(active.petName)}</strong><span>${escapeHtml([listing.breed, listing.age ? `${listing.age} ${Number(listing.age) === 1 ? 'year' : 'years'}` : '', listing.sex].filter(Boolean).join(' · ') || 'Pet details are available in the listing')}</span></span><span class="listing-chat-status"><i></i> Adoption discussion</span>`;
    } else {
      listingCard.hidden = true;
      listingCard.replaceChildren();
    }
  }
  if (messageInput) messageInput.disabled = !active;
  if (messageInput && !active) messageInput.placeholder = 'Choose a conversation to start chatting…';
  if (messageInput && active) messageInput.placeholder = `Message ${active.buyerId === profile.id ? (active.sellerName || 'seller') : (active.buyerName || 'buyer')}…`;
  if (sendButton) sendButton.disabled = !active || !messageInput?.value.trim();
  const charCount = document.getElementById('messageCharCount');
  if (charCount && messageInput) charCount.textContent = `${messageInput.value.length} / 2000`;
  if (!active) {
    chat.innerHTML = '<div class="chat-welcome"><span>🐾</span><h2>Your next friendship starts with hello.</h2><p>Choose an existing conversation or start a chat about a pet listed by another member.</p></div>';
  } else {
    chat.innerHTML = (active.messages || []).map((message) => {
      const own = message.senderId === profile.id;
      const senderName = own ? profile.name : (active.buyerId === profile.id ? active.sellerName : active.buyerName) || 'Paw Net member';
      const sentAt = new Date(message.sentAt);
      const time = Number.isNaN(sentAt.getTime()) ? '' : sentAt.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
      return `<article class="message-row ${own ? 'mine' : ''}"><span class="message-avatar">${escapeHtml((senderName || 'P').slice(0, 1).toUpperCase())}</span><div class="message-content"><span class="message-sender">${escapeHtml(own ? 'You' : message.senderName || senderName)}</span><div class="bubble ${own ? 'sent' : 'received'}">${escapeHtml(message.text).replace(/\n/g, '<br>')}</div><time>${escapeHtml(time)}</time></div></article>`;
    }).join('') || '<p class="chat-no-messages">Say hello and start your adoption conversation.</p>';
  }
  list?.querySelectorAll('[data-conversation]')?.forEach((button) => button.addEventListener('click', async () => {
    activeConversationId = button.dataset.conversation;
    renderMessages();
    try {
      const result = await apiRequest('api/messages.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'read', conversationId: activeConversationId })
      });
      const index = conversations.findIndex((conversation) => conversation.id === activeConversationId);
      if (index >= 0) conversations[index] = result.conversation;
      renderMessages();
    } catch (error) {
      showToast(error.message || 'Unable to mark messages as read.');
    }
  }));
  if (active && wasNearBottom) chat.scrollTop = chat.scrollHeight;
}

function renderSettings() {
  document.querySelectorAll('[data-setting]').forEach((input) => {
    input.checked = Boolean(settings[input.dataset.setting]);
  });
  document.body.classList.toggle('dark', Boolean(settings.darkMode));
}

function renderDashboardData(data) {
  profile = data.profile || {};
  favorites = new Set(data.favorites || []);
  applications = data.applications || [];
  messages = data.messages || [];
  settings = data.settings || {};

  renderProfile();
  renderPetGrid();
  renderApplications();
  renderActivity();
  renderMessages();
  renderSettings();
  document.getElementById('favoriteCount')?.replaceChildren(document.createTextNode(String(favorites.size)));
}

async function loadDashboard() {
  try {
    const [petData, dashboardData] = await Promise.all([
      apiRequest('api/pets.php'),
      apiRequest('api/dashboard-data.php')
    ]);
    pets = Array.isArray(petData) ? petData : (petData.pets || []);
    document.getElementById('availablePetCount')?.replaceChildren(document.createTextNode(String(pets.length)));
    renderDashboardData(dashboardData);
    try {
      const messageData = await apiRequest('api/messages.php');
      conversations = messageData.conversations || [];
      messagingError = '';
    } catch (error) {
      conversations = [];
      messagingError = error.message || 'Unable to connect to the messaging database.';
    }
    renderMessages();
    if (messagingError) showToast(messagingError);
  } catch (error) {
    console.error(error);
    showToast(error.message || 'Unable to load your dashboard data.');
  }
}

function showSection(sectionId) {
  const section = document.getElementById(sectionId);
  if (!section) return;
  document.querySelectorAll('.dashboard-section').forEach((item) => item.classList.toggle('active', item === section));
  document.querySelectorAll('.nav-item').forEach((item) => item.classList.toggle('active', item.dataset.section === sectionId));
  document.getElementById('sidebar')?.classList.remove('open');
  document.getElementById('sidebarOverlay')?.classList.remove('open');
  if (sectionId === 'favorites') renderPetGrid();
  if (sectionId === 'applications') renderApplications();
  if (sectionId === 'messages') renderMessages();
}

function setupSectionNavigation() {
  document.querySelectorAll('[data-section], [data-section-target]').forEach((button) => {
    button.addEventListener('click', () => showSection(button.dataset.section || button.dataset.sectionTarget));
  });
}

async function setFavorite(petName, favorite) {
  try {
    const result = await apiRequest('api/favorites.php', {
      method: 'POST',
      body: JSON.stringify({ petName, favorite })
    });
    favorites = new Set(result.favorites || []);
    renderPetGrid();
    document.getElementById('favoriteCount')?.replaceChildren(document.createTextNode(String(favorites.size)));
    showToast(favorite ? `${petName} saved to your favorites.` : `${petName} removed from your favorites.`);
  } catch (error) {
    console.error(error);
    showToast(error.message || 'Could not update favorites.');
  }
}

function openPet(petIdentifier) {
  const pet = pets.find((item) => item.id === petIdentifier) || petByName(petIdentifier);
  if (!pet) return;
  selectedPet = pet;
  document.getElementById('modalPetImage').src = pet.image;
  document.getElementById('modalPetImage').alt = pet.name;
  document.getElementById('modalPetName').textContent = pet.name;
  document.getElementById('modalPetMeta').textContent = `${pet.breed} · ${pet.age} ${pet.age === 1 ? 'year' : 'years'} old · ${pet.sex ? `${pet.sex[0].toUpperCase()}${pet.sex.slice(1)}` : 'Sex not specified'} · ${pet.type}`;
  document.getElementById('modalPetDescription').textContent = pet.description;
  const pickup = document.getElementById('modalPickupLocation');
  const inquiryWrap = document.getElementById('modalInquiryWrap');
  const inquiryText = document.getElementById('modalInquiryMessage');
  const isOwnListing = Boolean(pet.ownerId && pet.ownerId === profile.id);
  pickup.hidden = !pet.ownerId;
  pickup.textContent = isOwnListing
    ? 'This is your listing. You can manage it from your seller dashboard.'
    : pet.ownerId ? '📍 The seller’s pickup address will be included privately in your conversation.' : '';
  inquiryWrap.hidden = !pet.ownerId || isOwnListing;
  inquiryText.value = pet.ownerId ? `Hi, I'm interested in adopting ${pet.name}. Could you tell me more about their health, personality, and routine, and let me know when we could arrange a meet-and-greet?` : '';
  const applyButton = document.getElementById('modalApply');
  applyButton.disabled = isOwnListing;
  applyButton.textContent = isOwnListing ? 'Your listing' : pet.ownerId ? 'Send adoption inquiry' : 'Apply to Adopt';
  const favoriteButton = document.getElementById('modalFavorite');
  favoriteButton.textContent = favorites.has(pet.name) ? '♥ Saved' : '♡ Save Favorite';
  document.getElementById('petModal').classList.add('open');
}

function setupPetActions() {
  document.addEventListener('click', async (event) => {
    const target = event.target.closest?.('[data-adopt], [data-favorite]');
    if (!target) return;
    if (target.hasAttribute('data-adopt')) {
      openPet(target.dataset.adopt);
      return;
    }
    const name = target.dataset.favorite;
    await setFavorite(name, !favorites.has(name));
  });

  document.getElementById('closePetModal')?.addEventListener('click', () => document.getElementById('petModal')?.classList.remove('open'));
  document.getElementById('petModal')?.addEventListener('click', (event) => {
    if (event.target.id === 'petModal') event.currentTarget.classList.remove('open');
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') document.getElementById('petModal')?.classList.remove('open');
  });
  document.getElementById('modalFavorite')?.addEventListener('click', async () => {
    const name = document.getElementById('modalPetName')?.textContent;
    if (!name) return;
    await setFavorite(name, !favorites.has(name));
    const button = document.getElementById('modalFavorite');
    button.textContent = favorites.has(name) ? '♥ Saved' : '♡ Save Favorite';
  });
  document.getElementById('modalApply')?.addEventListener('click', async () => {
    const petName = document.getElementById('modalPetName')?.textContent;
    if (!petName || !selectedPet) return;
    try {
      if (selectedPet?.ownerId && selectedPet.id) {
        const result = await apiRequest('api/messages.php', { method: 'POST', body: JSON.stringify({ action: 'start', petId: selectedPet.id, message: document.getElementById('modalInquiryMessage')?.value.trim() }) });
        const existingIndex = conversations.findIndex((conversation) => conversation.id === result.conversation.id);
        if (existingIndex >= 0) conversations[existingIndex] = result.conversation;
        else conversations.push(result.conversation);
        activeConversationId = result.conversation.id;
        renderMessages();
        showSection('messages');
        showToast(result.message);
        document.getElementById('petModal')?.classList.remove('open');
        return;
      }
      await apiRequest('api/apply.php', {
        method: 'POST',
        body: JSON.stringify(selectedPet.id ? { petId: selectedPet.id } : { petName })
      });
      const result = await apiRequest('api/dashboard-data.php');
      applications = result.applications || [];
      renderApplications();
      renderActivity();
      showToast(`Your adoption request for ${petName} was submitted.`);
    } catch (error) {
      showToast(error.message || 'Unable to submit your adoption request.');
    }
    document.getElementById('petModal')?.classList.remove('open');
  });
}

function setupFilters() {
  document.getElementById('petSearch')?.addEventListener('input', renderPetGrid);
  document.getElementById('typeFilter')?.addEventListener('change', renderPetGrid);
  document.getElementById('ageFilter')?.addEventListener('change', renderPetGrid);
  document.getElementById('petSort')?.addEventListener('change', renderPetGrid);
  document.querySelectorAll('[data-pet-type]').forEach((button) => {
    button.addEventListener('click', () => {
      document.getElementById('typeFilter').value = button.dataset.petType;
      renderPetGrid();
    });
  });
  document.getElementById('clearPetFilters')?.addEventListener('click', () => {
    document.getElementById('petSearch').value = '';
    document.getElementById('globalSearch').value = '';
    document.getElementById('typeFilter').value = 'all';
    document.getElementById('ageFilter').value = 'all';
    document.getElementById('petSort').value = 'recommended';
    renderPetGrid();
  });
  document.getElementById('surprisePet')?.addEventListener('click', () => {
    const matches = filteredPets();
    if (!matches.length) {
      showToast('No pets match those filters yet. Try clearing a filter.');
      return;
    }
    openPet(matches[Math.floor(Math.random() * matches.length)].name);
  });
  document.getElementById('globalSearch')?.addEventListener('input', () => {
    const section = document.getElementById('find-pets');
    if (!section.classList.contains('active')) showSection('find-pets');
    const petSearch = document.getElementById('petSearch');
    petSearch.value = document.getElementById('globalSearch').value;
    renderPetGrid();
  });
}

function setupForms() {
  const messageInput = document.getElementById('messageInput');
  const messageForm = document.getElementById('messageForm');
  const messageButton = messageForm?.querySelector('button[type="submit"]');
  const updateComposer = () => {
    if (!messageInput) return;
    messageInput.style.height = 'auto';
    messageInput.style.height = `${Math.min(messageInput.scrollHeight, 120)}px`;
    const counter = document.getElementById('messageCharCount');
    if (counter) counter.textContent = `${messageInput.value.length} / 2000`;
    if (messageButton) messageButton.disabled = !activeConversationId || !messageInput.value.trim();
  };
  messageInput?.addEventListener('input', updateComposer);
  messageInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
      event.preventDefault();
      messageForm?.requestSubmit();
    }
  });
  document.getElementById('messagePetSelect')?.addEventListener('change', () => {
    const select = document.getElementById('messagePetSelect');
    const button = document.getElementById('startSellerConversation');
    if (button) button.disabled = !select?.value;
  });
  document.getElementById('conversationSearch')?.addEventListener('input', renderMessages);

  document.getElementById('profileForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
      const result = await apiRequest('api/profile.php', {
        method: 'POST',
        body: JSON.stringify({
          name: document.getElementById('profileName').value,
          email: document.getElementById('profileEmail').value,
          phone: document.getElementById('profilePhone').value,
          address: document.getElementById('profileAddress').value
        })
      });
      profile = { ...profile, ...result.profile };
      renderProfile();
      showToast('Your profile has been saved.');
    } catch (error) {
      showToast(error.message || 'Unable to save your profile.');
    } finally {
      submit.disabled = false;
    }
  });

  document.getElementById('startSellerConversation')?.addEventListener('click', async () => {
    const select = document.getElementById('messagePetSelect');
    const pet = pets.find((item) => item.id === select?.value);
    if (!pet) {
      showToast('Choose a seller-listed pet to start a conversation.');
      return;
    }
    const button = document.getElementById('startSellerConversation');
    button.disabled = true;
    try {
      const result = await apiRequest('api/messages.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'start', petId: pet.id, message: `Hi, I want to adopt ${pet.name}. Could you share more details and let me know when we could meet?` })
      });
      const index = conversations.findIndex((item) => item.id === result.conversation.id);
      if (index >= 0) conversations[index] = result.conversation;
      else conversations.push(result.conversation);
      activeConversationId = result.conversation.id;
      messagingError = '';
      renderMessages();
      showToast(result.message || 'Your inquiry was sent to the seller.');
    } catch (error) {
      messagingError = error.message || 'Unable to start a conversation.';
      renderMessages();
      showToast(error.message || 'Unable to send your inquiry to the seller.');
    } finally {
      button.disabled = false;
    }
  });

  document.getElementById('messageForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    if (!message) return;
    if (!activeConversationId) {
      showToast('Choose a seller conversation before sending a message.');
      return;
    }
    const submit = event.currentTarget.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
      const result = await apiRequest('api/messages.php', { method: 'POST', body: JSON.stringify({ action: 'reply', conversationId: activeConversationId, message }) });
      const index = conversations.findIndex((conversation) => conversation.id === activeConversationId);
      if (index >= 0) conversations[index] = result.conversation;
      messagingError = '';
      input.value = '';
      renderMessages();
    } catch (error) {
      messagingError = error.message || 'Unable to send your message.';
      renderMessages();
      showToast(error.message || 'Unable to send your message.');
    } finally {
      submit.disabled = !activeConversationId || !input.value.trim();
    }
  });

  window.setInterval(async () => {
    try {
      const result = await apiRequest('api/messages.php');
      conversations = result.conversations || [];
      messagingError = '';
      renderMessages();
      const inboxOpen = document.getElementById('messages')?.classList.contains('active');
      const active = conversations.find((conversation) => conversation.id === activeConversationId);
      if (inboxOpen && active && Number(active.unreadCount || 0) > 0) {
        const readResult = await apiRequest('api/messages.php', {
          method: 'POST',
          body: JSON.stringify({ action: 'read', conversationId: activeConversationId })
        });
        const index = conversations.findIndex((conversation) => conversation.id === activeConversationId);
        if (index >= 0) conversations[index] = readResult.conversation;
        renderMessages();
      }
    } catch (error) {
      messagingError = error.message || 'Unable to refresh your conversations.';
      renderMessages();
    }
  }, 7000);

  document.querySelectorAll('[data-setting]').forEach((input) => {
    input.addEventListener('change', async () => {
      const previous = !input.checked;
      const nextSettings = { ...settings, [input.dataset.setting]: input.checked };
      try {
        const result = await apiRequest('api/preferences.php', { method: 'POST', body: JSON.stringify({ settings: nextSettings }) });
        settings = result.settings;
        renderSettings();
        showToast('Your preferences have been saved.');
      } catch (error) {
        input.checked = previous;
        document.body.classList.toggle('dark', Boolean(settings.darkMode));
        showToast(error.message || 'Unable to save your preferences.');
      }
    });
  });
}

function setupMobileMenu() {
  const button = document.getElementById('mobileMenu');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  button?.addEventListener('click', () => {
    sidebar?.classList.toggle('open');
    overlay?.classList.toggle('open');
  });
  overlay?.addEventListener('click', () => {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('open');
  });
}

async function initializeDashboard() {
  setupSectionNavigation();
  setupPetActions();
  setupFilters();
  setupForms();
  setupMobileMenu();
  document.getElementById('contactTeamBtn')?.addEventListener('click', () => showSection('messages'));
  document.getElementById('profileMenuBtn')?.addEventListener('click', () => showSection('profile'));
  document.getElementById('notificationBtn')?.addEventListener('click', () => {
    showSection('applications');
    showToast(applications.length ? `You have ${applications.length} adoption request${applications.length === 1 ? '' : 's'}.` : 'You have no adoption updates yet.');
  });
  await loadDashboard();
}

document.addEventListener('DOMContentLoaded', initializeDashboard);
