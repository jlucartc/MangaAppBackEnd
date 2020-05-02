<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\QueryException;
use App\MangaUpdates;
use App\Mangas;
use Illuminate\Support\Facades\Log;

class InsertUpdates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $updates;
    protected $tries;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($updates,$tries)
    {
        $this->updates = $updates;
        $this->tries = $tries;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        if($this->tries > 0){

            Log::info('Inserting Updates...');

            $copyOfUpdates = $this->updates;

            foreach($this->updates as $update){


                $newUpdate = new MangaUpdates();

                $newUpdate->ChapterCount = $update['ChapterCount'];
                $newUpdate->UpdatedAt = date("Y-m-d H:i:s");
                $newUpdate->MangaName = $update['MangaName'];

                try{

                    $manga = Mangas::select('CoverLink')
                    ->where('Name','=',$newUpdate->MangaName)
                    ->first();

                    $newUpdate->CoverLink = $manga->CoverLink;
                    $newUpdate->save();
                    unset($copyOfUpdates[array_search($update,$copyOfUpdates)]);
                    Log::info('Update saved...');

                }catch(QueryException $e){

                    Log::info('Update failed. Trying again...');
                    Log::info($e);
                    InsertUpdates::dispatch($copyOfUpdates,$this->tries-1);

                }


            }

        }


    }
}
