<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CasinosController extends Controller
{
	
	public function create(Company $company , Request $request){
		
		return view('casinos.form', $this->getViewVars($company));
	}
	protected function getViewVars(Company $company){
		// $leasingEclAndNewPortfolioFundingRate = $study?  $study->leasingEclAndNewPortfolioFundingRate : null;
		return [
			'company'=>$company ,
			'model'=>$company ,
			'title'=>__('Gaming'),
			'storeRoute'=>route('store.casinos',['company'=>$company->id]),
		];
	}

	public function store(Company $company , Request $request)
	{
		$elementsToDelete = $company->storeRepeaterRelations($request,['casinoNames'],$company);
		deleteTypeIds('casino_names',$elementsToDelete,'casinos','casino_type_id');
		return response()->json([
			'redirectTo'=>route('admin.view.hospitality.sector',['company'=>$company->id])
		]);
	}
	
}
