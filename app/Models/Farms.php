<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Farms extends Model
{
    protected $table = 'farms';

    protected $fillable = ['user_id', 'location', 'timezone'];

    public function user()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
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
