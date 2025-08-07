<?php

namespace App\Console\Commands;

use App\Jobs\TestJob1;
use App\Jobs\TestJob2;
use App\Models\Company;
use App\Models\SalesForecast;
use App\Models\SalesGathering;
use App\ReadyFunctions\IntervalSummationOperations;
use App\Services\Caching\CashingService;
use App\Services\Caching\CustomerDashboardCashing;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Async\Pool;
use Throwable;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Code Command';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
		foreach(Company::all() as $company){
			$company->insertBasicSelects();
		}
	}

	
}
