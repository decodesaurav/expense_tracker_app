document.addEventListener('DOMContentLoaded', () => {
    const successMessage = document.getElementById('success-message');

    // Hide the success message after 3000 milliseconds (3 seconds)
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 3000);
    }
});
