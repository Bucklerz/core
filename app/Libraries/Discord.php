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

    public function __construct()
    {
        $this->token = config('services.discord.token');
        $this->guild_id = config('services.discord.guild_id');
        $this->base_url = config('services.discord.base_discord_uri').'/guilds';
    }

    public function updateUser(Account $account)
    {
        $this->updateUserRoles($account);
        $this->updateUserNickname($account);
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

    public function getUserRoles(Account $account): Collection
    {
        $response = $this->sendDiscordAPIRequest("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}")->json();

        return collect($response->roles);
    }

    public function grantRole(Account $account, string $role): bool
    {
        $role_id = $this->findRole($role);

        return $this->grantRoleById($account, $role_id);
    }

    public function grantRoleById(Account $account, int $role): bool
    {
        $response = $this->sendDiscordAPIRequest("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}/roles/{$role}", 'put');

        return $this->result($response);
    }

    public function removeRole(Account $account, string $role): bool
    {
        $role_id = $this->findRole($role);

        return $this->removeRoleById($account, $role_id);
    }

    public function removeRoleById(Account $account, int $role): bool
    {
        $response = $this->sendDiscordAPIRequest("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}/roles/{$role}", 'delete');

        return $this->result($response);
    }

    public function setNickname(Account $account, string $nickname): bool
    {
        $response = $this->sendDiscordAPIRequest("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}", 'patch',
            [
                'nick' => $nickname,
            ]
        );

        return $this->result($response);
    }

    public function kick(Account $account): bool
    {
        $response = $this->sendDiscordAPIRequest("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}", 'delete');

        if ($response->status() == 404) {
            return true;
        }

        return $this->result($response);
    }

    private function findRole(string $roleName): int
    {
        $response = $this->sendDiscordAPIRequest("{$this->base_url}/{$this->guild_id}/roles")->json();

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

    private function sendDiscordAPIRequest($url, $method = 'get', $data = null)
    {
        $request = Http::withToken($this->token, 'Bot');

        if ($data) {
            $data = ['json' => $data];
        } else {
            $data = [];
        }

        $response = null;
        $allowedNumRetries = 1;

        for ($i = 0; $i < $allowedNumRetries + 1; $i++) {
            $response = $request->send(strtoupper($method), $url, $data);

            // Check for rate limit
            if ($response->status() == 429) {
                $retryAfter = $response->json()['retry_after'];
                usleep($retryAfter + 10);
                continue;
            }

            // No rate limit problem, lets get out of the loop
            break;
        }

        return $response;
    }
}
