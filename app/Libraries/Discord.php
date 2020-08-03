<?php

namespace App\Libraries;

use App\Models\Mship\Account;
use Illuminate\Http\Client\Response;
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

    public function grantRole(Account $account, string $role): bool
    {
        $role_id = $this->findRole($role);

        return $this->grantRoleById($account, $role_id);
    }

    public function grantRoleById(Account $account, int $role): bool
    {
        $response = Http::withHeaders($this->headers)
            ->retry(3, 20000)
            ->put("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}/roles/{$role}");

        return $this->result($response);
    }

    public function removeRole(Account $account, string $role): bool
    {
        $role_id = $this->findRole($role);

        return $this->removeRoleById($role_id);
    }

    public function removeRoleById(Account $account, int $role): bool
    {
        $response = Http::withHeaders($this->headers)
            ->retry(3, 20000)
            ->delete("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}/roles/{$role}");

        return $this->result($response);
    }

    public function setNickname(Account $account, string $nickname): bool
    {
        $response = Http::withHeaders($this->headers)
            ->retry(3, 20000)
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
            ->retry(3, 20000)
            ->delete("{$this->base_url}/{$this->guild_id}/members/{$account->discord_id}");

        return $this->result($response);
    }

    private function findRole(string $roleName): int
    {
        $response = Http::withHeaders($this->headers)
            ->retry(3, 20000)
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
