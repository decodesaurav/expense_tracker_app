<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static where(string $string, mixed $category)
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

	public function expenses()
	{
    	return $this->hasMany(Expense::class);
	}
}
