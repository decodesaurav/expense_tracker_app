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
                <div class="search-bar" style="display: block;">
                    Search By Name:
                    <input type="text" name="search" placeholder="Search by description or category"
                           value="{{ old('search', $request->input('search')) }}">
                </div>
                <!-- Filter Options -->
                <div class="filter-bar" style="display: block;">
                    Search Categories
                    <select name="filter">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option
                                value="{{ $category }}"{{ old('filter', $request->input('filter')) === $category ? ' selected' : '' }}>
                                {{$category}}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sorting-option" style="display: block;">
                    Sort By Date:
                    <select name="sort" style="margin: 4px;">
                        @foreach ($sortOptions as $value => $label)
                            <option
                                value="{{ $value }}"{{ old('sort', $request->input('sort')) === $value ? ' selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <div class="custom-date-filter" style="display: block;">
                        <label>
                            <input type="checkbox" name="apply_custom_date_filter" value="1"
                                {{ old('apply_custom_date_filter', $request->input('apply_custom_date_filter')) ? 'checked' : '' }}>
                            Apply Custom Date Filter
                        </label>
                    </div>
                </div>
                <div class="sorting-option" style="display: block;">
                    Sort By Order:
                    <select name="sort-amount" style="margin: 4px;">
                        @foreach ($sortAmount as $value => $label)
                            <option
                                value="{{ $value }}"{{ old('sort-amount', $request->input('sort-amount')) === $value ? ' selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Date Range Filter -->
                <div class="date-range" style="display: none;">
                    <label for="start_date" style="margin: 4px;">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" class="date-input"
                           placeholder="Select start date"
                           value="{{ old('start_date', $request->input('start_date')) }}">

                    <label for="end_date" style="margin: 4px;">End Date:</label>
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
        <table class="table">
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
                                <button type="button" class="btn btn-danger delete-expense"
                                        data-id="{{ $expense->id }}">Delete
                                </button>
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
@endsection
<script>
    document.addEventListener('DOMContentLoaded', function () {
        flatpickr('#start_date', {
            maxDate: 'today', // Set maximum date to today
            dateFormat: 'Y-m-d', // Set desired date format
            onChange: function (selectedDates) {
                endPicker.set('minDate', selectedDates[0]);
            }
        });

        const endPicker = flatpickr('#end_date', {
            maxDate: 'today',
            dateFormat: 'Y-m-d',
            defaultDate: "{{ old('end_date', $request->input('end_date')) }}"
        });
    });
</script>
