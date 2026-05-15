<?php

namespace App\Models;

use App\Models\Traits\UserOwned;
use Illuminate\Database\Eloquent\Model;

class Feeders extends Model
{
    use UserOwned;

    protected $table = 'feeders';

    protected $fillable = ['hog_pen_id', 'device_id', 'status', 'last_refill'];

    public function hogpen()
    {
        return $this->belongsTo(Hogpens::class, 'hog_pen_id');
    }

    public function iotDevice()
    {
        return $this->belongsTo(IotDevices::class, 'device_id');
    }

    public function feedingLogs()
    {
        return $this->hasMany(FeedingLogs::class, 'feeder_id');
    }

    //
}
