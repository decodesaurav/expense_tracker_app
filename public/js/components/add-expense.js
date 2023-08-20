document.addEventListener('DOMContentLoaded', function(e) {
    let addNewCategoryLink = document.getElementById('add-new-category');
    let categoryForm = document.getElementById('new-category-field');
    const form = document.getElementById('expense-addition');
    const addButton = form.querySelector('.btn-primary');
    const successMessageContainer = document.getElementById('added-expense-details');
    const table = document.querySelector('.table');
    let rowCount = parseInt(table.getAttribute('data-row-count'));


    addButton.addEventListener('click', function () {
        // Get the form data
        event.preventDefault();
        const formData = new FormData(form);
        const errorMessageContainer = document.getElementById('error-expense-message');

        // Validate form data
        if (!formData.get('amount') || !formData.get('date') || !formData.get('description') || !formData.get('category')) {
            errorMessageContainer.innerHTML = 'Please provide all the data';
            errorMessageContainer.style.display = '';
            errorMessageContainer.style.setProperty('display', 'block' );

            // Automatically remove the error message after 3 seconds
            setTimeout(function () {
                errorMessageContainer.style.setProperty('display', 'none' );
            }, 3000);

            return; // Exit the function if data is missing
        }
        var amountValue = formData.get('amount');
        if( isNaN(parseFloat(amountValue))){
            errorMessageContainer.innerHTML = 'Please enter valid amount';
            errorMessageContainer.style.display = '';
            errorMessageContainer.style.setProperty('display', 'block' );

            // Automatically remove the error message after 3 seconds
            setTimeout(function () {
                errorMessageContainer.style.setProperty('display', 'none' );
            }, 3000);

            return; // Exit the function if data is NaN

        }

        // Send a POST request using Axios
        axios.post('/expenses', formData)
            .then(function (response) {
                const expenseDetails = response.data.expense;

                // Create a new row for the latest expense
                const newRow = createTableRow(expenseDetails);

                // Get the table body
                const tableBody = document.getElementById('expense-table-body');
                const rowCount = tableBody.getElementsByTagName('tr').length;

                // Check if row count exceeds the limit of 10
                if (rowCount >= 10) {
                    // Remove the last row (oldest expense)
                    tableBody.removeChild(tableBody.lastElementChild);
                }

                // Add the new row to the top
                tableBody.insertBefore(newRow, tableBody.firstChild);

                // Clear the form input fields
                form.reset();

                successMessageContainer.innerHTML = 'Expense added successfully: ' + expenseDetails.description;
                successMessageContainer.style.display = 'block';
                setTimeout(function () {
                    successMessageContainer.style.display = 'none';
                }, 3000);
            })
            .catch(function (error) {
                if (error.response && error.response.status === 422) {
                    errorMessageContainer.textContent = 'Please provide all the data';
                } else {
                    console.error('Error adding expense:', error);
                }
            });
    });


    addNewCategoryLink.addEventListener('click', function(event) {
        event.preventDefault();
        categoryForm.style.display = 'block';
    });

    //date fetch current date
    const dateInput = document.getElementById('date');

    if (dateInput) {
        dateInput.max = getCurrentDate();
    }

    //Edit buttons
    const editButtons = document.querySelectorAll('.edit-expense');
    editButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            const expenseId = this.getAttribute('data-id');
            console.log(expenseId)
            const updateExpenseRoute = this.getAttribute('data-route');
            const csrfToken = this.getAttribute('data-csrf');
            fetch(`/expenses/${expenseId}/edit`)
                .then(response => response.json())
                .then(data => {
                    const modal = document.getElementById('editModal');
                    const modalBody = modal.querySelector('.modal-body');

                    // Populate modal form with data
                    modalBody.innerHTML = `
                        <form action="/expenses/${expenseId}" method="POST" id="editExpenseForm">
                            <input type="hidden" name="_method" value="PUT">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <div class="form-group">
                                <label for="amount">Amount</label>
                                <input type="text" name="amount" id="amount" class="form-control" value="${data.amount}" required>
                            </div>
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input type="date" name="date" id="date" class="form-control" value="${data.date}" max="${getCurrentDate()}" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <input type="text" name="description" id="description" class="form-control" value="${data.description}" required>
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select name="category" id="category" class="form-control">
                                    <!-- Populate options -->
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Update</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </form>
                    `;
                    // Show the modal
                    jQuery(modal).modal('show');
                })
                .catch(error => {
                    console.error('Error fetching expense data:', error);
                });
        });
    });
});
function createTableRow(expense) {
    const newRow = document.createElement('tr');
    const updateRoute = `/expenses/${expense.id}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    newRow.innerHTML = `
      <td>${expense.description}</td>
      <td>${expense.amount}</td>
      <td>${expense.category}</td>
      <td>${expense.date}</td>
      <td>
        <button class="btn btn-primary edit-expense" data-id="${expense.id}" data-route="${updateRoute}" data-csrf="${csrfToken}" data-toggle="modal" data-target="#editModal">Edit</button>

        <form class="d-inline" id="delete-form-${expense.id}">
            <button type="button" class="btn btn-danger delete-expense" data-id="${expense.id}">Delete</button>
        </form>
      </td>
    `;

    // Attach the delete event listener to the newly created button
    const deleteButton = newRow.querySelector('.delete-expense');
    deleteButton.addEventListener('click', handleDelete);

    return newRow;
}
function handleDelete(event) {
    const expenseId = event.target.getAttribute('data-id');
    const deleteForm = document.getElementById(`delete-form-${expenseId}`);

    // Prevent form submission
    event.preventDefault();

    // Display a confirmation dialog
    const confirmDelete = window.confirm('Are you sure you want to delete this expense?');
    if (!confirmDelete) {
        return;
    }

    // Once the expense is deleted, remove the row from the table
    const row = deleteForm.closest('tr');
    row.remove();

    // Show a success message
    const successMessageContainer = document.getElementById('deleted-expense-details');
    successMessageContainer.innerHTML = 'Expense deleted successfully';
    successMessageContainer.style.display = 'block';
    setTimeout(function () {
        successMessageContainer.style.display = 'none';
    }, 3000);
}
function getCurrentDate() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}
