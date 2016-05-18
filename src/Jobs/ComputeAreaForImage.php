<?php

namespace Dias\Modules\Laserpoints\Jobs;

use DB;
use Log;
use Dias\Jobs\Job;
use Dias\Image;
use Dias\Transect;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ComputeAreaForImage extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * The project for which the report should be generated.
     *
     * @var Project
     */
    private $image;
    private $transect;
    private $laserdist;
    /**
     * Create a new job instance.
     *
     * @param Project $project The project for which the report should be generated.
     *
     * @return void
     */
    public function __construct(Image $image, Transect $transect, $laserdist)
    {
        $this->image = $image;
        $this->transect = $transect;
        $this->laserdist = $laserdist;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::reconnect();
        $output = [];
        $err_code = 0;
        exec('/usr/bin/python /home/vagrant/dias/vendor/dias/laserpoints/src/Scripts/detect.py '.$this->transect->url."/".$this->image->filename." ".$this->laserdist,$output,$err_code);
        if ($err_code ==0){
            $ret = json_decode($output[0],true);
            $jsfield = DB::select("select metainfo from images where id=?",[$this->image->id]);
            $tmp = json_decode($jsfield[0]->metainfo,true);
            if (is_null($tmp)){
                $tmp=array();
            }
            $tmp["area"] = $ret["area"];
            $tmp["px"] = $ret["px"];
            $tmp["numLaserpoints"] = $ret["numLaserpoints"];
            DB::update("update images set metainfo =? where id =?",[json_encode($tmp),$this->image->id]);
        }else{
            Log::warning("laserpoint job for image with ID ".$this->image->id."ended with error code ".$err_code);
        }
    }
}
