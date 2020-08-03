<?php

namespace App\Console\Commands\ExternalServices;

use App\Console\Commands\Command;
use App\Libraries\Discord;
use App\Models\Mship\Account;
use Illuminate\Support\Facades\Log;

class ManageDiscord extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:manager
                            {--f|force= : If specified, only this CID will be updated.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure Discord users are in sync with VATSIM UK\'s data';

    /** @var Discord */
    protected $discord;

    /**
     * Create a new command instance.
     *
     * @param Discord $discord
     */
    public function __construct(Discord $discord)
    {
        parent::__construct();

        $this->discord = $discord;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $discordUsers = $this->getUsers();

        if (! $discordUsers) {
            $this->error('No users found.');
            exit();
        }

        foreach ($discordUsers as $account) {
            $this->discord->updateUser($account);
        }

        $this->info($discordUsers->count().' user(s) updated on Discord.');
        Log::debug($discordUsers->count().' user(s) updated on Discord.');
    }

    protected function getUsers()
    {
        if ($this->option('force')) {
            return Account::where('id', $this->option('force'))->where('discord_id', '!=', null)->get();
        }

        return Account::where('discord_id', '!=', null)->get();
    }
}
