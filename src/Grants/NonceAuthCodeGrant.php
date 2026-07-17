<?php

declare(strict_types=1);

namespace roilafx\Espocrmevo\Grants;

use roilafx\Espocrmevo\Repositories\AuthCodeRepository;
use roilafx\Espocrmevo\RequestTypes\NonceAuthorizationRequest;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;

class NonceAuthCodeGrant extends AuthCodeGrant
{
    protected function createAuthorizationRequest(): AuthorizationRequestInterface
    {
        return new NonceAuthorizationRequest();
    }

    public function completeAuthorizationRequest(AuthorizationRequestInterface $authorizationRequest): ResponseTypeInterface
    {
        if ($authorizationRequest instanceof NonceAuthorizationRequest && $this->authCodeRepository instanceof AuthCodeRepository) {
            $this->authCodeRepository->setCurrentNonce($authorizationRequest->getNonce());
        }

        return parent::completeAuthorizationRequest($authorizationRequest);
    }
}