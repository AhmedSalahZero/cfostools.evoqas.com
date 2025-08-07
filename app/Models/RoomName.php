<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RoomName extends Model
{
	protected $table = 'room_names';
	protected $guarded = [];
	public static function booted()
	{
		// static::saved(function(self $model){
		// 	DB::table('rooms')->where('room_type_id',$model->id)->update([
		// 		'name'=>$model->getName()
		// 	]);
		// });
		// static::deleting(function(self $model){

		// 	DB::table('rooms')->where('room_type_id',$model->id)->delete();
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
