<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class CategoryControllerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
		$categories = [
			'Housing',
			'Transportation',
			'Food and Dining',
			'Entertainment',
			'Personal Care',
			'Healthcare',
			'Education',
			'Debts',
			'Savings',
			'Gifts and Donations',
			'Travel',
			'Utilities',
			'Insurance',
			'Taxes',
			'Business Expenses',
			'Childcare and Education',
			'Pets',
			'Miscellaneous',
			'Emergency Expenses'
		];
		foreach ($categories as $category) {
			DB::table('categories')->insert([
				'name' => $category,
			]);
		}
    }
}
