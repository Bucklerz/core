<?php

namespace App\Events\Mship;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Spatie\Permission\Models\Role;

class RoleUpdated extends Event
{
    use SerializesModels;

    public $role = null;

    public function __construct(Role $role)
    {
        $this->role = $role;
    }
}
