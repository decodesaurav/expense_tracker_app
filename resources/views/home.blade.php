@extends('layouts.dashboard')

@section('content')
	@if (session('status'))
		<div class="alert alert-success" role="alert">
			{{ session('status') }}
		</div>
	@endif
	<div class="card">
		<div class="card-body">
			<p>Total spendings (till date)</p>
			<h3>Nrs.{{ number_format($totalSpending, 2) }}</h3>
		</div>
	</div>
@endsection
