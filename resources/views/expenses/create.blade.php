<!-- resources/views/expenses/create.blade.php -->

@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h2 class="text-center">Add New Expense</h2>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('expenses.store') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="amount">Amount</label>
                                <input type="text" name="amount" id="amount" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input type="date" name="date" id="date" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <input type="text" name="description" id="description" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" name="category" id="category" class="form-control" required>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
