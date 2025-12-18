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

// Initialize Stripe
const stripe = Stripe('your_public_key_here');

// Handle payment button clicks
document.querySelectorAll('.pay-button').forEach(button => {
    button.addEventListener('click', async function(e) {
        e.preventDefault();
        
        // Get violation data from the button attributes
        const violationId = this.getAttribute('data-violation-id');
        const amount = this.getAttribute('data-amount');
        const violationType = this.getAttribute('data-violation-type');
        
        // Show loading overlay
        document.getElementById('payment-processing').style.display = 'flex';
        
        try {
            // Send request to create checkout session
            const response = await fetch('../php/process_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'violation_id': violationId,
                    'amount': amount,
                    'violation_type': violationType
                })
            });
            
            const session = await response.json();
            
            if (session.error) {
                // Hide loading overlay
                document.getElementById('payment-processing').style.display = 'none';
                alert('Payment Error: ' + session.error);
                return;
            }
            
            // Redirect to Stripe Checkout
            stripe.redirectToCheckout({ sessionId: session.id });
        } catch (error) {
            // Hide loading overlay
            document.getElementById('payment-processing').style.display = 'none';
            console.error('Error:', error);
            alert('An error occurred while processing your payment. Please try again.');
        }
    });
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