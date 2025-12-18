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
    // Get all rows in the table body
    const rows = document.querySelectorAll('.records-table tbody tr');
    const modal = document.getElementById('violationModal');
    const closeModal = document.querySelector('.close-modal');
    
    // Modal field elements
    const modalDate = document.getElementById('modalDate');
    const modalViolation = document.getElementById('modalViolation');
    const modalLicense = document.getElementById('modalLicense');
    const modalFine = document.getElementById('modalFine');
    const modalStatus = document.getElementById('modalStatus');
    const modalLocation = document.getElementById('modalLocation');
    const modalRemark = document.getElementById('modalRemark');
    const modalImage = document.getElementById('modalImage');
    
    // Debug log: check if rows are found
    console.log("Found " + rows.length + " table rows.");
    
    // Attach click listener to each row
    rows.forEach(row => {
        row.addEventListener('click', function() {
            // Retrieve data attributes from the row
            const date = row.getAttribute('data-date');
            const violation = row.getAttribute('data-violation');
            const license = row.getAttribute('data-license');
            const fine = row.getAttribute('data-fine');
            const status = row.getAttribute('data-status');
            const location = row.getAttribute('data-location');
            const remark = row.getAttribute('data-remark');
            const imageSrc = row.getAttribute('data-image');
            
            // Debug log: show data in console
            console.log("Row clicked:", { date, violation, license });
            
            // Populate modal fields
            modalDate.textContent = date;
            modalViolation.textContent = violation;
            modalLicense.textContent = license;
            modalFine.textContent = fine;
            modalStatus.textContent = 
                (row.getAttribute("data-payment-status") === "false" && row.getAttribute("data-violation-status") === "false") ? 
                    "Pending" : 
                (row.getAttribute("data-payment-status") === "false" && row.getAttribute("data-violation-status") === "true") ?
                    "Appeal Approved" :
                (row.getAttribute("data-payment-status") === "true" && row.getAttribute("data-violation-status") === "true") ?
                    "Paid" : "Unknown";
            modalLocation.textContent = location;
            modalRemark.textContent = remark;
            
            // If an image URL is provided, display it; otherwise, hide the container
            if (imageSrc && imageSrc.trim() !== "") {
                // Prepend the data URL prefix (adjust the mime type as needed)
                modalImage.src = "data:image/jpeg;base64," + imageSrc;
                document.getElementById('modalImageContainer').style.display = 'block';
            } else {
                document.getElementById('modalImageContainer').style.display = 'none';
            }
            
            // Show the modal
            modal.style.display = 'block';
            });
    });
    
    // Attach event listener to close modal when the close button is clicked
    closeModal.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    // Also close the modal when clicking outside the modal content
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});


