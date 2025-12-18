const login_form = document.getElementById('login-form');
login_form.addEventListener('submit', function (event) {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;

    // Simple validation or form handling logic here
    console.log('email:', email);
    console.log('Password:', password);
});