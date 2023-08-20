
// Remove query parameters from the URL
function removeQueryParams() {
    history.replaceState({}, document.title, window.location.pathname);
}

// Add an event listener to the window's 'load' event
window.addEventListener('DOMContentLoaded', removeQueryParams);
