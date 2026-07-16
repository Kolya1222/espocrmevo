<?php

namespace roilafx\Espocrmevo\RequestTypes;

use League\OAuth2\Server\RequestTypes\AuthorizationRequest;

class NonceAuthorizationRequest extends AuthorizationRequest
{
    private ?string $nonce = null;

    public function setNonce(?string $nonce): void
    {
        $this->nonce = $nonce;
    }

    public function getNonce(): ?string
    {
        return $this->nonce;
    }
}