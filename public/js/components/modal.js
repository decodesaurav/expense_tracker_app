
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('editModal');
    if(modal) {
        const modalBody = modal.querySelector('.modal-body');
        const expenseTableBody = document.getElementById('expense-table-body');

        expenseTableBody.addEventListener('click', (event) => {
            const editButton = event.target;
            console.log(editButton)
            if(editButton.classList.contains('edit-expense')) {
                const expenseId = editButton.dataset.id;
                const updateExpenseRoute = editButton.dataset.route;
                const csrfToken = editButton.dataset.csrf;
                if (editButton) {
                    fetch(`/expenses/${expenseId}/edit`)
                        .then(response => response.json())
                        .then(data => {
                            const expenseAmount = data.amount;
                            const expenseDate = data.date;
                            const expenseDescription = data.description;
                            const categories = data.categories;

                            modalBody.innerHTML = `
                                <form action="${updateExpenseRoute}" method="POST" id="editExpenseForm">
                                    <input type="hidden" name="_method" value="PUT">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="text" name="amount" id="amount" class="form-control" value="${expenseAmount}" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="date">Date</label>
                                        <input type="date" name="date" id="date" class="form-control" value="${expenseDate}" max="${getCurrentDate()}" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <input type="text" name="description" id="description" class="form-control" value="${expenseDescription}" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="category">Category</label>
                                        <select name="category" id="category" class="form-control">
                                            ${categories.map(category => `
                                                <option value="${category.name}" ${category.id === data.category_id ? 'selected' : ''}>${category.name}</option>
                                            `).join('')}
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Update</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"">Cancel</button>
                                </form>
                            `;
                            const cancelButton = modal.querySelector('[data-bs-dismiss="modal"]');
                            cancelButton.addEventListener('click', () => {
                                // Clear the modal content or reset form fields
                                modalBody.innerHTML = '';
                            });
                            jQuery(modal).modal('show');
                        })
                        .catch(error => {
                            console.error('Error fetching expense data:', error);
                        });
                } else {
                    console.log('out of update')
                }
            } else {
                console.log('here')
            }
        });
    }
});
function getCurrentDate() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    // Get the user's time zone offset in minutes
    const timezoneOffset = today.getTimezoneOffset();
    // Adjust the date based on the time zone offset
    const adjustedDate = new Date(today.getTime() - timezoneOffset * 60 * 1000);

    const adjustedYear = adjustedDate.getFullYear();
    const adjustedMonth = String(adjustedDate.getMonth() + 1).padStart(2, '0');
    const adjustedDay = String(adjustedDate.getDate()).padStart(2, '0');

    return `${adjustedYear}-${adjustedMonth}-${adjustedDay}`;
}
