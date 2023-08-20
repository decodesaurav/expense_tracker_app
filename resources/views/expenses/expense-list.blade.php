
<meta name="csrf-token" content="{{ csrf_token() }}">

<link rel="stylesheet" type="text/css" href="{{ asset('css/index.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('css/pagination.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.6/dist/flatpickr.min.css">


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.6/dist/flatpickr.min.js"></script>
<script src="{{ asset('js/components/modal.js') }}"></script>
<script src="{{ asset('js/components/delete-expense.js') }}"></script>
<script src="{{ asset('js/components/index.js') }}"></script>
<script src="{{ asset('js/components/expense-list.js') }}"></script>

@extends('layouts.dashboard')

@section('content')
    <div id="success-message-container">
        @if(session('success'))
            <div class="alert alert-success" id="success-message">
                {{ session('success') }}
            </div>
        @endif
    </div>
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
            </ul>
        </div>
    @endif
    <div class="content-container">
        <div class="form-container expense-list">
            <form action="{{ route('expenses.show') }}" method="GET" class="combined-form">
                <!-- Search Bar -->
                <div class="search-bar">
                    <input type="text" name="search" placeholder="Search by description or category">
                </div>
                <!-- Filter Options -->
                <div class="filter-bar">
                    <select name="filter">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Sorting Options -->
                <div class="sorting-option">
                    <select name="sort">
                        <option value="date_asc">Date (Oldest First)</option>
                        <option value="date_desc">Date (Newest First)</option>
                        <option value="amount_asc">Amount (Low to High)</option>
                        <option value="amount_desc">Amount (High to Low)</option>
                        <!-- Add more sorting options if needed -->
                    </select>
                </div>
                <!-- Date Range Filter -->
                <div class="date-range">
                    <label for="start_date">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" class="date-input"
                           placeholder="Select start date"
                           value="{{ old('start_date', $request->input('start_date')) }}">

                    <label for="end_date">End Date:</label>
                    <input type="date" name="end_date" id="end_date" class="date-input"
                           placeholder="Select end date"
                           value="{{ old('end_date', $request->input('end_date')) }}">
                </div>

                <div class="apply-button">
                    <button type="submit">Apply</button>
                </div>
            </form>
            <div class="message-container"></div>
        </div>
        <table class="table" >
            <h1 class="expense-header">Expense Records</h1>
            <thead>
            <tr>
                <th>Description</th>
                <th>Amount</th>
                <th>Category</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody id="expense-table-body">
            @if ($expenses === null || $expenses->isEmpty())
                <tr>
                    <td colspan="5">No records found.</td>
                </tr>
            @else
                @foreach(($sortedExpenses ?? $expenses) as $expense)
                    <tr>
                        <td>{{ $expense->description }}</td>
                        <td>{{ $expense->amount }}</td>
                        <td>{{ $expense->category }}</td>
                        <td>{{ $expense->date }}</td>
                        <td>
                            <button class="btn btn-primary edit-expense"
                                    data-id="{{ $expense->id }}"
                                    data-route="{{ route('expenses.update', $expense->id) }}"
                                    data-toggle="modal"
                                    data-target="#editModal"
                                    data-csrf="{{ csrf_token() }}"
                            >
                                Edit
                            </button>
                            <form class="d-inline" id="delete-form-{{ $expense->id }}">
                                @csrf
                                <button type="button" class="btn btn-danger delete-expense" data-id="{{ $expense->id }}">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
        <div class="pagination">
                {{ $expenses->links('vendor.pagination.tailwind') }}
        </div>
        <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit Expense</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <!-- The edit form will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
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

                if ((!searchInput && !filterInput) && ! ( startDateInput && endDateInput ) ) {
                    event.preventDefault();
                    displayMessage('No search, filter criteria applied.');
                }
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr('#start_date', {
                maxDate: 'today', // Set maximum date to today
                dateFormat: 'Y-m-d', // Set desired date format
                onChange: function(selectedDates) {
                    endPicker.set('minDate', selectedDates[0]);
                }
            });

            const endPicker = flatpickr('#end_date', {
                maxDate: 'today',
                dateFormat: 'Y-m-d',
                defaultDate: '{{ old('end_date', $request->input('end_date')) }}'
            });
        });
        // Preserve form values in local storage
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.combined-form');

            // On form submission
            form.addEventListener('submit', function(event) {
                const formElements = form.elements;
                for (const element of formElements) {
                    if (element.type !== 'submit') {
                        localStorage.setItem(element.name, element.value);
                    }
                }
            });

            // Restore form values from local storage
            const storedFormValues = localStorage.getItem('formValues');
            if (storedFormValues) {
                const formValues = JSON.parse(storedFormValues);
                for (const [name, value] of Object.entries(formValues)) {
                    const inputElement = form.querySelector(`[name="${name}"]`);
                    if (inputElement) {
                        inputElement.value = value;
                    }
                }
            }
        });
    </script>
@endsection
