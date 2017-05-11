<?php

namespace Phases\PodioDataApi\Models;



class PodioApiCredential extends BaseModel
{
    protected $encrypt = ['client_secret','access_token','refresh_token'];
    protected $fillable = ['client_id','client_secret','access_token','refresh_token','expires_in','is_in_use'];
}
