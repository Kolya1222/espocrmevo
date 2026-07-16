<?php

namespace roilafx\Espocrmevo\Entities;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class ScopeEntity implements ScopeEntityInterface
{
    use EntityTrait;

    public function jsonSerialize(): mixed
    {
        return $this->getIdentifier();
    }

    public function __toString(): string
    {
        return $this->getIdentifier();
    }
}