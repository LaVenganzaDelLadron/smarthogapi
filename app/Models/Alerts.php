<?php

namespace App\Models;

use App\Models\Traits\UserOwned;
use Illuminate\Database\Eloquent\Model;

class Alerts extends Model
{
    use UserOwned;

    protected $table = 'alerts';

    protected $fillable = ['farm_id', 'hog_pen_id', 'type', 'message', 'severity', 'status'];

    public function farm()
    {
        return $this->belongsTo(Farms::class, 'farm_id');
    }

    public function hogpen()
    {
        return $this->belongsTo(Hogpens::class, 'hog_pen_id');
    }

    //
}
