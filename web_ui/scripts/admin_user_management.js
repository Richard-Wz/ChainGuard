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

// Modal functionality for user management
document.addEventListener('DOMContentLoaded', function() {
    // Attach click listener to each user table row
    document.querySelectorAll('.user-table tbody tr').forEach(row => {
        row.addEventListener('click', function(event) {
            // Prevent modal trigger when clicking on action buttons inside the row
            if (event.target.closest('.btn')) {
                return;
            }
            
            // Extract data from row attributes; ensure your PHP prints these attributes in <tr>
            const userID = this.dataset.id || 'N/A';
            const fullName = this.dataset.fullname || 'N/A';
            const email = this.dataset.email || 'N/A';
            const contactNumber = this.dataset.contactnumber || 'N/A';
            const licensePlate = this.dataset.licenseplate || 'N/A';
            const address = this.dataset.address || 'N/A';
            // const blockchainClientID = this.dataset.blockchainclientid || 'N/A';
            const blockchainUserID = this.dataset.blockchainuserid || 'N/A';
            const profileImage = this.dataset.profileimage || '';
            
            // Format date
            const createdAtRaw = this.dataset.createdat || 'N/A';
            let formattedDate = createdAtRaw;
            if (!isNaN(createdAtRaw)) {
                // Convert the string timestamp to an integer
                const timestamp = parseInt(createdAtRaw);
                const dateObj = new Date(timestamp);
                formattedDate = dateObj.getFullYear() + '-' +
                                String(dateObj.getMonth() + 1).padStart(2, '0') + '-' +
                                String(dateObj.getDate()).padStart(2, '0');
            }
            
            // Populate modal fields
            document.getElementById('modalUserID').textContent = userID;
            document.getElementById('modalName').textContent = fullName;
            document.getElementById('modalEmail').textContent = email;
            document.getElementById('modalContact').textContent = contactNumber;
            document.getElementById('modalLicense').textContent = licensePlate;
            document.getElementById('modalAddress').textContent = address;
            document.getElementById('modalCreatedAt').textContent = formattedDate;
            document.getElementById('modalWalletID').textContent = blockchainUserID;
            // document.getElementById('modalBlockchainUserID').textContent = blockchainClientID;
            
            // Handle the profile image
            if (profileImage.trim() !== '') {
                document.getElementById('modalImage').src = profileImage;
                document.getElementById('modalImageContainer').style.display = 'block';
            } else {
                document.getElementById('modalImage').src = '';
                document.getElementById('modalImageContainer').style.display = 'none';
            }
            
            // Show the modal popup
            document.getElementById('userModal').style.display = 'block';
        });
    });
    
    // Close modal when clicking the close button
    document.querySelector('.close-modal').addEventListener('click', function() {
        document.getElementById('userModal').style.display = 'none';
    });
    
    // Close modal when clicking outside the modal content
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('userModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Manage driver functionality
    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', function(event) {
            event.stopPropagation();
            const id = this.dataset.id; // get the id from the button's data-id attribute
            window.location.href = 'admin_user_profile.php?id=' + encodeURIComponent(id);
        });
    });

    // Delete driver functionality
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', function(event) {
            event.stopPropagation(); // Prevent row click events from firing.
            const id = this.dataset.id;
            if (confirm("Are you sure you want to delete this driver?")) {
                // Redirect to delete_driver.php with the driver id
                window.location.href = 'delete_driver.php?id=' + encodeURIComponent(id);
            }
        });
    });
});