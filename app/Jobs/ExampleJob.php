<?php

namespace App\Jobs;
// use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class ExampleJob extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        Log::info(mt_rand());
    }
}
