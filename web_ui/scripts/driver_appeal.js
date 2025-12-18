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