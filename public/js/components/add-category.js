function appendCategory(responseData) {
    let id = responseData.id;
    let name = responseData.name;

    let selectElement = document.getElementById('category');

    // If the select element doesn't exist, create it
    if (!selectElement) {
        selectElement = document.createElement('select');
        selectElement.id = 'category';
        selectElement.name = 'category';
        selectElement.classList.add('form-control');

        // Get the container for the category dropdown
        const formGroup = document.getElementById('add-category-new');

        // Remove any existing "Add New Category" link
        const addNewCategoryLink = formGroup.querySelector('#add-new-category');
        if (addNewCategoryLink) {
            addNewCategoryLink.remove();
        }

        // Append the select element to the container
        formGroup.appendChild(selectElement);
    }

    // Check if an option with the same value already exists
    const existingOption = selectElement.querySelector(`option[value="${id}"]`);
    if (!existingOption) {
        const newOption = document.createElement('option');
        newOption.value = id;
        newOption.text = name;
        selectElement.appendChild(newOption);

        // Automatically select the newly added option
        newOption.selected = true;
    }
}

document.addEventListener('DOMContentLoaded', function (event) {
    document.getElementById('add-category-btn').addEventListener('click', function (event) {
        event.preventDefault();
        const formData = new FormData(document.getElementById('category-form-field'));
        const errorMessageContainer = document.getElementById('error-message');
        const newCategoryField = document.getElementById('new-category-field');
        const addCategoryBtn = document.getElementById('add-category-btn');


        //send AJAX POST request
        const url = "/categories";
        axios.post(url, formData, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'multipart/form-data'
            }
        })
            .then(function (response) {
                console.log('Category added successfully:', response.data);
                appendCategory(response.data);
                //clear category input field
                const categoryInput = document.getElementById('category-add-new');
                categoryInput.value = '';

                //clear add new category option
                const categoryDropdown = document.getElementById('category');
                const emptyOption = categoryDropdown.querySelector('option[value="add-new"]');
                if (emptyOption) {
                    emptyOption.remove();
                }
                newCategoryField.style.display = 'none';
            })
            .catch(function (error) {
                if (error.response && error.response.status === 422) {
                    errorMessageContainer.textContent = 'The provided category is already present or not valid'; // Display the error message
                } else if (error.response && error.response.status === 401) {
                    errorMessageContainer.textContent = 'Please login or register'; // Display the error message
                } else {
                    console.error('Error adding category:', error);
                }
            })

        document.addEventListener('click', function (event) {
            if (event.target !== addCategoryBtn) {
                errorMessageContainer.textContent = ''; // Clear the error message
            }
        });
    });
});

