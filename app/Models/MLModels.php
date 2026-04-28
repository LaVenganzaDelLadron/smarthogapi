<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MLModels extends Model
{

    protected $table = "ml_models";

    protected $fillable = ['model_name', 'version', 'accuracy_score'];

    public function hogHealthPredictions()
    {
        return $this->hasMany(HogHealthPredictions::class, 'model_id');
    }

    //
}
