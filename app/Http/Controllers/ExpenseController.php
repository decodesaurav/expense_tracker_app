<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Expense;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
	public function index()
	{
		//First of all, fetch expenses from the database
		$expenses = Expense::orderBy('created_at', 'desc')->take(10)->get(); // Fetch the latest 10 expenses
		$showForm = true;
		return view('expenses.index', compact('showForm', 'expenses'));
	}

	public function create()
	{
		return view('expenses.create');
	}

	public function update(Request $request, $id)
	{
		$expense = Expense::findOrFail($id);
		$validatedData = $request->validate([
			'amount' => 'required|numeric',
			'date'   =>  'required|date',
			'description'   =>  'required|string|max:255',
			'category' => 'required|string',
			'category_id' => 'nullable',
			'new_category' => 'nullable',
		]);
		$category_model = Category::where('name', $validatedData['category'])->first();
		$category_id = $category_model->id;
		unset($validatedData['category_id']);

		// Update the category_id as well
		$validatedData['category_id'] = $category_id;
		//Update the expense
		$expense->update($validatedData);

		return back()->with('success', 'Expense updated successfully');
	}
	public function destroy($id)
	{
		$expense = Expense::findOrFail($id);
		$expense->delete();
		// Redirect or return a response
		return response()->json($expense->description, 200);
	}

	public function store(Request $request)
	{
		//validate the data first
		$validatedData = $request->validate([
			'amount' => 'required|numeric',
			'date'   =>  'date_format:Y-m-d|before:now',
			'description'   =>  'required|string|max:255',
			'category' => 'required|string',
			'category_id' => 'nullable',
			'new_category' => 'nullable',
		]);
		$category = $request->input('category');
		$categoryName = Category::where('id', $category)->value('name');

		//Save the new expense to the database
		$expense = Expense::create([
			'amount' => $validatedData['amount'],
			'date'   =>  $validatedData['date'],
			'description'   =>  $validatedData['description'],
			'category_id' => $category,
			'category'     => $categoryName,
			'user_id' => Auth::user()->id,
		]);

		//return to display
		return response()->json([
			'message' => 'Expense added successfully',
			'expense' => $expense,
			'status'  => 200
		]);
	}

	/**
	 * @param Request $request
	 * @param $id
	 *
	 */
	protected function edit(Request $request, $id)
	{
		$expense = Expense::findOrFail($id);
		$categories = Category::all();
		return response()->json([
			'amount' => $expense->amount,
			'date'  => $expense->date,
			'description' => $expense->description,
			'categories' => $categories,
		]);
	}
}
