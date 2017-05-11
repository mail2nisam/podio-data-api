<?php

namespace Phases\PodioDataApi\Models;


class AppFieldValue extends BaseModel
{
    protected $fillable = ['app_item_id','field_id','field_value'];
    protected $hidden = ['created_at','updated_at','app_item_id','fieldDetails'];
    protected $encrypt = ['field_value'];
    protected $with = ['fieldDetails'];
    protected $touches = ['appItem'];
//    protected $appends = ['field_name'];

    public function fieldDetails()
    {
        return $this->belongsTo('Phases\PodioDataApi\Models\AppField','field_id','field_id');

    }

    public function appItem()
    {
        return $this->belongsTo('Phases\PodioDataApi\Models\AppItem');
    }


    public function getFieldNameAttribute()
    {
        return $this->fieldDetails->external_id;
    }

}
