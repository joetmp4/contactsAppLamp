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
        sessionStorage.removeItem('isAdmin');

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
                sessionStorage.setItem('loginErrorMessage', result.error || 'We couldn\'t log you in. Please check your credentials and try again.');
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

        //Keep the session token
        sessionStorage.setItem('token', result.token);
        sessionStorage.setItem('firstName', result.firstName);
        //Determines whether to go to admin or normal
        if (result.role === 'admin') {
            sessionStorage.setItem('isAdmin', 'true');
            window.location.assign('./admin.html');
        } else {
            sessionStorage.setItem('isAdmin', 'false');
            window.location.assign('./contacts.html');
        }
    } catch (error) {
        loginMessage.textContent = 'Unable to complete login. Please try again later.';
    } finally {
        loginButton.disabled = false;
    }
});
