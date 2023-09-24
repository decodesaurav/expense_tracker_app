<?php

namespace App\Services;

use App\Models\Expense;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Models\Category;

class TotalSpendingService
{
	public $sortByDate;

	public function __construct()
	{
		$this->sortByDate = [
			'today' => Carbon::today(),
			'customDays' => Carbon::now()->subDays(3)->startOfDay(),
			'thisWeek' => Carbon::now()->subDays(7)->startOfDay(),
			'thisMonth' => Carbon::now()->subMonth()->startOfMonth()
		];
	}

	public function calculateTotalSpendingTillDate($user)
	{
		try{
			$userCreationDate = Carbon::parse($user->created_at);
			$currentDate = Carbon::now();

			return Expense::where('user_id', $user->id)
				->whereBetween('date', [$userCreationDate, $currentDate])
				->sum('amount');

		} catch (QueryException $e) {
			Log::error("Error calculating total spending: ", $e->getMessage());
			throw $e;
		}
	}

	public function filterByDate($user, $selectedCategory)
	{
		try{
			$now = Carbon::now();
			$startDate = null;
			$endDate = null;

			switch($selectedCategory) {
				case 'total':
					$startDate = Carbon::parse($user->created_at);
					$endDate = $now;
					break;
				case 'today':
					$startDate = $this->sortByDate['today'];
					$endDate  = $now;
					break;
				case 'customDays':
					$startDate = $this->sortByDate['customDays'];
					$endDate = $now;
					break;
				case 'thisWeek':
					$startDate = $this->sortByDate['thisWeek'];
					$endDate = $now;
					break;
				case 'thisMonth':
					$startDate = $this->sortByDate['thisMonth'];
					$endDate = $now;
					break;
				default:
					throw new Exception('Invalid selectedCategory');
			}

			$filteredExpense = Expense::whereBetween('date', [$startDate, $endDate])->sum('amount');
			return $filteredExpense;

		} catch ( Exception $e ) {
			Log::error('The error in filtering dashboard expense' . $e->getMessage());
			throw $e;
		}
	}

	public function filterByCategory($user, $selectedCategoryId)
	{
		try {
			$userId = $user->id;			
			if ($selectedCategoryId && $userId){
				$filteredExpense = Expense::where('category_id', $selectedCategoryId)
											->where('user_id', $userId)
											->sum('amount');
			} else {
				return false;
			}
			return $filteredExpense;
		} catch (\Exception $e) {
			Log::error('The error in filtering dashboard expense' . $e->getMessage());
			throw $e;
		}
	}
}
