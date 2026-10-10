let pets = [];
let favorites = new Set();
let applications = [];
let messages = [];
<<<<<<< HEAD
let conversations = [];
let activeConversationId = '';
=======
>>>>>>> 2fe54b1b48e42ea676b505281f68d6bd526c71b7
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
  const species = String(pet.type || '').toLowerCase();
  return `
    <article class="${compact ? 'dashboard-pet' : 'full-pet-card'}">
      <div class="pet-image-wrap">
        <img src="${escapeHtml(pet.image)}" alt="${escapeHtml(pet.name)}" loading="lazy">
        <button class="heart-btn${liked ? ' liked' : ''}" data-favorite="${escapeHtml(pet.name)}" aria-label="${liked ? 'Remove' : 'Add'} ${escapeHtml(pet.name)} ${liked ? 'from' : 'to'} favorites" aria-pressed="${liked}">${liked ? '♥' : '♡'}</button>
        <span class="species-chip ${escapeHtml(species)}">${escapeHtml(species)}</span>
      </div>
      <div class="${compact ? 'pet-body' : 'full-pet-card-body'}">
        <div class="pet-card-title"><h3>${escapeHtml(pet.name)}</h3><span class="pet-tag">${escapeHtml(pet.breed)}</span></div>
        <p>${escapeHtml(pet.age)} ${Number(pet.age) === 1 ? 'year' : 'years'} old</p>
        <button class="${compact ? 'small-primary' : 'primary-btn'}" data-adopt="${escapeHtml(pet.name)}">Meet ${escapeHtml(pet.name)}</button>
      </div>
    </article>`;
}

