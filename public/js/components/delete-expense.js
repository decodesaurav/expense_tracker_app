document.addEventListener('DOMContentLoaded', function (event) {
    event.preventDefault();
    const deleteButtons = document.querySelectorAll('.delete-expense');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function () {
            const expenseId = button.getAttribute('data-id');
            if (confirm('Are you sure you want to delete this expense?')) {
                const form = document.getElementById(`delete-form-${expenseId}`);
                if (form) {
                    const formId = form.getAttribute('id');
                    const expenseId = formId.replace('delete-form-', '');
                    const url = `/expenses/${expenseId}`;
                    //delete
                    axios.delete(url)
                        .then(function (response) {
                            const expenseRow = button.closest('tr');
                            if (expenseRow) {
                                expenseRow.remove();
                            }

                            // Show the success message container
                            const successMessageContainer = document.getElementById('deleted-expense-details');

                            // Clear any inline styles
                            successMessageContainer.style.display = '';

                            successMessageContainer.style.setProperty('display', 'block');

                            // Toggle deleted expense details
                            const deletedExpenseDetails = document.getElementById('deleted-expense-details');
                            deletedExpenseDetails.innerHTML = 'Expense deleted Successfully: ' + JSON.stringify(response.data);

                            // Automatically remove the success message after 3 seconds
                            setTimeout(function () {
                                successMessageContainer.style.setProperty('display', 'none' );
                            }, 3000);
                        })
                        .catch(function (error) {
                            console.error('Error deleting expense:', error);
                        });
                }
            }
        });
    });
});
