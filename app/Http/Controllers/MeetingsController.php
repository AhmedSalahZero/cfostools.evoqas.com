<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class MeetingsController extends Controller
{
	
	public function create(Company $company , Request $request){
		
		return view('meetings.form', $this->getViewVars($company));
	}
	protected function getViewVars(Company $company){
		// $leasingEclAndNewPortfolioFundingRate = $study?  $study->leasingEclAndNewPortfolioFundingRate : null;
		return [
			'company'=>$company ,
			'model'=>$company ,
			'title'=>__('Meetings'),
			'storeRoute'=>route('store.meetings',['company'=>$company->id]),
		];
	}

	public function store(Company $company , Request $request)
	{
		$elementsToDelete = $company->storeRepeaterRelations($request,['meetingNames'],$company);
		deleteTypeIds('meeting_names',$elementsToDelete,'meetings','meeting_type_id');
		
		return response()->json([
			'redirectTo'=>route('admin.view.hospitality.sector',['company'=>$company->id])
		]);
	}
	
}
