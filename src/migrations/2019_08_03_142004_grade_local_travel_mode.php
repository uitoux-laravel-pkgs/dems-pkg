<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class GradeLocalTravelMode extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		Schema::create('grade_local_travel_mode', function (Blueprint $table) {
			$table->unsignedInteger('grade_id');
			$table->unsignedInteger('local_travel_mode_id');

			$table->foreign('grade_id')->references('id')->on('entities')->onDelete('cascade')->onUpdate('cascade');
			$table->foreign('local_travel_mode_id')->references('id')->on('entities')->onDelete('cascade')->onUpdate('cascade');

			$table->unique(["grade_id", "local_travel_mode_id"]);
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		Schema::dropIfExists('grade_local_travel_mode');
	}
}
