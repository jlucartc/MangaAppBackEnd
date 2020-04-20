<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\QueryException;
use App\MangaUpdates;
use Illuminate\Support\Facades\Log;

class InsertUpdates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $updates;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($updates)
    {
        $this->updates = $updates;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        Log::info('Inserting Updates...');

        $copyOfUpdates = $this->updates;

        foreach($this->updates as $update){


            $newUpdate = new MangaUpdates();

            $newUpdate->ChapterCount = $update['ChapterCount'];
            $newUpdate->UpdatedAt = date("Y-M-d H:i:s");
            $newUpdate->MangaName = $update['MangaName'];

            try{

                $newUpdate->save();
                unset($copyOfUpdates[array_search($update,$copyOfUpdates)]);
                Log::info('Update saved...');

            }catch(QueryException $e){

                Log::info('Update failed. Trying again...');
                InsertUpdates::dispatch($copyOfUpdates);

            }


        }



    }
}
