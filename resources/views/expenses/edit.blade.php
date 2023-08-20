<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
<script src="{{ asset('js/components/add-expense.js') }}"></script>
@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Edit Expense</h1>
        <div class="row justify-content-center"> <!-- Center the content -->
            <div class="col-md-12"> <!-- Adjust the column width as needed -->
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif
                <form action="{{ route('expenses.update', $expense->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="amount">Amount</label>
                            <input type="text" name="amount" id="amount" class="form-control"
                                   value="{{ $expense->amount }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="date">Date</label>
                            <input type="date" name="date" id="date" class="form-control" value="{{ $expense->date }}"
                                   required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="description">Description</label>
                            <input type="text" name="description" id="description" class="form-control"
                                   value="{{ $expense->description }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="category">Category</label>
                            @if (!empty($categories))
                                <select name="category" id="category" class="form-control">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->name }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <div>
                                    <a href="#" id="add-new-category">Add New Category</a>
                                </div>
                            @endif
                        </div>

                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">Update</button>
                        <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
                <form action="{{ route('categories.store') }}" method="POST" class="row g-2" id="category-form-field">
                    @csrf
                    <div class="col-md-3" id="new-category-field" style="display: none;">
                        <div class="form-group">
                            <label for="category">New Category</label>
                            <input type="text" name="category" id="category" class="form-control" required>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Add</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
