<?php

namespace App\Models\Traits\Relations;

use App\Models\DepartmentExpense;
use App\Models\HospitalitySector;

trait RoomRelation
{
	public function hospitalitySector()
	{
		return $this->belongsTo(HospitalitySector::class, 'hospitality_sector_id');
	}

}
