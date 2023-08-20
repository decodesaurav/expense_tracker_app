<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Expense;

class ExpenseDisplayController extends Controller
{
    public int $perPage = 10;
    public array $sortOptions = [
        'date_asc' => 'Date (Oldest First)',
        'date_desc' => 'Date (Newest First)',
        'yesterday' => 'Yesterday',
        'today' => 'Today',
        'last_three_days' => 'Last 3 days',
        'last_seven_days' => 'Last 7 days',
        'last_month' => 'Last month',
        'last_three_months' => 'Last 3 months',
    ];
    private array $sortByAmount = [
        'amount_asc' => 'Amount (Low to High)',
        'amount_desc' => 'Amount (High to Low)'
    ];

    private function applySorting($query, $sort, $column): void
    {
        $sortBy = [
            'date_asc' => 'date',
            'date_desc' => 'date desc',
            'amount_asc' => 'amount',
            'amount_desc' => 'amount desc',
        ];
        if (array_key_exists($sort, $sortBy)) {
            $query->orderByRaw($sortBy[$sort]);
        }
        if ($column === 'date') {
            $this->applyCustomSorting($query, $sort);
        }
    }

    private function applyCustomSorting($query, $sort): void
    {
        $sortByOptions = [
            'yesterday' => Carbon::yesterday(),
            'today' => Carbon::today(),
            'last_three_days' => Carbon::now()->subDays(3)->startOfDay(),
            'last_seven_days' => Carbon::now()->subDays(7)->startOfDay(),
            'last_month' => Carbon::now()->subMonth()->startOfMonth(),
            'last_three_months' => Carbon::now()->subMonths(3)->startOfMonth(),
        ];

        if (array_key_exists($sort, $sortByOptions)) {
            $query->whereDate('date', '>=', $sortByOptions[$sort]);
        }
    }

    public function index(Request $request)
    {
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
            $sortAmount = $request->input('sort-amount');
            if ($sortAmount === 'amount_asc' || $sortAmount === 'amount_desc') {
                $this->applySorting($query, $sortAmount, 'amount');
            }
            $this->applySorting($query, $sort, 'date');
        }

        //Apply Search Filter
        if ($request->has('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('description', 'like', '%' . $request->input('search') . '%')
                    ->orWhere('category', 'like', '%' . $request->input('search') . '%');
            });
        }
        //Apply Date filtering
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $endDate = date('y-m-d', strtotime($endDate . '+1 day'));
            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }
        }
        $allExpenses = $query->orderBy('date', 'desc')->paginate($this->perPage);
        $expense = ($allExpenses->isEmpty() && !$request->filled('search') && !$request->has(
                'filter'
            )) ? $query->paginate($this->perPage) : null;
        return view('expenses.expense-list', [
            'sortedExpenses' => $expense ?? null,
            'expenses' => $allExpenses,
            'categories' => $categories,
            'request' => $request,
            'sortOptions' => $this->sortOptions,
            'sortAmount' => $this->sortByAmount
        ])->withInput($request->all());
    }
}
