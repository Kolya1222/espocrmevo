<?php

namespace roilafx\Espocrmevo\Entities;

use League\OAuth2\Server\Entities\UserEntityInterface;

class UserEntity implements UserEntityInterface
{
    /** @var string|int */
    private $identifier;

    public function __construct($identifier)
    {
        $this->identifier = $identifier;
    }

    public function getIdentifier(): string
    {
        return (string) $this->identifier;
    }
}