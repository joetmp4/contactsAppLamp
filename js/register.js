const registerForm = document.querySelector('#register-form');
const registerMessage = document.querySelector('#register-message');

registerForm.addEventListener('submit', async function (event) {
    event.preventDefault();

    const registrationData = {
        firstName: document.querySelector('#firstName').value.trim(),
        lastName: document.querySelector('#lastName').value.trim(),
        login: document.querySelector('#login').value.trim(),
        password: document.querySelector('#password').value
    };

    registerMessage.textContent = 'Creating your account...';

    try {
        const response = await fetch('./api/index.php?action=register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(registrationData)
        });

        const result = await response.json();

        if (!response.ok) {
            registerMessage.textContent =
                result.error || 'Registration failed. Please try again.';
            return;
        }

        registerMessage.textContent =
            'Account created successfully! You can now log in.';

        registerForm.reset();
    } catch (error) {
        registerMessage.textContent =
            'Unable to complete registration. Please try again later.';
    }
});
