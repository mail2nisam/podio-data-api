<?php

namespace Phases\PodioDataApi;

use App\App;
use App\AppField;
use App\AppFieldValue;
use App\AppItem;
use App\Http\Controllers\Controller;
use App\PodioApiCredential;
use App\User;
use Illuminate\Http\Request;
use Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Intervention\Image\Facades\Image;

class PodioController extends Controller
{
    /**
     * Authenticate Podio using email and password
     */
    public function authenticatePodio()
    {

        $activeCredentials = $this->getFirstActiveApiCredentials();
        if($activeCredentials){
            \Podio::setup($activeCredentials->client_id, $activeCredentials->client_secret);
            try {
              return  \Podio::authenticate('refresh_token', ['refresh_token' => $activeCredentials->refresh_token]);
            } catch (\Exception $exception) {
                Log::error($exception->getMessage());
            }
        }
    }

    /**
     * Sync Podio apps to Db
     */
    public function syncPodioApps()
    {
        $this->authenticatePodio();
        $appIds = Config::get('podio.apps');
        foreach ($appIds as $appId) {
            $this->getAppStructure($appId);
        }
    }

    public function syncAppData()
    {
        $this->authenticatePodio();
        $appIds = Config::get('podio.apps');
        foreach ($appIds as $appId) {
            $this->getPodioAppData($appId);
        }
    }

