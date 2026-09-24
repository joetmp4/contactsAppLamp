const loginForm = document.querySelector('#login-form');
const loginMessage = document.querySelector('#login-message');

loginForm.addEventListener('submit', async function (event) {
    event.preventDefault();

    const loginData = {
        login: document.querySelector('#login').value.trim(),
        password: document.querySelector('#password').value
    };

    const loginButton = loginForm.querySelector('button[type="submit"]');
    loginButton.disabled = true;
    loginMessage.textContent = 'Logging in...';

    try {
        sessionStorage.removeItem('token');
        sessionStorage.removeItem('firstName');

        const response = await fetch('./api/index.php?action=login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(loginData)
        });

        const result = await response.json();

        if (!response.ok) {
            if (response.status === 401 || response.status === 403) {
                window.location.assign('./login-error.html');
                return;
            }

            loginMessage.textContent = 'Unable to log in right now. Please try again later.';
            return;
        }

        if (typeof result.token !== 'string' || !result.token) {
            loginMessage.textContent = 'The server did not return a login session. Please try again.';
            return;
        }

        // Keep the session token for requests made from the contacts page.
        sessionStorage.setItem('token', result.token);
        sessionStorage.setItem('firstName', result.firstName);
        window.location.assign('./contacts.html');
    } catch (error) {
        loginMessage.textContent = 'Unable to complete login. Please try again later.';
    } finally {
        loginButton.disabled = false;
    }
});
