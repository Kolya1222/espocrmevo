<?php

namespace roilafx\Espocrmevo\Repositories;

use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void {}

    public function revokeRefreshToken($tokenId): void {}

    public function isRefreshTokenRevoked($tokenId): bool
    {
        return false;
    }

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return null;
    }
}
