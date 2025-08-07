<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		// DB::table('rooms')->delete();
		// Schema::table('rooms',function(Blueprint $table){
		// 	$table->dropColumn('room_type_id');
		// });
		// Schema::table('rooms',function(Blueprint $table){
		// 	$table->unsignedBigInteger('room_type_id')->nullable();
		// 	$table->foreign('room_type_id')->references('id')->on('room_names');
		// });
		
		foreach(['room','food','casino','meeting','other'] as $name){
			DB::table($name.'s')->delete();
			Schema::table($name.'s',function(Blueprint $table) use ($name){
				$table->dropColumn($name.'_type_id');
			});
			Schema::table($name.'s',function(Blueprint $table) use($name){
				$table->unsignedBigInteger($name.'_type_id')->nullable();
				$table->foreign($name.'_type_id')->references('id')->on($name.'_names');
			});
		}
		
		
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
