<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="{{ asset('js/components/dashboard.js') }}" defer></script>
@extends('layouts.dashboard')

@section('content')
<link href="{{asset('css/dashboard.css')}}" rel="stylesheet">
	@if (session('status'))
		<div class="alert alert-success" role="alert">
			{{ session('status') }}
		</div>
	@endif
	<div class="dashboard-container">
		<div class="card dashboad-tab">
			<div class="card-body dashboard-tab-head">
				<div class="tab first-content">
					<p>Total spendings (By time)</p>
					<h3 id="totalSpendings">Nrs.{{ number_format($totalSpending, 2) }}</h3>
				</div>
				<div class="tab second-content">
					<label for="timeFilter">Filter</label>
					<select id="timeDropdown">
						<option value="total">All time</option>
						<option value="today">Today</option>
						<option value="customDays">Last three days</option>
						<option value="thisWeek">This week</option>
						<option value="thisMonth">This month</option>
					</select>
				</div>
			</div>
		</div>
		<div class="card dashboad-tab-2">
			<div class="card-body dashboard-tab-head">
				<div class="tab first-content">
					<p>Total spendings (By category)</p>
					<h3 id="totalSpendingsByCategory">Nrs. 0</h3>
				</div>
				<div class="tab second-content-2">
					<label for="categoryFilter">Filter</label>
					@if (count($categories))
						<select id="categoryExpenseFilter">
							<option value="select-one">Select One</option>
							@foreach ($categories as $category)
								<option value="{{ $category->id }}">{{ $category->name }}</option>
							@endforeach
						</select>
					@endif
				</div>
			</div>
		</div>
	</div>
	<div class="row mt-3">
		<div class="col-md-5">
			<div class="card">
				<div class="card-header">
					<h5><b>Expense Pie Chart</b></h5>
				</div>
				<div class="card-body d-flex justify-content-center align-items-center">
					<canvas id="expensePieChart" width="400" height="400"></canvas>
				</div>
			</div>
		</div>
		<div class="col-md-4">
			<div class="card">
				<div class="card-header">
					<h5>Latest Transactions</h5>
				</div>
				<div class="card-body">
					<table class="table">
						<thead>
							<tr>
								<th>Name</th>
								<th>Amount</th>
							</tr>
						</thead>
						<tbody>
							@php
								$allExpenses = $categories->flatMap(function ($category) {
									return $category->expenses;
								});
							
								$latestExpenses = $allExpenses->sortByDesc('created_at')->take(5);
							@endphp
							@foreach ($latestExpenses as $expense)
								<tr>
									<td>{{ $expense->description }}</td>
									<td>Nrs.{{ number_format($expense->amount, 2) }}</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
	<div class="row mt-3 second-content d-flex align-items-center justify-content-center">
		<div class="col-md-6">
			<div class="mt-4">
				<div class="card">
					<div class="card-header">
						<h5><b>Expense Line Chart</b></h5>
					</div>
					<div class="card-body">
						<canvas id="expenseTimelineChart" width="400" height="200"></canvas>
					</div>
				</div>
			</div>
		</div>
		<div class="col-md-2">
			<div class="mt-4">
				&nbsp;
			</div>
		</div>
	</div>	
	<script type="module" src="{{ asset('js/components/chart.js') }}"></script>
@endsection
