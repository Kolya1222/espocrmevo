<?php

namespace roilafx\Espocrmevo\Repositories;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use roilafx\Espocrmevo\Entities\ClientEntity;
use roilafx\Espocrmevo\Models\OidcClient;

class ClientRepository implements ClientRepositoryInterface
{
    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $client = OidcClient::where('client_id', $clientIdentifier)
                            ->where('is_active', 1)
                            ->first();

        if (!$client) {
            return null;
        }

        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier($client->client_id);
        $clientEntity->setName($client->name ?? $client->client_id);
        $clientEntity->setRedirectUri($client->redirect_uri);
        $clientEntity->setConfidential(true);

        return $clientEntity;
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $client = OidcClient::where('client_id', $clientIdentifier)
                            ->where('is_active', 1)
                            ->first();

        if (!$client) {
            return false;
        }
        if ($client->client_secret !== null) {
            return hash_equals($client->client_secret, $clientSecret ?? '');
        }
        return true;
    }
}