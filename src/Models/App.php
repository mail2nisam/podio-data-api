<?php

namespace Phases\PodioDataApi\Models;


use Illuminate\Database\Eloquent\Model;

class App extends Model
{
    protected $fillable = ['app_id','app_name','app_name_formatted','status'];

}
