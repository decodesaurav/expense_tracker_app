<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Category;

/**
 * @method static create(array $array)
 * @method static findOrFail($id)
 */
class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'date',
        'description',
        'category',
        'category_id'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
