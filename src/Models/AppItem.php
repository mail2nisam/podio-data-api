<?php

namespace Phases\PodioDataApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AppItem extends Model
{
    protected $fillable = ['app_id', 'podio_item_id', 'podio_app_item_id'];
    protected $hidden = ['app_id', 'app', 'fieldValues', 'podio_app_item_id'];
    protected $appends = ['data'];

    public function fieldValues()
    {
        return $this->hasMany('Phases\PodioDataApi\Models\AppFieldValue');
    }

    public function app()
    {
        return $this->belongsTo('Phases\PodioDataApi\Models\App', 'app_id', 'app_id');
    }

    public function getDataAttribute()
    {
        $values = $this->fieldValues;
        $output = [];
        foreach ($values as $value) {
            switch ($value->fieldDetails->field_type){
                case 'app':
                    $itemID = $value->field_value;
                    $query = \DB::table('app_items')
                        ->join('app_field_values', 'app_items.id', '=', 'app_field_values.app_item_id')
                        ->join('app_fields', 'app_fields.field_id', '=', 'app_field_values.field_id')
                        ->where('podio_item_id', $itemID);
                    $appFieldValues = $query->pluck('field_value', 'external_id');
                    $relatedFieldValues = [];
                    if($appFieldValues){
                        $relatedFieldValues['podio_item_id'] = $query->first()->podio_item_id;
                        foreach ($appFieldValues as $key => $fieldValue) {
                            $relatedFieldValues[$key] = Crypt::decrypt($fieldValue);
                        }
                    }

                    $output[$value->field_name] = $relatedFieldValues;
                    break;
                case 'image':
                    $multipleImageValues = unserialize($value->field_value);
                    $imageUrls = [];
                    foreach ($multipleImageValues as $imageNumber => $singleImageValues) {
                        $imageUrls[$imageNumber][] = url('storage/' . $singleImageValues);
                    }
                    $output[$value->field_name] = $imageUrls;
                    break;
                case 'date':
                case 'email':
                case 'phone':
                case 'money':
                case 'embed':
                case 'location':
                case 'contact':
                    $output[$value->field_name] = unserialize($value->field_value);
                    break;
                default:
                    $output[$value->field_name] = $value->field_value;
            }
        }
        return $output;
    }

}
