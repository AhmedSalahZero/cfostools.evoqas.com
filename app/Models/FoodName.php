<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FoodName extends Model
{
	protected $table = 'food_names';
	protected $guarded = [];
	public static function booted()
	{
		
		// static::deleted(function(self $model){
		// 	DB::table('foods')->where('food_type_id',$model->id)->delete();
		// });
	}
	
	public function getTitle():string 
	{
		return $this->title;
	}
	public function getName():string
	{
		return $this->getTitle();
	}

	public function isActive():bool 
	{
		return (bool)$this->is_active; 
	}
	
	
	
}
