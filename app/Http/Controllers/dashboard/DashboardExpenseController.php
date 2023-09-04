<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers\dashboard;

use App\Services\TotalSpendingService;
use App\Models\Expense;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;



class DashboardExpenseController extends Controller
{
	protected $totalSpendingService;
	protected $data = [];

	public function __construct(TotalSpendingService $totalSpendingService)
	{
		$this->totalSpendingService = $totalSpendingService;
	}

	public function index()
	{
		$this->calculateTotalSpendings();
		return view('home', $this->data);
	}

	public function calculateTotalSpendings()
	{
		$user = Auth::user();
		$totalSpending = $this->totalSpendingService->calculateTotalSpendingTillDate($user);

		$this->data['totalSpending'] = $totalSpending;
	}
}
