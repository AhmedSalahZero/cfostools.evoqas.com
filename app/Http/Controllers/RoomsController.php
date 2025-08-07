<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomsController extends Controller
{
	
	public function create(Company $company , Request $request){
		
		return view('rooms.form', $this->getViewVars($company));
	}
	protected function getViewVars(Company $company){
		// $leasingEclAndNewPortfolioFundingRate = $study?  $study->leasingEclAndNewPortfolioFundingRate : null;
		return [
			'company'=>$company ,
			'model'=>$company ,
			'title'=>__('Rooms'),
			'storeRoute'=>route('store.rooms',['company'=>$company->id]),
		];
	}

	public function store(Company $company , Request $request)
	{
		$elementsToDelete =  $company->storeRepeaterRelations($request,['roomNames'],$company);
		deleteTypeIds('room_names',$elementsToDelete,'rooms','room_type_id');
		
		// if($showErrorMessage){
		// 	$message = __('Some Items can not be deleted where they are used in some studies');
		// }
		return response()->json([
			'redirectTo'=>route('admin.view.hospitality.sector',['company'=>$company->id]),
			// 'message'=>$message,
			// 'redirectTo'=>route('create.leasing.categories',['company'=>$company->id])
		]);
	}
	
}
