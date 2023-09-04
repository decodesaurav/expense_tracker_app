<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Support\Carbon;

class TotalSpendingService
{
	public function calculateTotalSpendingTillDate($user)
	{
		$userCreationDate = Carbon::parse($user->created_at);
		$currentDate = Carbon::now();

		return Expense::where('user_id', $user->id)
			->whereBetween('date', [$userCreationDate, $currentDate])
			->sum('amount');
	}
}
