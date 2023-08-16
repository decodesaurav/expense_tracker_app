<meta name="csrf-token" content="{{ csrf_token() }}">

<link rel="stylesheet" type="text/css" href="{{ asset('css/index.css') }}">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/components/modal.js') }}"></script>
<script src="{{ asset('js/components/delete-expense.js') }}"></script>
<script src="{{ asset('js/components/index.js') }}"></script>


@extends('layouts.dashboard')

@section('content')
    <div class="container">
        <h2 class="page-header">Expense List</h2>
        <div id="deleted-expense-details" class="deleted-expense-details"></div>
        <div id="added-expense-details" class="added-expense-details"></div>
        <div id="error-expense-message" class="error-expense-message"></div>
        <div id="updated-expense-message" class="updated-expense-message"></div>

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
        <x-add-expense />
        <table class="table">
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
            @foreach($expenses as $expense)
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
            </tbody>
        </table>
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
