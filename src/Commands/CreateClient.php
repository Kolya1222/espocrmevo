<?php

namespace roilafx\Espocrmevo\Commands;

use Illuminate\Console\Command;
use roilafx\Espocrmevo\Models\OidcClient;

class CreateClient extends Command
{
    protected $signature = 'espocrmevo:create-client
                            {--id= : Client ID}
                            {--secret= : Client secret (optional)}
                            {--redirect= : Redirect URI}
                            {--name= : Client name}';

    protected $description = 'Create a new OIDC client';

    public function handle()
    {
        $clientId = $this->option('id') ?? $this->ask('Client ID');
        $secret   = $this->option('secret') ?? $this->secret('Client secret (leave empty for none)');
        $redirect = $this->option('redirect') ?? $this->ask('Redirect URI');
        $name     = $this->option('name') ?? $this->ask('Client name', $clientId);

        OidcClient::create([
            'client_id'     => $clientId,
            'client_secret' => $secret ?: null,
            'redirect_uri'  => $redirect,
            'grant_types'   => 'authorization_code refresh_token',
            'scope'         => 'openid profile email phone',
            'name'          => $name,
            'is_active'     => true,
        ]);

        $this->info("Client '$clientId' created successfully.");
    }
}