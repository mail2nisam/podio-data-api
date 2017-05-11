<?php

namespace Phases\PodioDataApi\Models;

use Illuminate\Database\Eloquent\Model;

class AppField extends Model
{
    protected $fillable = ['app_id','field_id','external_id','field_type','options'];
    protected $hidden = ['id','created_at','updated_at','app_id','field_id','options'];
    public function getOptionsAttribute($value)
    {
        return unserialize($value);
    }
}
