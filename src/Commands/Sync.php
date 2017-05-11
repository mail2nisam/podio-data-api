<?php

namespace Phases\PodioDataApi\Commands;


use Illuminate\Console\Command;
use Phases\PodioDataApi\PodioController;

class Sync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Podio apps and data';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $podioController = new PodioController();
        $this->comment("Creating/Updating User account");
//        $newUser = $podioController->createUserAccount();
//        $this->comment("Your api_token for {$newUser->email} is : {$newUser->api_token}");
        $this->comment("Syncing Podio app structure");
        $podioController->syncPodioApps();
        $this->info("Podio structure is ready");
        $this->comment("Syncing Podio app data");
        $podioController->syncAppData();
        $this->info("Syncing data completed");
        $this->info("Adding app hooks to Podio apps");
//        $podioController->syncPodioHooks();
        $this->info("Syncing completed");

    }
}
