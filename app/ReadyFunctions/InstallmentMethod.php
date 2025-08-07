<?php

namespace App\ReadyFunctions;

use Carbon\Carbon;

class InstallmentMethod
{
	public function __calculate(
		int $startDate,
		float $amount,
		float $downPaymentOneRate,
		float $balanceRate,
		float $installmentCount,
		string $installmentInterval,
		float $downPaymentTwoRate = 0,
		int $downPaymentTwoMonth = 0
	):array {
		$downPaymentOneAmount = $downPaymentOneRate / 100 * $amount;
		$downPaymentOneDateAsIndex = $startDate;
		$downPaymentTwoDateAsIndex =  $startDate+$downPaymentTwoMonth ;
		$downPaymentTwoAmount =  $downPaymentTwoRate / 100 * $amount;
		$balanceAmount = 	$balanceRate / 100 * $amount;
		$installmentAmount = $balanceAmount / $installmentCount;
		if ($downPaymentTwoMonth == 0) {
			$installmentsDates = [
				$downPaymentOneDateAsIndex => $downPaymentOneAmount + $downPaymentTwoAmount,
			];
		} else {
			$installmentsDates = [
				$downPaymentOneDateAsIndex => $downPaymentOneAmount,
				$downPaymentTwoDateAsIndex => $downPaymentTwoAmount
			];
		}

		$installmentDuration = ($installmentInterval == 'monthly' ? 1 : ($installmentInterval == 'quarterly' ? 3 : ($installmentInterval == 'semi-annually' ? 6 : ($installmentInterval == 'annually' ? 12 : 0))));
		for ($i = 1; $i <= $installmentCount; $i++) {
			$currentInstallmentDateAsIndex = $downPaymentTwoDateAsIndex + $installmentDuration ;
			$installmentsDates[$currentInstallmentDateAsIndex] = $installmentAmount;
		}
		return $installmentsDates;
	}
}
