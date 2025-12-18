document.querySelector('.hamburger-menu').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('active');
});

// Close sidebar when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const hamburger = document.querySelector('.hamburger-menu');
    
    if (!sidebar.contains(event.target) && !hamburger.contains(event.target) && sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
    }
});

// Make menu items clickable
document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', function() {
        document.querySelectorAll('.menu-item').forEach(el => {
            el.classList.remove('active');
        });
        this.classList.add('active');
    });
});

// Handle edit profile button click
document.querySelector('.edit-profile').addEventListener('click', function() {
    window.location.href = 'edit_driver_profile.php';
});

// Notification timeout
document.addEventListener('DOMContentLoaded', function() {
    // Check if there are any notification elements
    const notifications = document.querySelectorAll('.notification');
    if (notifications.length > 0) {
        // Add click event to close buttons
        const closeButtons = document.querySelectorAll('.close-notification');
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                this.parentElement.style.display = 'none';
            });
        });
        
        // Auto-hide notifications after 5 seconds
        setTimeout(function() {
            notifications.forEach(notification => {
                notification.style.display = 'none';
            });
        }, 5000);
    }
});
