<script src="{{ asset('js/app.js') }}"></script>
<script src="{{ asset('js/components/add-category.js') }}"></script>
<script src="{{ asset('js/components/add-expense.js') }}"></script>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>


<div class="card">
    <div class="card-header">
        <h2 class="text-center">Add New Expense</h2>
    </div>
    <div class="card-body">
        <form id="expense-addition" class="row g-2">
            @csrf
            <div class="col-md-3">
                <div class="form-group">
                    <label for="amount">Amount</label>
                    <input type="text" name="amount" id="amount" class="form-control" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" name="date" id="date" class="form-control" required
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" name="description" id="description" class="form-control" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group" id="add-category-new">
                    <label for="category">Category</label>
                    @if (count($categories))
                        <select name="category" id="category" class="form-control">
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <select name="category" id="category" class="form-control">
                            <option value="add-new">Add new category</option>
                        </select>
                        <div>
                            <a href="#" class="no-category-present" id="add-new-category">Add New Category</a>
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-12">
                <button type="button" class="btn btn-primary btn-block">Add</button>
            </div>
        </form>
        <div class="card-body">
            <form class="row g-2" id="category-form-field">
                @csrf
                <div class="col-md-3" id="new-category-field" style="display: none;">
                    <div class="form-group">
                        <label for="category">New Category</label>
                        <input type="text" name="category" id="category-add-new" class="form-control" required>
                    </div>
                    <div style="margin-top: 20px;"></div>
                    <div class="col-md-3">
                        <div class="col-12">
                            <button type="button" id="add-category-btn" class="btn btn-primary btn-block"><i
                                    class="fa fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                    <div id="error-message" class="text-danger"></div>
                </div>
            </form>
        </div>
    </div>
</div>
