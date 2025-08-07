<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TestJob2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels,Batchable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
       
		
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
		
		$start = microtime(true);
        for($i = 5000 ; $i < 10000 ; $i++){
			logger('from second');
			logger($i);
		}
		$end = microtime(true);
		$time = $end - $start;
		logger($time);
		
    }
}
