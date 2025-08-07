<?php

namespace App\Providers;

use App\Http\Controllers\ExportTable;
use App\Models\Company;
use App\Models\HospitalitySector;
use App\Models\IncomeStatementItem;
use App\Models\Language;

use App\Models\Section;

use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}

	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	
	
	public function boot(Request $request)
	{
		
		ini_set('max_execution_time', 6000); //300 seconds = 5 minutes

		
		Collection::macro('filterByDateColumn',function(string $dateColumnName,?string $startDate, ?string $endDate  ){
			/**
			 * @var Collection $this 
			 */
			return $this->when($startDate && $endDate ,function(Collection $items) use ($startDate,$endDate,$dateColumnName){
				return $items->where($dateColumnName,'>=',Carbon::make($startDate)->startOfDay())->where($dateColumnName,'<=',Carbon::make($endDate)->endOfDay());
			}) ;
		});
		
		
		
		$yearIndexWithYear = [];
		$dateIndexWithDate = [];
		$dateWithDateIndex = [];
		$studyStartDate = null;
		$studyEndDate = null;
		
		$hospitalitySectorId = $request->segment(4);
		if(is_numeric($hospitalitySectorId)){
			$hospitalitySector = HospitalitySector::find($hospitalitySectorId);
			if($hospitalitySector){
				$datesAndIndexesHelpers = $hospitalitySector->getDatesIndexesHelper();
				$datesIndexWithYearIndex=$datesAndIndexesHelpers['datesIndexWithYearIndex']; 
				$yearIndexWithYear=$datesAndIndexesHelpers['yearIndexWithYear']; 
				$dateIndexWithDate=$datesAndIndexesHelpers['dateIndexWithDate']; 
				$dateIndexWithMonthNumber=$datesAndIndexesHelpers['dateIndexWithMonthNumber']; 
				$dateWithMonthNumber=$datesAndIndexesHelpers['dateWithMonthNumber']; 
				$dateWithDateIndex=$datesAndIndexesHelpers['dateWithDateIndex']; 
				app()->singleton('datesIndexWithYearIndex',function() use ($datesIndexWithYearIndex){
					return $datesIndexWithYearIndex;
				});
				app()->singleton('yearIndexWithYear',function() use ($yearIndexWithYear){
					return $yearIndexWithYear;
				});
				
				app()->singleton('dateIndexWithDate',function() use ($dateIndexWithDate){
					return $dateIndexWithDate;
				});
				app()->singleton('dateWithMonthNumber',function() use ($dateWithMonthNumber){
					return $dateWithMonthNumber;
				});
				app()->singleton('dateIndexWithMonthNumber',function() use ($dateIndexWithMonthNumber){
					return $dateIndexWithMonthNumber;
				});
				app()->singleton('dateWithDateIndex',function() use ($dateWithDateIndex){
					return $dateWithDateIndex;
				});
			}
			
		}
		
		
		View::share('langs', Language::all());
		View::share('lang', app()->getLocale());
		View::share('yearIndexWithYear', $yearIndexWithYear);
		View::share('dateIndexWithDate', $dateIndexWithDate);
		View::share('dateWithDateIndex', $dateWithDateIndex);
		View::share('studyStartDate', $studyStartDate);
		View::share('studyEndDate', $studyEndDate);
		$currentCompany = Company::find(Request()->segment(2));
		if ($currentCompany) {
			View::share('exportables', (new ExportTable)->customizedTableField($currentCompany, 'SalesGathering', 'selected_fields'));
			View::share('exportablesForUploadExcel', (new ExportTable)->customizedTableField($currentCompany, 'UploadExcel', 'selected_fields'));
		}
		View::composer('*', function ($view) {

			$requestData = Request()->all();
			if (isset($requestData['start_date']) && isset($requestData['end_date'])) {
				$view->with([
					'start_date' => $requestData['start_date'],
					'end_date' => $requestData['end_date'],
				]);
			} elseif (isset($requestData['date'])) {
				$view->with([
					'date' => $requestData['date']
				]);
			}
		});

		View::composer('*', function ($view) {
			if (Auth::check()) {


				if (request()->route()->named('home') || (!isset(request()->company))) {
					$sections = [Section::with('subSections')->find(2)];
					$view->with('client_sections', $sections);
				} else {
					$view->with('client_sections', Section::mainClientSideSections()->with('subSections')->get());
				}
				if (Auth::user()->hasrole('super-admin')) {
					$view->with('super_admin_sections', Section::mainSuperAdminSections()->get());
				}
			}
		});
	}

}
