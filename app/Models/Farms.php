<?php

namespace App\Models;

use App\Models\Traits\UserOwned;
use Illuminate\Database\Eloquent\Model;

class Farms extends Model
{
    use UserOwned;

    protected $table = 'farms';

    protected $fillable = ['user_id', 'location', 'timezone'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hogpens()
    {
        return $this->hasMany(Hogpens::class, 'farm_id');
    }

    public function dailyFarmReports()
    {
        return $this->hasMany(DailyFarmReports::class, 'farm_id');
    }

    public function alerts()
    {
        return $this->hasMany(Alerts::class, 'farm_id');
    }

    //
}
