<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers\dashboard;

use App\Services\TotalSpendingService;
use App\Models\Expense;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


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
		$category = Category::with('expenses')->get();
		$this->data['categories'] = $category;
		return view('home', $this->data);
	}

	public function calculateTotalSpendings()
	{
		$user = Auth::user();
		$totalSpending = $this->totalSpendingService->calculateTotalSpendingTillDate($user);

		$this->data['totalSpending'] = $totalSpending;
	}

	public function filter(Request $request, $selectedTime )
	{
		try{
			$user = Auth::user();
			if ($selectedTime){
				$filterByDate = $this->totalSpendingService->filterByDate($user, $selectedTime);
			}
			return response()->json([
				'filteredExpense' => $filterByDate
			],
				$status = 200,
			);
		} catch (\Exception $e ){
			Log::error('The error in filtering dashboard expense' . $e->getMessage());
			throw $e;
		}
	}

	public function filterByCategory( $selectedCategory ) {
		try{
			$user = Auth::user();
			$filterByCategory = $this->totalSpendingService->filterByCategory($user, $selectedCategory);
			return response()->json([
				'filterByCategory' => $filterByCategory
			],
				$status = 200,
			);
		} catch (\Exception $e ){
			Log::error('The error in filtering dashboard expense' . $e->getMessage());
			throw $e;
		}
	}
	public function getExpenseByCategory(){
		try{
			$expenses = Expense::select(DB::raw('category, SUM(amount) as total_amount'))
				->groupBy('category')
				->get();
			$totalSum = $expenses->sum('total_amount');
			$expenses->each(function ($expense) use ($totalSum) {
				$expense->percentage = ($expense->total_amount / $totalSum) * 100;
			});

			$timelineData = Expense::select(DB::raw('DATE_FORMAT(date, "%M %Y") AS month'), DB::raw('SUM(amount) AS total_amount'))
			->groupBy(DB::raw('DATE_FORMAT(date, "%Y-%m")'), 'date')
			->orderBy(DB::raw('DATE_FORMAT(date, "%Y-%m")'))
			->get();
			$timelineLabels = $timelineData->pluck('month');
    		$timelineValues = $timelineData->pluck('total_amount');

			return response()->json ([
				'pieChart' => $expenses,
				'timeline' => [
					'labels' => $timelineLabels,
					'data' => $timelineValues,
				],
			]);
			} catch (\Exception $e ){
			Log::error('The error in filtering expense pie chart dashboard' . $e->getMessage());
			throw $e;
		}
	}
}