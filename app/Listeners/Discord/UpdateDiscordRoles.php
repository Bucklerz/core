<?php

namespace App\Listeners\Discord;

use App\Events\Discord\DiscordUnlinked;
use App\Events\Mship\RoleUpdated;
use App\Exceptions\Discord\InvalidDiscordRemovalException;
use App\Http\Controllers\Adm\Mship\Role;
use App\Libraries\Discord;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateDiscordRoles implements ShouldQueue
{
    /**
     * @var Discord
     */
    private $discord;

    public function __construct(Discord $discord)
    {
        $this->discord = $discord;
    }

    /**
     * Handle the event.
     *
     * @param RoleUpdated $event
     * @return void
     */
    public function handle(RoleUpdated $event)
    {
        $users = $event->role->users;

        foreach ($users as $user){
            $this->discord->updateUser($user);
        }
    }
}
