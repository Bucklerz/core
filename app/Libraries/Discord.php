<?php

namespace App\Libraries;

use App\Models\Discord\DiscordRole;
use App\Models\Mship\Account;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class Discord
{
    /** @var string */
    private $token;

    /** @var int */
    private $guild_id;

    /** @var string */
    private $base_url;

    /** @var array */
    private $headers;

    public function __construct()
    {
        $this->token = config('services.discord.token');
        $this->guild_id = config('services.discord.guild_id');
        $this->base_url = config('services.discord.base_discord_uri').'/guilds';
        $this->headers = ['Authorization' => "Bot {$this->token}"];
    }

    public function updateUser(Account $account)
    {
        $this->updateUserRoles($account);
        $this->updateUserNickname($account);
        sleep(1);
    }

    public function updateUserRoles(Account $account)
    {
        $currentRoles = $this->getUserRoles($account);

        // Grant roles the user has permissions for
        DiscordRole::all()->filter(function (DiscordRole $role) use ($account) {
            return $account->hasPermissionTo($role->permission_id);
        })->each(function (DiscordRole $role) use ($account, $currentRoles) {
            if (! $currentRoles->contains($role->discord_id)) {
                $this->grantRoleById($account, $role->discord_id);
            }
        });

        // Revoke roles the user no longer has access to
        DiscordRole::all()->filter(function (DiscordRole $role) use ($account) {
            return ! $account->hasPermissionTo($role->permission_id);
        })->each(function (DiscordRole $role) use ($account, $currentRoles) {
            if ($currentRoles->contains($role->discord_id)) {
                $this->removeRoleById($account, $role->discord_id);
            }
        });
    }

    public function updateUserNickname(Account $account)
    {
        $this->setNickname($account, $account->name);
    }

    public function grantRole(Account $account, string $role): bool
    {
        $role_id = $this->findRole($role);

        return $this->grantRoleById($account, $role_id);
    }

    public function grantRoleById(Account $account, int $role): bool
    {
        $response = Http::withHeaders($this->headers)
            ->put("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}/roles/{$role}");

        return $this->result($response);
    }

    public function removeRole(Account $account, string $role): bool
    {
        $role_id = $this->findRole($role);

        return $this->removeRoleById($account, $role_id);
    }

    public function removeRoleById(Account $account, int $role): bool
    {
        $response = Http::withHeaders($this->headers)
            ->delete("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}/roles/{$role}");

        return $this->result($response);
    }

    public function setNickname(Account $account, string $nickname): bool
    {
        $response = Http::withHeaders($this->headers)
            ->patch("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}",
            [
                'nick' => $nickname,
            ]
        );

        return $this->result($response);
    }

    public function kick(Account $account): bool
    {
        $response = Http::withHeaders($this->headers)
            ->delete("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}");

        if ($response->status() == 404) {
            return true;
        }

        return $this->result($response);
    }

    public function getUserRoles(Account $account): Collection
    {
        $response = Http::withHeaders($this->headers)
            ->get("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}");

        if (! $response->successful()) {
            return collect([]);
        }

        return collect($response->json()['roles']);
    }

    private function findRole(string $roleName): int
    {
        $response = Http::withHeaders($this->headers)
            ->get("{$this->base_url}/{$this->guild_id}/roles")->json();

        $role_id = collect($response)
            ->where('name', $roleName)
            ->pluck('id')
            ->first();

        return (int) $role_id;
    }

    protected function result(Response $response)
    {
        if ($response->status() > 300) {
            return false;
        }

        return true;
    }
}
