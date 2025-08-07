<?php 
namespace App\Tasks;
use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
class LoanTask implements Task
{
    /**
     * Executes the task.
     *
     * @return \Amp\Promise
     */
	public function __construct($calculateFixedLoanAtEndService ,$loanType  , $loanStartDate,$loanAmount,$baseRate,$marginRate,$tenor,$installmentPaymentIntervalName,$stepUpRate,$stepUpIntervalName,$stepDownRate,$stepDownIntervalName,$gracePeriod) {
		$this->calculateFixedLoanAtEndService = $calculateFixedLoanAtEndService;
		$this->loanType = $loanType;
		$this->loanStartDate=$loanStartDate;
		$this->loanAmount=$loanAmount;
		$this->baseRate=$baseRate;
		$this->marginRate=$marginRate;
		$this->tenor= $tenor ;
		$this->installmentPaymentIntervalName=$installmentPaymentIntervalName;
		$this->stepUpRate=$stepUpRate;
		$this->stepUpIntervalName=$stepUpIntervalName;
		$this->stepDownRate=$stepDownRate;
		$this->stepDownIntervalName=$stepDownIntervalName;
		$this->gracePeriod= $gracePeriod;
    }

    public function run(Channel $channel, Cancellation $cancellation): string
    {
		for($i = 0 ; $i<200  ; $i++){
			$this->calculateFixedLoanAtEndService->__calculate($this->loanType,$this->loanStartDate,$this->loanAmount,$this->baseRate,$this->marginRate,$this->tenor,$this->installmentPaymentIntervalName,$this->stepUpRate,$this->stepUpIntervalName,$this->stepDownRate,$this->stepDownIntervalName,$this->gracePeriod);
		}
		return 'salah';
        // return file_get_contents($this->url); // Example blocking function
    }
}
