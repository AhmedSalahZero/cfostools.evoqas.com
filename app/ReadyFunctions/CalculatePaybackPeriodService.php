<?php 
namespace App\ReadyFunctions;

use Carbon\Carbon;

class CalculatePaybackPeriodService
{
	public function __calculate(int $studyDurationInYears,array $accumulatedFreeCashFlowForEquity,string $studyStartDateAsString,float $totalEquityInjection ){
		
        $paybackPeriod = 1;
        $paybackDate = null;
		$isMoreThan = true ;
        foreach ($accumulatedFreeCashFlowForEquity as $accumulatedFreeCashFlowForEquity) {
            if ($accumulatedFreeCashFlowForEquity < 0) {
                $paybackPeriod++;
            } else {
                if ($accumulatedFreeCashFlowForEquity >= $totalEquityInjection) {
					$isMoreThan = false ;
					$paybackDate = (Carbon::make($studyStartDateAsString)->addMonths($paybackPeriod - 1)->format('M Y'));
                    break;
                } else {
                    $paybackPeriod++;
                }
            }
        }
		
		$paybackPeriod = number_format($paybackPeriod/ 12 , 1) . ' Years' ;
		if($isMoreThan){
			$paybackPeriod = __('More Than ') . $studyDurationInYears . ' Years' ;
		}
		return [
			$paybackDate =>$paybackPeriod 
		];
	}
}
