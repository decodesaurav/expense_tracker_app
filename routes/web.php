<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ExpenseDisplayController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home')->middleware('auth', 'role:editor,admin');

//User Profile
Route::get('/profile', [ProfileController::class, 'show'])->name('profile')->middleware('auth');
Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update')->middleware('auth');

//Expenses CRUD
Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');

//Category
Route::get('/categories', [CategoryController::class, 'categories'])->name('categories.index');
Route::get('/categories/create', [CategoryController::class, 'createCategories'])->name('categories.create');
Route::post('/categories', [CategoryController::class, 'storeCategories'])->name('categories.store')->middleware('auth');

//CRUD Expense
Route::get('/expenses/{id}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->name('expenses.update');
Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

//My Spendings Page
Route::get('/expense-list', [ExpenseDisplayController::class, 'index'])->name('expenses.show');
