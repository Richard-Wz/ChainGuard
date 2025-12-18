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

// Preview uploaded image before submission
document.getElementById('violationImage').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const previewContainer = document.getElementById('imagePreviewContainer');

    // Clear previous preview
    if (previewContainer) {
        previewContainer.innerHTML = '';
    }

    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            // Create an image element and set its source to the file data URL
            const img = document.createElement('img');
            img.src = event.target.result;
            img.alt = 'Image Preview';
            img.classList.add('image-preview');

            // Append the image to the container
            previewContainer.appendChild(img);
        };
        reader.readAsDataURL(file);
    }
});

// Close notification
document.addEventListener('DOMContentLoaded', function() {
    // Function to close a notification with a fade out effect
    function closeNotification(notification) {
        notification.style.transition = 'opacity 0.3s';
        notification.style.opacity = '0';
        setTimeout(function() {
            notification.style.display = 'none';
        }, 300);
    }

    // Event listener for manual close by clicking the close icon
    var closeButtons = document.querySelectorAll('.close-notification');
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var notification = button.parentElement;
            closeNotification(notification);
        });
    });

    // Automatically hide notifications after 5 seconds
    setTimeout(function() {
        var notifications = document.querySelectorAll('.notification');
        notifications.forEach(function(notification) {
            closeNotification(notification);
        });
    }, 5000);
});