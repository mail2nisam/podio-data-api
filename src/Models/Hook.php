<?php

namespace Phases\PodioDataApi\Models;

use Illuminate\Database\Eloquent\Model;

class Hook extends Model
{
    protected $fillable = ['hook_id','hook_url','app_id'];
}
