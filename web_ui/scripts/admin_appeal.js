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

// Close the modal when the close button is clicked
document.addEventListener('DOMContentLoaded', function() {
    // Get the rows in the .appeal-table
    const rows = document.querySelectorAll('.appeal-table tbody tr');
    
    // Reference your appeal modal and close button
    const modal = document.getElementById('appealModal');
    const closeModal = modal.querySelector('.close-modal');
    
    // Reference modal fields
    const modalAppealID   = document.getElementById('modelAppealID');
    const modalViolationID = document.getElementById('modalViolationID');
    const modalDriverName = document.getElementById('modalDriverName');
    const modalAppealText = document.getElementById('modalAppealText');
    const modalStatus     = document.getElementById('modalStatus');
    const modalDate       = document.getElementById('modalDate');
    const modalImage      = document.getElementById('modalImage');
    const modalImageContainer = document.getElementById('modalImageContainer');

    rows.forEach(row => {
        row.addEventListener('click', function() {
            // Retrieve data attributes from the row
            const appealID   = row.getAttribute('data-appeal-id');
            const violationID = row.getAttribute('data-violation-id');
            const driverName = row.getAttribute('data-driver-name');
            const appealText = row.getAttribute('data-appeal-text');
            const status     = row.getAttribute('data-status');
            const date       = row.getAttribute('data-date');
            const imageSrc   = row.getAttribute('data-image');

            // Populate modal fields
            modalAppealID.textContent   = appealID;
            modalViolationID.textContent = violationID;
            modalDriverName.textContent = driverName;
            modalAppealText.textContent = appealText;
            modalStatus.textContent     = status;
            modalDate.textContent       = date;

            // If an image is available, show it
            if (imageSrc && imageSrc.trim() !== "") {
                modalImage.src = "data:image/jpeg;base64," + imageSrc;
                modalImageContainer.style.display = 'block';
            } else {
                modalImageContainer.style.display = 'none';
            }

            // Show the modal
            modal.style.display = 'block';
        });
    });

    // Close the modal when the "×" is clicked
    closeModal.addEventListener('click', function() {
        modal.style.display = 'none';
    });

    // Close the modal when clicking outside it
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    // Approval button click handler
    const approveButtons = document.querySelectorAll('.btn-approve');
    approveButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const appealID = this.getAttribute('data-id');
            
            if (confirm('Are you sure you want to approve this appeal?')) {
                // Send AJAX request to approve the appeal
                approveAppeal(appealID);
            }
        });
    });
    
    // Rejection button click handler
    const rejectButtons = document.querySelectorAll('.btn-reject');
    rejectButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const appealID = this.getAttribute('data-id');
            
            if (confirm('Are you sure you want to reject this appeal?')) {
                // Send AJAX request to reject the appeal
                rejectAppeal(appealID);
            }
        });
    });
});

// Function to handle appeal approval
function approveAppeal(appealID) {
    const formData = new FormData();
    formData.append('appealID', appealID);
    formData.append('action', 'Approved');
    
    fetch('../php/appeal_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('Server response:', data);
        if (data.success) {
            alert('Appeal approved successfully!');
            // Reload the page to show updated status
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while processing your request: ' + error.message);
    });
}

// Function to handle appeal rejection
function rejectAppeal(appealID) {
    const formData = new FormData();
    formData.append('appealID', appealID);
    formData.append('action', 'Rejected');
    
    fetch('../php/appeal_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('Server response:', data);
        if (data.success) {
            alert('Appeal rejected successfully!');
            // Reload the page to show updated status
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while processing your request: ' + error.message);
    });
}