function renderPetGrid() {
  const grid = document.getElementById('fullPetGrid');
  const favoriteGrid = document.getElementById('favoriteGrid');
  const searchValue = (document.getElementById('petSearch')?.value || document.getElementById('globalSearch')?.value || '').trim().toLowerCase();
  const typeValue = document.getElementById('typeFilter')?.value || 'all';
  const ageValue = document.getElementById('ageFilter')?.value || 'all';

  const filtered = pets.filter((pet) => {
    const matchesSearch = `${pet.name} ${pet.breed} ${pet.type}`.toLowerCase().includes(searchValue);
    const matchesType = typeValue === 'all' || pet.type === typeValue;
    const matchesAge = ageValue === 'all' || (ageValue === 'young' && pet.age <= 2) || (ageValue === 'adult' && pet.age >= 3);
    return matchesSearch && matchesType && matchesAge;
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
    const picks = ['dog', 'cat', 'hamster'].map((species) => pets.find((pet) => pet.type === species)).filter(Boolean);
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
<<<<<<< HEAD
  const list = document.getElementById('conversationContacts');
  const active = conversations.find((item) => item.id === activeConversationId);
  const sellerPets = pets.filter((pet) => pet.ownerId && pet.status !== 'sold' && pet.ownerId !== profile.id);
  if (sellerPetSelect) {
    const sellerPets = pets.filter((pet) => pet.ownerId && pet.status !== 'sold');
    sellerPetSelect.innerHTML = '<option value="">Choose a pet listed by a seller</option>' + sellerPets.map((pet) => `<option value="${escapeHtml(pet.id)}">${escapeHtml(pet.name)} · ${escapeHtml(pet.breed)}</option>`).join('');
  }
  if (list) {
    list.innerHTML = conversations.map((item) => {
      const otherName = item.buyerId === profile.id ? item.sellerName : item.buyerName;
      const lastMessage = item.messages?.[item.messages.length - 1]?.text || 'Start your conversation';
      return `<button class="conversation${item.id === activeConversationId ? ' active' : ''}" data-conversation="${escapeHtml(item.id)}"><div class="message-avatar">${escapeHtml((otherName || 'P').slice(0, 1).toUpperCase())}</div><div><strong>${escapeHtml(otherName)}</strong><p>About ${escapeHtml(item.petName)}</p><small>${escapeHtml(lastMessage)}</small></div></button>`;
    }).join('');
    if (!conversations.length) list.innerHTML = '<p class="empty-state">No seller conversations yet. Choose a seller-listed pet above, then message them here.</p>';
  }
  const header = document.querySelector('.chat-header');
  if (header) header.innerHTML = `<div class="message-avatar">${active ? escapeHtml(((active.buyerId === profile.id ? active.sellerName : active.buyerName) || 'P').slice(0, 1).toUpperCase()) : '🐾'}</div><div><strong>${active ? escapeHtml(active.buyerId === profile.id ? active.sellerName : active.buyerName) : 'Choose a seller conversation'}</strong><span>${active ? `About ${escapeHtml(active.petName)} · ${escapeHtml(active.listing?.sex || '')} · Pickup: ${escapeHtml(active.listing?.pickupLocation || 'confirm with seller')}` : 'Your messages go directly to the pet seller you choose.'}</span></div>`;
  const messageInput = document.getElementById('messageInput');
  const sendButton = document.querySelector('#messageForm button[type="submit"]');
  if (messageInput) messageInput.disabled = !active;
  if (sendButton) sendButton.disabled = !active;
  if (!active) chat.innerHTML = '<p class="empty-state">Choose a conversation or start one about a seller-listed pet. Your messages will be sent directly to that seller.</p>';
  else chat.innerHTML = (active.messages || []).map((message) => `<div class="bubble ${message.senderId === profile.id ? 'sent' : 'received'}">${escapeHtml(message.text).replace(/\n/g, '<br>')}</div>`).join('');
  list?.querySelectorAll('[data-conversation]')?.forEach((button) => button.addEventListener('click', () => { activeConversationId = button.dataset.conversation; renderMessages(); }));
  if (active) chat.scrollTop = chat.scrollHeight;
=======
  if (!messages.length) {
    chat.innerHTML = '<p class="empty-state">Welcome to Paw Net support. Send us a message if you need help with your adoption journey.</p>';
    return;
  }
  chat.innerHTML = messages.map((message) => `<div class="bubble ${message.sender === 'team' ? 'received' : 'sent'}">${escapeHtml(message.text)}</div>`).join('');
  chat.scrollTop = chat.scrollHeight;
>>>>>>> 2fe54b1b48e42ea676b505281f68d6bd526c71b7
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
<<<<<<< HEAD
const unread = conversations.reduce((n, c) => {
  const last = c.messages?.[c.messages.length - 1];
  return last && last.senderId !== profile.id ? n + 1 : n;
}, 0);
document.getElementById('messageBadge')?.replaceChildren(document.createTextNode(unread ? String(unread) : ''));seller-inbox.js}

async function loadDashboard() {
  try {
    const [petData, dashboardData, messageData] = await Promise.all([
      apiRequest('api/pets.php'),
      apiRequest('api/dashboard-data.php'),
      apiRequest('api/messages.php')
    ]);
    pets = Array.isArray(petData) ? petData : (petData.pets || []);
    conversations = messageData.conversations || [];
=======
  document.getElementById('messageBadge')?.replaceChildren(document.createTextNode(String(messages.length)));
}

async function loadDashboard() {
  try {
    const [petData, dashboardData] = await Promise.all([
      apiRequest('api/pets.php'),
      apiRequest('api/dashboard-data.php')
    ]);
    pets = Array.isArray(petData) ? petData : (petData.pets || []);
>>>>>>> 2fe54b1b48e42ea676b505281f68d6bd526c71b7
    document.getElementById('availablePetCount')?.replaceChildren(document.createTextNode(String(pets.length)));
    renderDashboardData(dashboardData);
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

function openPet(petName) {
  const pet = petByName(petName);
  if (!pet) return;
  document.getElementById('modalPetImage').src = pet.image;
  document.getElementById('modalPetImage').alt = pet.name;
  document.getElementById('modalPetName').textContent = pet.name;
<<<<<<< HEAD
  document.getElementById('modalPetMeta').textContent = `${pet.breed} · ${pet.age} ${pet.age === 1 ? 'year' : 'years'} old · ${pet.sex ? `${pet.sex[0].toUpperCase()}${pet.sex.slice(1)}` : 'Sex not specified'} · ${pet.type}`;
  document.getElementById('modalPetDescription').textContent = pet.description;
  const pickup = document.getElementById('modalPickupLocation');
  const inquiryWrap = document.getElementById('modalInquiryWrap');
  const inquiryText = document.getElementById('modalInquiryMessage');
  pickup.hidden = !pet.ownerId;
  pickup.textContent = pet.ownerId ? '📍 The seller’s pickup address will be included privately in your conversation.' : '';
  inquiryWrap.hidden = !pet.ownerId;
  inquiryText.value = pet.ownerId ? `Hi, I'm interested in adopting ${pet.name}. Could you tell me more about their health, personality, and routine, and let me know when we could arrange a meet-and-greet?` : '';
  document.getElementById('modalApply').textContent = pet.ownerId ? 'Send adoption inquiry' : 'Apply to Adopt';
=======
  document.getElementById('modalPetMeta').textContent = `${pet.breed} · ${pet.age} ${pet.age === 1 ? 'year' : 'years'} old · ${pet.type}`;
  document.getElementById('modalPetDescription').textContent = pet.description;
>>>>>>> 2fe54b1b48e42ea676b505281f68d6bd526c71b7
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
    if (!petName) return;
    try {
<<<<<<< HEAD
      const selectedPet = petByName(petName);
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
=======
>>>>>>> 2fe54b1b48e42ea676b505281f68d6bd526c71b7
      await apiRequest('api/apply.php', { method: 'POST', body: JSON.stringify({ petName }) });
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
  document.getElementById('globalSearch')?.addEventListener('input', () => {
    const section = document.getElementById('find-pets');
    if (!section.classList.contains('active')) showSection('find-pets');
    const petSearch = document.getElementById('petSearch');
    petSearch.value = document.getElementById('globalSearch').value;
    renderPetGrid();
  });
}

function setupForms() {
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

<<<<<<< HEAD
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
      renderMessages();
      showToast(result.message || 'Your inquiry was sent to the seller.');
    } catch (error) {
      showToast(error.message || 'Unable to send your inquiry to the seller.');
    } finally {
      button.disabled = false;
    }
  });

=======
>>>>>>> 2fe54b1b48e42ea676b505281f68d6bd526c71b7
  document.getElementById('messageForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    if (!message) return;
<<<<<<< HEAD
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
      input.value = '';
      renderMessages();
      document.getElementById('messageBadge')?.replaceChildren(document.createTextNode(String(messages.length + conversations.length)));
=======
    const submit = event.currentTarget.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
      const result = await apiRequest('api/messages.php', { method: 'POST', body: JSON.stringify({ message }) });
      messages.push(result.message);
      input.value = '';
      renderMessages();
      document.getElementById('messageBadge')?.replaceChildren(document.createTextNode(String(messages.length)));
>>>>>>> 2fe54b1b48e42ea676b505281f68d6bd526c71b7
    } catch (error) {
      showToast(error.message || 'Unable to send your message.');
    } finally {
      submit.disabled = false;
    }
  });

<<<<<<< HEAD
  window.setInterval(async () => {
    if (!document.getElementById('messages')?.classList.contains('active')) return;
    try {
      const result = await apiRequest('api/messages.php');
      conversations = result.conversations || [];
      renderMessages();
    } catch (error) {
      showToast(error.message || 'Unable to refresh your conversations.');
    }
  }, 7000);

=======
>>>>>>> 2fe54b1b48e42ea676b505281f68d6bd526c71b7
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
