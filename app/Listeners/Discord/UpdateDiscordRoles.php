<?php

namespace App\Listeners\Discord;

use App\Events\Mship\RoleUpdated;
use App\Libraries\Discord;
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

        foreach ($users as $user) {
            $this->discord->updateUser($user);
        }
    }
}
