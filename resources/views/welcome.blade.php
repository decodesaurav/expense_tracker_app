<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="{{ asset('css/home.css') }}">

</head>
<body class="antialiased">
	<main class="main">
		<section class="hero">
			<div class="container">
				<h2 class="hero-title">Track Your Expenses</h2>
				<p class="hero-subtitle">Manage your finances with ease</p>
				<a href="{{ route('login') }}" class="btn btn-primary">Login</a>
				<a href="{{ route('register') }}" class="btn btn-primary">Sign-up</a>
			</div>
		</section>

		<section class="features">
			<div class="container">
				<div class="feature">
					<h3 class="feature-title">Expense Logging</h3>
					<p class="feature-description">Record your expenses and categorize them for better management.</p>
				</div>
				<div class="feature">
					<h3 class="feature-title">Budget Tracking</h3>
					<p class="feature-description">Set budgets and monitor your spending to achieve your financial goals.</p>
				</div>
				<div class="feature">
					<h3 class="feature-title">Visual Reports</h3>
					<p class="feature-description">View clear and insightful reports to understand your spending habits.</p>
				</div>
			</div>
		</section>
	</main>
</body>
</html>
