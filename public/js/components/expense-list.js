// Function to display the message
function displayMessage(message) {
    const messageContainer = document.querySelector('.message-container');
    console.log(messageContainer);

    const warningIcon = '<span style="color: red; font-size: 16px;">&#9888;</span>';
    const messageHTML = `<p>${warningIcon} ${message}</p>`;

    messageContainer.innerHTML = messageHTML;

    setTimeout(() => {
        messageContainer.innerHTML = '';
    }, 5000);
}

// Add an event listener to the form submit button
const submitButton = document.querySelector('.combined-form button[type="submit"]');
if (submitButton) {
    submitButton.addEventListener('click', function (event) {
        const searchInput = document.querySelector('.search-bar input[name="search"]').value;
        const filterInput = document.querySelector('.filter-bar select[name="filter"]').value;
        const startDateInput = document.querySelector('.date-range input[name="start_date"]').value;
        const endDateInput = document.querySelector('.date-range input[name="end_date"]').value;
        const sortFilterInput = document.querySelector('.date-range select[name="sort"]').value;

        if ((!searchInput && !filterInput) && !(startDateInput && endDateInput) && !sortFilterInput) {
            event.preventDefault();
            displayMessage('No search, filter criteria applied.');
        }
    });
}

document.addEventListener("DOMContentLoaded", function () {
    const checkbox = document.querySelector('input[name="apply_custom_date_filter"]');
    const dateRange = document.querySelector('.date-range');

    // Check the initial state of the checkbox
    if (checkbox.checked) {
        dateRange.style.display = "block";  // Show date range if checkbox is checked initially
    }

    // Attach an event handler to the checkbox
    checkbox.addEventListener('change', function () {
        if (this.checked) {
            dateRange.style.display = "block";  // Show date range when checkbox is checked
        } else {
            dateRange.style.display = "none";  // Hide date range when checkbox is unchecked
        }
    });
});