    /**
     * Get appStructure using appId from Podio
     * @param $appId
     */
    private function getAppStructure($appId)
    {
        try {
            $appStructure = \PodioApp::get($appId);
            $this->createApp($appStructure);
            try {
                $this->createFields($appStructure->fields, $appId);
            } catch (\Exception $exception) {
                Log::error($exception->getMessage());
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

    }

    /**
     * Create App from app Details
     * @param $appStructure
     */
    private function createApp($appStructure)
    {
        if ($appStructure) {
            try {
                $app = App::updateOrCreate(
                    [
                        'app_id' => $appStructure->app_id
                    ],
                    [
                        'app_id' => $appStructure->app_id,
                        'app_name' => $appStructure->config['name'],
                        'app_name_formatted' => $appStructure->url_label
                    ]
                );
                Log::info($app);
            } catch (\Exception $exception) {
                Log::error($exception->getMessage());
            }

        }
    }

    /**
     * Create app fields
     * @param $appFields
     * @param $appId
     */
    private function createFields($appFields, $appId)
    {
        if ($appFields) {
            foreach ($appFields as $appField) {
                try {
                    $field = AppField::updateOrCreate(
                        [
                            'field_id' => $appField->field_id
                        ],
                        [
                            'app_id' => $appId,
                            'field_id' => $appField->field_id,
                            'external_id' => $appField->external_id,
                            'field_type' => $appField->type,
                            'options' => serialize($appField->config)
                        ]
                    );
                    Log::info([$field->field_id, $field->external_id]);
                } catch (\Exception $exception) {
                    Log::error($exception->getMessage());
                }


            }
        }
    }

    /**
     * Fetch Podio data related to an App
     * @param $appId
     */
    private function getPodioAppData($appId)
    {
        $offset = 0;
        do {
            try {
                $items = \PodioItem::filter($appId, ['limit' => 100, 'offset' => $offset]);
                if ($items->total) {
                    $this->storeAppItems($items, $appId);
                }
                $offset += 100;
            } catch (\Exception $exception) {
                Log::error($exception->getMessage());
            }
        } while ($items->total > $offset);
    }

    /**
     * Save App Items to DB
     * @param $items
     * @param $appId
     */
    private function storeAppItems($items, $appId)
    {
        foreach ($items as $item) {
            $this->createOrUpdateAppItem($item, $appId);
        }
    }

    /**
     * Create or Update Podio Item data to DB
     * @param $item
     * @param $appId
     */
    private function createOrUpdateAppItem($item, $appId)
    {
        try {
            $appItem = AppItem::updateOrCreate(
                [
                    'podio_item_id' => $item->item_id
                ],
                [
                    'app_id' => $appId,
                    'podio_item_id' => $item->item_id,
                    'podio_app_item_id' => $item->app_item_id
                ]
            );
            Log::info($appItem->podio_item_id);
            $fields = $item->fields;
            foreach ($fields as $field) {
                $this->storeFieldValues($field, $appItem->id);
            }

        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }

    /**
     * Store field Values to app_field_value table
     * @param $field
     * @param $app_item_id
     */
    private function storeFieldValues($field, $app_item_id)
    {
        switch ($field->type) {
            case "text":
            case "number":
                $value = $field->values;
                break;
            case "category":
                $value = $field->values[0]['text'];
                break;
            case "app" :
                $value = $field->values[0]->item_id;
                break;
            case "date":

                break;
            case "image":
                $images = $field->values;
                $imageValue = [];
                foreach ($images as $image) {
                    $fileId = $image->file_id;
                    $file = \PodioFile::get($fileId);
                    $appName = AppItem::find($app_item_id)->app->app_name_formatted;
                    $imageName = '/images/' . $appName . '/' . $app_item_id . '/' . $image->name;
                    Storage::put($imageName, $file->get_raw(), 'public');
                    $imageValue[] = $appName . "/{$app_item_id}/{$image->name}";
                }
                $value = serialize($imageValue);
                break;
        }
        try {
            $appFieldValue = AppFieldValue::updateOrCreate(
                [
                    'app_item_id' => $app_item_id,
                    'field_id' => $field->id
                ],
                [
                    'app_item_id' => $app_item_id,
                    'field_id' => $field->id,
                    'field_value' => $value
                ]
            );
            Log::info($appFieldValue);
        } catch (\Exception $exception) {
            Log::error("Update or create app fields".$exception->getMessage());
        }

    }

    /**
     * Fetch all Items in as json response fro DB
     * @param $appName
     * @param Request $request
     * @param $paginate
     * @return array|\Illuminate\Database\Eloquent\Collection|\Illuminate\Http\JsonResponse|static[]
     */
    public function getItems(Request $request,$appName,$paginate=false)
    {
        $app = App::where('app_name_formatted', $appName)->first();
        if ($app) {
            $appId = $app->app_id;
        } else {
            return response()->json(['status' => 403, 'message' => 'App name not found']);
        }
        $dataQuery = AppItem::with('fieldValues')->where('app_id', $appId);
        $dataQuery = $this->filterAppItems($request,$dataQuery);
        $data = $dataQuery->get();


        $finalData = $this->applyDataFilter($request,$data);

        return $finalData;
    }

    /**
     * Create various sizes of images using Intervention\Image library
     * @param $image
     * @param $appName
     * @param $app_item_id
     * @return array|bool
     */
    private function makeImageSlices($image, $appName, $app_item_id)
    {
        $imageSizes = Config::get('image.image_sizes');
        if (!count($imageSizes)) {
            return false;
        }
        $imageNames = [];
        foreach ($imageSizes as $imageSize) {

            $x = $imageSize[0];

            $imageAbsolutePath = storage_path('app/images/' . $appName . '/' . $app_item_id . '/' . $image->name);
            $img = Image::make($imageAbsolutePath);
            $pathInfo = pathinfo($image->name);
            $extension = $pathInfo['extension'];
            $imageBaseName = $pathInfo['filename'];
            $resizeImageName = $imageBaseName . "_{$x}_x." . $extension;
            $img->resize($x, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            $img->save(storage_path('app/images/' . $appName . '/' . $app_item_id . '/' . $resizeImageName));
            $imageNames[] = $appName . '/' . $app_item_id . '/' . $resizeImageName;
        }
        return $imageNames;
    }

    /**
     * Sanitize Input to integer
     * @param $input
     * @return Integer
     */
    private function toInt($input)
    {
        return filter_var($input,FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Show Image from Storage Directory
     * @param $appName
     * @param $id
     * @param $filename
     * @param $request
     * @return mixed
     */
    public function showImage($appName, $id, $filename,Request $request)
    {
        $image = Image::make(storage_path('app/images/' . $appName . '/' . $id . '/' . $filename));
        $mode = $request->input('mode');
        if(!$mode){
            $image = $image;
        }
        $width =  $request->input('width') ?  $this->toInt($request->input('width')) : null;
        $height =  $request->input('height')?  $this->toInt($request->input('height')) : null;

        $x =  $request->input('x')?  $this->toInt($request->input('x')) : null;
        $y =  $request->input('y')?  $this->toInt($request->input('y')) : null;

        switch ($mode){
            case 'resize':
                if(!$width &&  !$height ){
                    return response()->json(['response'=>'Width or height needs to be defined','status'=>403]);
                }
                if (in_array(null, [$width,$height]) && ($width || $height))
                {
                    $image = $image->resize($width,$height, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }else{
                    $image = $image->resize($width,$height, function ($constraint) {
                        $constraint->upsize();
                    });
                }
                break;
            case 'crop':
                if ($width && $height && $x && $y){

                    $image = $image->crop($width,$height,$x,$y);
                }else{
                    $params = ['width'=>$width,'height'=>$height,'x'=>$x,'y'=>$y];
                    $inValidParams = (in_array(null, $params));
                    if($inValidParams){
                        return response()->json(['message'=>'Please check all parameters for image cropping {width,height,x,y}']);
                    }

                }
                break;
        }
        return $image->response();
    }

    /**
     * Auto Create all Podio hooks to an App
     */
    public function syncPodioHooks()
    {
        $this->authenticatePodio();
        $apps = Config::get('podio.apps');
        foreach ($apps as $appId) {
            $baseUrl = URL::to('/');
            $hookUrl = "{$baseUrl}/podio/hooks/{$appId}";

            $hooks = ['item.create','item.update','app.update','app.delete','item.delete'];
            try{

                $podioHooks = \PodioHook::get_for('app',$appId);
            }catch (\Exception $exception)
            {
                Log::error("Get all hooks".$exception->getMessage());
            }


            foreach ($hooks as $hook)
            {
                $hookFoundOnPodio = false;
                if($podioHooks)
                {
                    foreach ($podioHooks as $podioHook)
                    {
                        if($podioHook->status=='active'){

                            $arrayExist =  [
                                "type" => $podioHook->type,
                                "url" => $podioHook->url
                            ];
                            $newHook = ['type'=>$hook,'url'=>$hookUrl];
                            if($arrayExist == $newHook){
                                $hookFoundOnPodio = true;
                                break;
                            }
                        }


                    }
                }
                try {
                    if(!$hookFoundOnPodio){

                        $hookId = \PodioHook::create('app', $appId, array('type' => $hook, 'url' => $hookUrl));
                        Log::info("HOOK ID" . $hookId);
                        Log::debug('Hooks created');
                        try {
                            \PodioHook::verify($hookId);
                        } catch (\Exception $exception) {
                            Log::error("verify Hook request".$exception->getMessage());
                        }

                    }else{
                        Log::debug('Hooks already exist');
                    }

                } catch (\Exception $exception) {
                    Log::error("Create Hook ".$exception->getMessage());
                }
            }


        }

    }

    /**
     * Manage Podio App Hooks
     * @param $appId
     * @param Request $request
     */
    public function mangeAppHook($appId, Request $request)
    {

        $hookType = $request->input('type');
        Log::info($hookType);
        switch ($hookType) {
            case 'hook.verify':
                if ($request->input("code") && $request->input("hook_id")) {
                    try {
                        $this->authenticatePodio();
                        \PodioHook::validate($request->input("hook_id"), array('code' => $request->input("code")));
                    } catch (\Exception $exception) {
                        Log::error($exception->getMessage());
                    }
                }
                break;
            case 'item.create':
            case 'item.update':
                Log::info("Update hook fired");
                $this->updatePodioItem($appId, $request->input('item_id'));
                break;
            case 'item.delete':
                $this->deletePodioItem($request->input('item_id'));
                break;
            case 'app.update':
                $this->updateAppStructure($appId);
                break;
            case 'app.delete':
                $this->deleteAppAndRelatedData($appId);
                break;

        }
    }

    /**
     * Reflect Podio Item Changes to DB when Hook Fired
     * @param $appId
     * @param $itemId
     */
    private function updatePodioItem($appId, $itemId)
    {
        $this->authenticatePodio();
        try {
            $item = \PodioItem::get($itemId);
            $this->createOrUpdateAppItem($item, $appId);
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }

    /**
     * Delete Podio Item from DB when Hook Fired
     * @param $itemId
     */
    private function deletePodioItem($itemId)
    {
        $appItem = AppItem::where('podio_item_id', $itemId)->first();
        if ($appItem) {
            \DB::table('app_field_values')->where('app_item_id', $appItem->id)->delete();
            $appName = App::where('app_id', $appItem->app_id)->first()->app_name_formatted;
            $imageDir = '/images/' . $appName . '/' . $appItem->id;
            Storage::deleteDirectory($imageDir);
            $appItem->delete();
            Log::info("Item deleted {$itemId} ");
        }
    }

    /**
     * Update app Structure when Podio app Modified
     * @param $appId
     */
    private function updateAppStructure($appId)
    {
        $this->authenticatePodio();
        $this->getAppStructure($appId);
    }

    /**
     * Delete an entire app and related data from DB when App deleted
     * @param $appId
     */
    private function deleteAppAndRelatedData($appId)
    {
        $appItems = AppItem::where('app_id', $appId);
        if ($appItems) {
            foreach ($appItems as $appItem) {
                $this->deletePodioItem($appItem->podio_item_id);
            }
        }
        $app = App::where('app_id', $appId);
        if ($app) {
            $app->delete();
        }
    }

    /**
     * Fetch and update refresh token and access token for each api credentials.
     */
    public function syncPodioAuthCredentials()
    {
        $authCredentials = Config::get('podio.auth_credentials');
        foreach ($authCredentials as $clientId => $clientSecret)
        {
            \Podio::setup($clientId,$clientSecret);
            $auth = \Podio::authenticate_with_password(Config('podio.auth.email'), Config('podio.auth.password'));
            if($auth)
            {
                $oauthDetails = \Podio::$oauth;
                if($oauthDetails){
                    PodioApiCredential::updateOrCreate(
                        ['client_id'=>$clientId],
                        [
                            'client_id'=>$clientId,
                            'client_secret'=>$clientSecret,
                            'access_token'=>$oauthDetails->access_token,
                            'refresh_token'=>$oauthDetails->refresh_token,
                            'expires_in'=>$oauthDetails->expires_in,
                            'is_in_use'=>0
                        ]
                    );
                }
            }
        }
        $firstItem = PodioApiCredential::first();
        $firstItem->is_in_use = 1;
        $firstItem->save();
    }

    /**
     * Get first Active Podio API Credentials
     * @return mixed
     */
    private function getFirstActiveApiCredentials()
    {
        $firstApiCredentials = PodioApiCredential::where('is_in_use',1);
        if($firstApiCredentials->exists())
        {
            return $firstApiCredentials->first();
        }else{
            $firstApiCredentials = PodioApiCredential::first();
            if($firstApiCredentials){
                $firstApiCredentials->is_in_use = 1;
                $firstApiCredentials->save();
                return $firstApiCredentials;
            }else{
                return false;
            }

        }
    }

    public function showApiToken($email)
    {
        $userDetails = User::where('email',$email)->first();
        if($userDetails){
            return response()->json(['status'=>200,'api_token'=>$userDetails->api_token]);
        }else{
            return response()->json(['status'=>403,'response'=>'User with this email not found']);

        }
    }

    public function createUserAccount()
    {
        $userEmail = Config::get('podio.auth.email');
        $user = User::where('email',$userEmail)->first();
        if(!$user){
            $user =  User::create([
                'name'=>$userEmail,
                'email'=>$userEmail,
                'password'=>Hash::make($userEmail),
                'api_token'=>str_random(60)
            ]);
        }
        return $user;
    }

    private function filterAppItems(Request $request,$modelQuery)
    {
        $filterOptions = ['id','created_at','updated_at','podio_item_id'];
        $requestedFilters = $request->all();
        $applicableFilters = array_intersect_key($requestedFilters,array_flip($filterOptions));
        foreach ($applicableFilters as $applicableFilterKey=>$applicableFilterValue)
        {
            switch ($applicableFilterKey){
                case 'id':
                    $modelQuery = $modelQuery->whereId($applicableFilterValue);
                    break;
                case 'podio_item_id':
                    $modelQuery = $modelQuery->wherePodioItemId($applicableFilterValue);
                    break;
                case 'created_at':
                case 'updated_at':
                    $dateCondition = $this->applyDefaultDateFilter($applicableFilterKey,$applicableFilterValue);
                    $modelQuery = ($dateCondition) ? $modelQuery->whereDate($dateCondition->column,$dateCondition->operator,$dateCondition->value):$modelQuery;
                    break;
            }
        }
        return $modelQuery;

    }

    private function applyDefaultDateFilter($name,$value)
    {
        $dateCondition = null;
        $dateParam = ($value) ? explode('_',$value):[];
        if(count($dateParam)>1){
            $searchTerm = $dateParam[0];
            $dateString = $dateParam[1];
            switch ($searchTerm){
                case 'after':
                    $dateCondition = (object)['column'=>$name,'operator'=>'>=','value'=>$dateString];
                    break;
                case 'before':
                    $dateCondition = (object)['column'=>$name,'operator'=>'<','value'=>$dateString];

                    break;
                case 'on':
                    $dateCondition = (object)['column'=>$name,'operator'=>'=','value'=>$dateString];
                    break;
            }
        }elseif(count($dateParam)==1){
            $dateCondition = (object)['column'=>$name,'operator'=>'=','value'=>$dateParam[0]];
        }
        return $dateCondition;
    }

    private function applyDataFilter(Request $request,$data)
    {
        $baseFilterOptions = ['id','created_at','updated_at','api_token'];
        $inputParams = array_diff_key($request->all(),array_flip($baseFilterOptions));

        $finalData = [];
        $searchCount = 0;
        if (count($inputParams) >= 1) {
            foreach ($data as $datum) {
                $dataAttribute = $datum->data;
                foreach ($inputParams as $queryString => $value) {
                    if (array_key_exists($queryString, $dataAttribute) && $value) {
                        $searchCount++;
                        if (!is_array($dataAttribute[$queryString]) && strcasecmp($dataAttribute[$queryString], $value) == 0) {
                            $finalData[] = $datum;
                        }
                    }
                    $nestedQueryString = explode("/",$queryString);

                    if(count($nestedQueryString)>1)
                    {
                        if(isset($nestedQueryString[0]) && $value){
                            $searchCount++;
                            if (is_array($dataAttribute[$nestedQueryString[0]]) &&
                                strcasecmp($dataAttribute[$nestedQueryString[0]][$nestedQueryString[1]], $value) == 0) {
                                $finalData[] = $datum;
                            }
                        }
                    }
                }

            }
        } else {
            $finalData = $data;
        }
        if ($searchCount === 0) {
            $finalData = $data;
        }
        return $finalData;
    }

}
