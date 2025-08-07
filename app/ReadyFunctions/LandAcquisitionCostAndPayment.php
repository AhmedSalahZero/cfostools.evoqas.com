<?php

namespace App\ReadyFunctions;

use App\Models\HospitalitySector;

class LandAcquisitionCostAndPayment
{
	public function __calculate(int $purchaseDateAsIndex, float $purchaseCost, float $contingencyRate, string $paymentMethodType, float $downPaymentOneRate, float $balanceRate, int $installmentCount, string $installmentInterval, float $downPaymentTwoRate, int $landAfterMonths, array $customCollectionPolicyValue = [], HospitalitySector $hospitalitySector = null, float $equityFundingRate, float $debtFundingRate,array $datesAsStringAndIndex,array $dateIndexWithDate,array $dateWithDateIndex)
	{
		// $paymentMethodType [cash , customize , installment]

		$totalLandPurchaseCost = $purchaseCost * (1+($contingencyRate/100));

		$landPayments = $this->calculateLandPayments($purchaseDateAsIndex, $totalLandPurchaseCost, $paymentMethodType, $downPaymentOneRate, $balanceRate, $installmentCount, $installmentInterval, $downPaymentTwoRate, $landAfterMonths, $customCollectionPolicyValue,$datesAsStringAndIndex,$dateIndexWithDate,$dateWithDateIndex, $hospitalitySector);

		return  $this->calculateLandLoanWithdrawal($landPayments, $totalLandPurchaseCost, $equityFundingRate, $debtFundingRate);
	}

	public function calculateLandPayments(int $purchaseDateAsIndex, float $totalPurchaseCost, string $paymentMethodType, float $downPaymentOneRate, float $balanceRate, int $installmentCount, string $installmentInterval, float $downPaymentTwoRate, int $landAfterMonths, array $customCollectionPolicyValue,array $datesAsStringAndIndex,array $dateIndexWithDate,array $dateWithDateIndex, HospitalitySector $hospitalitySector = null):array
	{
		$landPayments  = [];
	
		switch($paymentMethodType) {
			case 'cash':
				$landPayments = [
					$purchaseDateAsIndex => $totalPurchaseCost
				];
				
				break;
			case 'installment':
				$installmentMethod = new InstallmentMethod();
				$landPayments = $installmentMethod->__calculate($purchaseDateAsIndex, $totalPurchaseCost, $downPaymentOneRate, $balanceRate, $installmentCount, $installmentInterval, $downPaymentTwoRate, $landAfterMonths);
				break;
			case 'customize':
				$collectionPolicyService = new CollectionPolicyService;
				$landPayments = $collectionPolicyService->applyCollectionPolicy(true, 'customize', $customCollectionPolicyValue, [$purchaseDateAsIndex=>$totalPurchaseCost],$dateIndexWithDate,$dateWithDateIndex, $hospitalitySector);
				break;
		}
		return $landPayments;
	}

	protected function calculateEquityFundingAmount(float $totalLandPurchaseCost, float $equityFundingRate)
	{
		return $totalLandPurchaseCost * ($equityFundingRate / 100);
	}

	public function calculateLandLoanWithdrawal(array $landPayments, float $totalLandPurchaseCost, float $equityFundingRate)
	{
		$equityFundingAmount = $this->calculateEquityFundingAmount($totalLandPurchaseCost, $equityFundingRate);
		$landLoanWithdrawal = [];
		$isFirstNestedIf = true;
		foreach ($landPayments as $dateAsIndex=>$landPayment) {
			$previousIndex = $dateAsIndex-1;
			$equityPaymentBalance[$dateAsIndex]  = $equityFundingAmount - $landPayment;
			$equityFundingAmount = $equityPaymentBalance[$dateAsIndex];
			if ($equityPaymentBalance[$dateAsIndex] < 0) {
				if ($isFirstNestedIf) {
					$landLoanWithdrawal[$dateAsIndex] = $equityPaymentBalance[$dateAsIndex] * -1;
				} else {
					$landLoanWithdrawal[$dateAsIndex] =  ($equityPaymentBalance[$dateAsIndex] - ($equityPaymentBalance[$previousIndex]??0) ) * -1;
				}
				$isFirstNestedIf = false;
			}
		}
	
		if (array_sum($landLoanWithdrawal) > -1 && array_sum($landLoanWithdrawal) < 1) {
			return [];
		}
		return $landLoanWithdrawal;
	}

	public function calculateLandEquityPayment(array $landPayments, float $totalLandPurchaseCost, float $equityFundingRate)
	{
		$equityFundingAmount = $this->calculateEquityFundingAmount($totalLandPurchaseCost, $equityFundingRate);
		$landEquityPayment = [];
		$remainingEquityFunding = [];
		$firstLoop = true;
		foreach ($landPayments as $dateIndex => $landPaymentValue) {
			$nextDateIndex = getNextDate($landPayments,$dateIndex);
			if ($firstLoop) {
				$remainingEquityFunding[$dateIndex] = $equityFundingAmount;
				$firstLoop= false;
			}
			if ($remainingEquityFunding[$dateIndex] >= $landPaymentValue) {
				$landEquityPayment[$dateIndex] = $landPaymentValue;
				if($nextDateIndex){
					$remainingEquityFunding[$nextDateIndex] = $remainingEquityFunding[$dateIndex] -$landEquityPayment[$dateIndex];
				}
			} else {
				$landEquityPayment[$dateIndex] = $remainingEquityFunding[$dateIndex];
				if($nextDateIndex){
					$remainingEquityFunding[$nextDateIndex] = $remainingEquityFunding[$dateIndex] -$landEquityPayment[$dateIndex];
				}
			}
		}

		return $landEquityPayment;
	}
}
