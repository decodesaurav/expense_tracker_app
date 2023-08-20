<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Expense;

class ExpenseDisplayController extends Controller
{
    public int $perPage = 10;

    public function index(Request $request) {
        $query = Expense::query(); //base query
        $categories = Category::distinct()->pluck('name');

        // Apply category filter if category is selected
        if ($request->filled('filter')) {
            $categoryFilter = $request->input('filter');
            $query->where('category', $categoryFilter);
        }

        // Apply Sorting
        if ($request->has('sort')) {
            $sort = $request->input('sort');
            $sortBy = [
                'date_asc' => 'date',
                'date_desc' => 'date desc',
                'amount_asc' => 'amount',
                'amount_desc' => 'amount desc',
            ];

            if (array_key_exists($sort, $sortBy)) {
                $query->orderByRaw($sortBy[$sort]);
            }
        }
        //Apply Search Filter
        if($request->has('search')){
            $query->where(function ($query) use ($request){
                $query->where('description','like','%' . $request->input('search') . '%')
                    ->orWhere('category', 'like', '%' . $request->input('search') . '%');
            });
        }
        //Apply Date filtering
        if($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $endDate = date('y-m-d', strtotime($endDate . '+1 day'));
            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }
        }
        $allExpenses = $query->orderBy('date', 'desc')->paginate($this->perPage);
        $expense = ($allExpenses->isEmpty() && !$request->filled('search') && !$request->has('filter')) ? $query->paginate($this->perPage) : null;
        return view('expenses.expense-list',[
            'sortedExpenses' => $expense ?? null,
            'expenses' => $allExpenses,
            'categories' => $categories,
            'request' => $request
        ])->withInput($request->all());
    }
}
