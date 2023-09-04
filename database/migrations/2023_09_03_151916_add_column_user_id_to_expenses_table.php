<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnUserIdToExpensesTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('expenses', function (Blueprint $table) {
			$table->foreignId('user_id')->default(7)->constrained(); // Change the default value as needed
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('expenses', function (Blueprint $table) {
			$table->dropForeign(['user_id']);
			$table->dropColumn('user_id');
		});
	}
}
