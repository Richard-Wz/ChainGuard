const form = document.getElementById('driver-registration-form');
form.addEventListener('submit', function(event) {
    event.preventDefault();
    // Get form values
    const fullName = document.getElementById('fullName').value.trim();
    const email = document.getElementById('email').value.trim();
    const contactNumber = document.getElementById('contactNumber').value.trim();
    const licensePlate = document.getElementById('licensePlate').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const address = document.getElementById('address').value.trim();

    // Name: letters and spaces only (no numbers)
    const nameRegex = /^[A-Za-z\s]+$/;

    // Email: basic check for an '@' and a '.'
    const emailRegex = /^\S+@\S+\.\S+$/;

    // Malaysian Contact Number:
    // This example checks for a number starting with 0 followed by 9 or 10 digits.
    const phoneRegex = /^0\d{9,10}$/;

    // Malaysian License Plate:
    // Format: 3 letters (allowed letters: A-H, J-N, P-Z) followed by 1-4 digits,
    // and optionally one more letter at the end (allowed letters only, excluding I and O).
    const licensePlateRegex = /^[A-HJ-NP-Z]{3}\d{1,4}[A-HJ-NP-Z]?$/;

    // Validate Name
    if (!nameRegex.test(fullName)) {
        alert("Please enter a valid name (letters and spaces only, no numbers).");
        return;
    }

    // Validate Email
    if (!emailRegex.test(email)) {
        alert("Please enter a valid email address.");
        return;
    }

    // Validate Contact Number
    if (!phoneRegex.test(contactNumber)) {
        alert("Please enter a valid Malaysian contact number (e.g., 0123456789).");
        return;
    }

    // Validate License Plate
    if (!licensePlateRegex.test(licensePlate)) {
        alert("Please enter a valid license plate (e.g., ABC1234A). Allowed letters: A-H, J-N, P-Z.");
        return;
    }

    // Password requirements:
    // - At least 8 characters long
    // - At least 1 alphabet letter
    // - At least 1 special character
    const passwordRegex = /^(?=.*[A-Za-z])(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?])[A-Za-z\d!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]{8,}$/;

    // Add this validation before checking if passwords match
    if (!passwordRegex.test(password)) {
        alert("Password must be at least 8 characters long, contain at least 1 letter, and at least 1 special character.");
        return;
    }

    // Validate Password
    if (password !== confirmPassword) {
        alert('Passwords do not match!');
        return;
    }

    console.log('Full Name:', fullName);
    console.log('Email:', email);
    console.log('Contact Number:', contactNumber);
    console.log('Vehicle License Plate:', licensePlate);
    console.log('Password:', password);
    console.log('Confirm Password:', confirmPassword);
    console.log('Address:', address);

    form.submit();
    // alert('Registration form submitted successfully!');
});
