const token = sessionStorage.getItem('token');
const isAdmin = sessionStorage.getItem('isAdmin') === 'true';

if (!token) {
    window.location.replace('./index.html');
}
if (!isAdmin) {
    window.location.replace('./contacts.html');
}

const welcomeMessage = document.querySelector('#welcome-message');
const logoutButton = document.querySelector('#logout-button');
const firstName = sessionStorage.getItem('firstName');
welcomeMessage.textContent = firstName ? `Welcome, ${firstName}!` : 'Welcome!';

const createAdminForm = document.querySelector('#create-admin-form');
const createAdminMessage = document.querySelector('#create-admin-message');

const userSearchInput = document.querySelector('#user-search-input');
const usersMessage = document.querySelector('#users-message');
const usersTable = document.querySelector('#users-table');
const usersTableBody = document.querySelector('#users-table-body');

const contactSearchInput = document.querySelector('#contact-search-input');
const allContactsMessage = document.querySelector('#all-contacts-message');
const contactsTable = document.querySelector('#contacts-table');
const contactsTableBody = document.querySelector('#contacts-table-body');

function authHeaders(extra = {}) {
    return Object.assign({ 'Authorization': `Bearer ${token}` }, extra);
}

function handleAuthFailure(response) {
    if (response.status === 401) {
        sessionStorage.clear();
        window.location.replace('./index.html');
        return true;
    }
    if (response.status === 403) {
        window.location.replace('./contacts.html');
        return true;
    }
    return false;
}

function buildUserRow(user) {
    const row = document.createElement('tr');

    const nameCell = document.createElement('td');
    nameCell.textContent = `${user.firstName} ${user.lastName}`;

    const usernameCell = document.createElement('td');
    usernameCell.textContent = user.username;

    const roleCell = document.createElement('td');
    roleCell.textContent = user.isAdmin ? 'Admin' : 'User';

    const statusCell = document.createElement('td');
    statusCell.textContent = user.isActive ? 'Active' : 'Disabled';

    const actionsCell = document.createElement('td');
    actionsCell.className = 'contact-actions';

    const toggleButton = document.createElement('button');
    toggleButton.type = 'button';
    toggleButton.textContent = user.isActive ? 'Disable' : 'Enable';
    toggleButton.className = user.isActive ? 'danger' : 'secondary';
    toggleButton.addEventListener('click', () => toggleActive(user));

    const resetButton = document.createElement('button');
    resetButton.type = 'button';
    resetButton.className = 'secondary';
    resetButton.textContent = 'Reset password';
    resetButton.addEventListener('click', () => resetPassword(user));

    actionsCell.append(toggleButton, resetButton);
    row.append(nameCell, usernameCell, roleCell, statusCell, actionsCell);
    return row;
}

async function loadUsers(search = '') {
    usersMessage.textContent = 'Loading users...';
    usersTable.hidden = true;

    try {
        const url = search
            ? `./api/index.php?action=admin_users&q=${encodeURIComponent(search)}`
            : './api/index.php?action=admin_users';

        const response = await fetch(url, { headers: authHeaders(), cache: 'no-store' });
        if (handleAuthFailure(response)) return;

        const result = await response.json();
        if (!response.ok || !Array.isArray(result.users)) {
            usersMessage.textContent = result.error || 'Unable to load users.';
            return;
        }

        if (result.users.length === 0) {
            usersMessage.textContent = 'No users found.';
            return;
        }

        usersMessage.textContent = '';
        usersTable.hidden = false;
        usersTableBody.innerHTML = '';
        for (const user of result.users) {
            usersTableBody.append(buildUserRow(user));
        }
    } catch (error) {
        usersMessage.textContent = 'Unable to load users. Please try again later.';
    }
}

async function toggleActive(user) {
    const nextActive = !user.isActive;
    const verb = nextActive ? 'enable' : 'disable';

    if (!window.confirm(`Are you sure you want to ${verb} ${user.username}?`)) {
        return;
    }

    try {
        const response = await fetch('./api/index.php?action=admin_toggle_active', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ userId: user.id, isActive: nextActive })
        });

        if (handleAuthFailure(response)) return;

        const result = await response.json();
        if (!response.ok) {
            window.alert(result.error || 'Could not update user.');
            return;
        }

        loadUsers(userSearchInput.value.trim());
    } catch (error) {
        window.alert('Could not update user. Please try again.');
    }
}

