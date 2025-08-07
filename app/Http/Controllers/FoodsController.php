<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class FoodsController extends Controller
{
	
	public function create(Company $company , Request $request){
		
		return view('foods.form', $this->getViewVars($company));
	}
	protected function getViewVars(Company $company){
		// $leasingEclAndNewPortfolioFundingRate = $study?  $study->leasingEclAndNewPortfolioFundingRate : null;
		return [
			'company'=>$company ,
			'model'=>$company ,
			'title'=>__('Foods'),
			'storeRoute'=>route('store.foods',['company'=>$company->id]),
		];
	}

	public function store(Company $company , Request $request)
	{
		$elementsToDelete = $company->storeRepeaterRelations($request,['foodNames'],$company);
		deleteTypeIds('food_names',$elementsToDelete,'foods','food_type_id');
		
		return response()->json([
			'redirectTo'=>route('admin.view.hospitality.sector',['company'=>$company->id])
		]);
	}
	
}
