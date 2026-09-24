const contactsMessage = document.querySelector('#contacts-message');
const contactsList = document.querySelector('#contacts-list');
const welcomeMessage = document.querySelector('#welcome-message');

async function loadContacts() {
    const token = sessionStorage.getItem('token');

    if (!token) {
        window.location.replace('./index.html');
        return;
    }

    try {
        const response = await fetch('./api/index.php', {
            headers: {
                'Authorization': `Bearer ${token}`
            },
            cache: 'no-store'
        });

        if (response.status === 401) {
            sessionStorage.removeItem('token');
            sessionStorage.removeItem('firstName');
            window.location.replace('./index.html');
            return;
        }

        if (!response.ok) {
            contactsMessage.textContent = 'Unable to load contacts. Please try again later.';
            return;
        }

        const result = await response.json();

        if (!Array.isArray(result.contacts)) {
            contactsMessage.textContent = 'The server returned an unexpected response.';
            return;
        }

        const firstName = sessionStorage.getItem('firstName');
        welcomeMessage.textContent = firstName ? `Welcome, ${firstName}!` : 'Welcome!';

        if (result.contacts.length === 0) {
            contactsMessage.textContent = 'You do not have any contacts yet.';
            return;
        }

        contactsMessage.textContent = '';

        for (const contact of result.contacts) {
            const item = document.createElement('li');
            const name = document.createElement('h3');
            const email = document.createElement('p');
            const phone = document.createElement('p');

            name.textContent = `${contact.firstName} ${contact.lastName}`;
            email.textContent = `Email: ${contact.email || 'Not provided'}`;
            phone.textContent = `Phone: ${contact.phone || 'Not provided'}`;

            item.append(name, email, phone);
            contactsList.append(item);
        }
    } catch (error) {
        contactsMessage.textContent = 'Unable to load contacts. Please try again later.';
    }
}

loadContacts();
