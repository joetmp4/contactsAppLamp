const contactsMessage = document.querySelector('#contacts-message');
const contactsList = document.querySelector('#contacts-list');
const welcomeMessage = document.querySelector('#welcome-message');
const adminLink = document.querySelector('#admin-link');
const logoutButton = document.querySelector('#logout-button');
const searchInput = document.querySelector('#search-input');
const addContactForm = document.querySelector('#add-contact-form');
const addContactMessage = document.querySelector('#add-contact-message');

const token = sessionStorage.getItem('token');

if (!token) {
    window.location.replace('./index.html');
}

if (sessionStorage.getItem('isAdmin') === 'true') {
    adminLink.hidden = false;
}

const firstName = sessionStorage.getItem('firstName');
welcomeMessage.textContent = firstName ? `Welcome, ${firstName}!` : 'Welcome!';

function authHeaders(extra = {}) {
    return Object.assign({ 'Authorization': `Bearer ${token}` }, extra);
}

function handleAuthFailure(response) {
    if (response.status === 401) {
        sessionStorage.clear();
        window.location.replace('./index.html');
        return true;
    }
    return false;
}

function escapeAttr(value) {
    return String(value).replace(/"/g, '&quot;');
}

function buildContactItem(contact) {
    const item = document.createElement('li');
    item.className = 'contact-card';

    const info = document.createElement('div');
    info.className = 'contact-info';

    const name = document.createElement('h3');
    name.textContent = `${contact.firstName} ${contact.lastName}`;

    const email = document.createElement('p');
    email.textContent = `Email: ${contact.email || 'Not provided'}`;

    const phone = document.createElement('p');
    phone.textContent = `Phone: ${contact.phone || 'Not provided'}`;

    info.append(name, email, phone);

    const actions = document.createElement('div');
    actions.className = 'contact-actions';

    const editButton = document.createElement('button');
    editButton.type = 'button';
    editButton.textContent = 'Edit';
    editButton.addEventListener('click', () => showEditForm(item, contact));

    const deleteButton = document.createElement('button');
    deleteButton.type = 'button';
    deleteButton.className = 'danger';
    deleteButton.textContent = 'Delete';
    deleteButton.addEventListener('click', () => deleteContact(contact.id));

    actions.append(editButton, deleteButton);
    item.append(info, actions);
    return item;
}

function showEditForm(item, contact) {
    item.innerHTML = '';

    const form = document.createElement('form');
    form.className = 'edit-form';
    form.innerHTML = `
        <label>First Name<input type="text" name="firstName" value="${escapeAttr(contact.firstName)}" maxlength="50" required /></label>
        <label>Last Name<input type="text" name="lastName" value="${escapeAttr(contact.lastName)}" maxlength="50" required /></label>
        <label>Email<input type="email" name="email" value="${escapeAttr(contact.email || '')}" maxlength="100" /></label>
        <label>Phone<input type="tel" name="phone" value="${escapeAttr(contact.phone || '')}" maxlength="20" /></label>
        <div class="contact-actions">
            <button type="submit">Save</button>
            <button type="button" class="secondary" data-cancel>Cancel</button>
        </div>
        <p class="form-message" role="status"></p>
    `;

    form.querySelector('[data-cancel]').addEventListener('click', () => loadContacts(searchInput.value.trim()));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formMessage = form.querySelector('.form-message');
        formMessage.textContent = 'Saving...';

        const updated = {
            firstName: form.firstName.value.trim(),
            lastName: form.lastName.value.trim(),
            email: form.email.value.trim(),
            phone: form.phone.value.trim()
        };

        try {
            const response = await fetch(`./api/index.php?id=${contact.id}`, {
                method: 'PUT',
                headers: authHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(updated)
            });

            if (handleAuthFailure(response)) return;

            const result = await response.json();
            if (!response.ok) {
                formMessage.textContent = result.error || 'Could not save changes.';
                return;
            }

            loadContacts(searchInput.value.trim());
        } catch (error) {
            formMessage.textContent = 'Could not save changes. Please try again.';
        }
    });

    item.append(form);
}

async function deleteContact(id) {
    if (!window.confirm('Delete this contact? This cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch(`./api/index.php?id=${id}`, {
            method: 'DELETE',
            headers: authHeaders()
        });

        if (handleAuthFailure(response)) return;

        if (!response.ok) {
            const result = await response.json();
            contactsMessage.textContent = result.error || 'Could not delete contact.';
            return;
        }

        loadContacts(searchInput.value.trim());
    } catch (error) {
        contactsMessage.textContent = 'Could not delete contact. Please try again.';
    }
}

function renderContacts(contacts) {
    contactsList.innerHTML = '';

    if (contacts.length === 0) {
        contactsMessage.textContent = 'No contacts found.';
        return;
    }

    contactsMessage.textContent = '';
    for (const contact of contacts) {
        contactsList.append(buildContactItem(contact));
    }
}

async function loadContacts(search = '') {
    contactsMessage.textContent = 'Loading contacts...';

    try {
        const url = search
            ? `./api/index.php?q=${encodeURIComponent(search)}`
            : './api/index.php';

        const response = await fetch(url, {
            headers: authHeaders(),
            cache: 'no-store'
        });

        if (handleAuthFailure(response)) return;

        if (!response.ok) {
            contactsMessage.textContent = 'Unable to load contacts. Please try again later.';
            return;
        }

        const result = await response.json();

        if (!Array.isArray(result.contacts)) {
            contactsMessage.textContent = 'The server returned an unexpected response.';
            return;
        }

        renderContacts(result.contacts);
    } catch (error) {
        contactsMessage.textContent = 'Unable to load contacts. Please try again later.';
    }
}

let searchTimeout;
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadContacts(searchInput.value.trim()), 300);
});

addContactForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    addContactMessage.textContent = 'Adding contact...';

    const newContact = {
        firstName: document.querySelector('#add-firstName').value.trim(),
        lastName: document.querySelector('#add-lastName').value.trim(),
        email: document.querySelector('#add-email').value.trim(),
        phone: document.querySelector('#add-phone').value.trim()
    };

    try {
        const response = await fetch('./api/index.php', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(newContact)
        });

        if (handleAuthFailure(response)) return;

        const result = await response.json();
        if (!response.ok) {
            addContactMessage.textContent = result.error || 'Could not add contact.';
            return;
        }

        addContactMessage.textContent = 'Contact added.';
        addContactForm.reset();
        loadContacts(searchInput.value.trim());
    } catch (error) {
        addContactMessage.textContent = 'Could not add contact. Please try again.';
    }
});

logoutButton.addEventListener('click', async () => {
    try {
        await fetch('./api/index.php?action=logout', {
            method: 'POST',
            headers: authHeaders()
        });
    } catch (error) {
        // Ignore network errors on logout - clear the local session regardless.
    }
    sessionStorage.clear();
    window.location.replace('./index.html');
});

loadContacts();
