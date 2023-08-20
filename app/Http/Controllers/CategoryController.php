<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Category::all();
        return response()->json($categories);
    }

    public function storeCategories(Request $request)
    {
        $categoryName = $request->input('category');
        $existingCategory = Category::where('name', $categoryName)->first();

        $validatedData = $request->validate([
            'category' => 'required|unique:categories,name',
        ]);

        // Create a new category and save it to the database
        Category::create([
            'name' => $validatedData['category'],
        ]);

        $lastCategory = Category::select('id', 'name')->latest()->first();

        if ($lastCategory) {
            return $lastCategory;
        } else {
            return ('Category Not found');
        }
    }

    public function createCategories()
    {
        return view('categories.create');
    }
}