async function resetPassword(user) {
    const newPassword = window.prompt(`New password for ${user.username} (8+ characters):`);
    if (!newPassword) {
        return;
    }
    if (newPassword.length < 8) {
        window.alert('Password must be at least 8 characters.');
        return;
    }

    try {
        const response = await fetch('./api/index.php?action=admin_reset_password', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ userId: user.id, password: newPassword })
        });

        if (handleAuthFailure(response)) return;

        const result = await response.json();
        if (!response.ok) {
            window.alert(result.error || 'Could not reset password.');
            return;
        }

        window.alert('Password updated.');
    } catch (error) {
        window.alert('Could not reset password. Please try again.');
    }
}

function buildContactRow(contact) {
    const row = document.createElement('tr');

    const nameCell = document.createElement('td');
    nameCell.textContent = `${contact.firstName} ${contact.lastName}`;

    const emailCell = document.createElement('td');
    emailCell.textContent = contact.email || 'Not provided';

    const phoneCell = document.createElement('td');
    phoneCell.textContent = contact.phone || 'Not provided';

    const ownerCell = document.createElement('td');
    ownerCell.textContent = contact.ownerUsername;

    row.append(nameCell, emailCell, phoneCell, ownerCell);
    return row;
}

async function loadAllContacts(search = '') {
    allContactsMessage.textContent = 'Loading contacts...';
    contactsTable.hidden = true;

    try {
        const url = search
            ? `./api/index.php?action=admin_contacts&q=${encodeURIComponent(search)}`
            : './api/index.php?action=admin_contacts';

        const response = await fetch(url, { headers: authHeaders(), cache: 'no-store' });
        if (handleAuthFailure(response)) return;

        const result = await response.json();
        if (!response.ok || !Array.isArray(result.contacts)) {
            allContactsMessage.textContent = result.error || 'Unable to load contacts.';
            return;
        }

        if (result.contacts.length === 0) {
            allContactsMessage.textContent = 'No contacts found.';
            return;
        }

        allContactsMessage.textContent = '';
        contactsTable.hidden = false;
        contactsTableBody.innerHTML = '';
        for (const contact of result.contacts) {
            contactsTableBody.append(buildContactRow(contact));
        }
    } catch (error) {
        allContactsMessage.textContent = 'Unable to load contacts. Please try again later.';
    }
}

let userSearchTimeout;
userSearchInput.addEventListener('input', () => {
    clearTimeout(userSearchTimeout);
    userSearchTimeout = setTimeout(() => loadUsers(userSearchInput.value.trim()), 300);
});

let contactSearchTimeout;
contactSearchInput.addEventListener('input', () => {
    clearTimeout(contactSearchTimeout);
    contactSearchTimeout = setTimeout(() => loadAllContacts(contactSearchInput.value.trim()), 300);
});

createAdminForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    createAdminMessage.textContent = 'Creating admin account...';

    const newAdmin = {
        firstName: document.querySelector('#admin-firstName').value.trim(),
        lastName: document.querySelector('#admin-lastName').value.trim(),
        login: document.querySelector('#admin-login').value.trim(),
        password: document.querySelector('#admin-password').value
    };

    try {
        const response = await fetch('./api/index.php?action=admin_create_admin', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(newAdmin)
        });

        if (handleAuthFailure(response)) return;

        const result = await response.json();
        if (!response.ok) {
            createAdminMessage.textContent = result.error || 'Could not create admin account.';
            return;
        }

        createAdminMessage.textContent = 'Admin account created.';
        createAdminForm.reset();
        loadUsers(userSearchInput.value.trim());
    } catch (error) {
        createAdminMessage.textContent = 'Could not create admin account. Please try again.';
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

loadUsers();
loadAllContacts();
