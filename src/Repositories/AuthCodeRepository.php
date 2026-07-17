<?php

namespace roilafx\Espocrmevo\Repositories;

use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use roilafx\Espocrmevo\Entities\AuthCodeEntity;
use roilafx\Espocrmevo\Models\AuthCode;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    private ?string $currentNonce = null;
    private ?string $lastRevokedNonce = null;
    private ?int $lastRevokedUserId = null;
    private string $encryptionKey = '';

    public function setEncryptionKey(string $key): void
    {
        $this->encryptionKey = $key;
    }

    public function setCurrentNonce(?string $nonce): void
    {
        $this->currentNonce = $nonce;
    }

    public function getLastRevokedNonce(): ?string
    {
        return $this->lastRevokedNonce;
    }

    public function getLastRevokedUserId(): ?int
    {
        return $this->lastRevokedUserId;
    }

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $scopes = array_map(
            fn($scope) => $scope->getIdentifier(),
            $authCodeEntity->getScopes()
        );

        AuthCode::create([
            'code_id'    => $authCodeEntity->getIdentifier(),
            'user_id'    => $authCodeEntity->getUserIdentifier(),
            'client_id'  => $authCodeEntity->getClient()->getIdentifier(),
            'scopes'     => json_encode($scopes),
            'nonce'      => $this->currentNonce,
            'is_revoked' => false,
            'expires_at' => $authCodeEntity->getExpiryDateTime(),
        ]);

        $this->currentNonce = null;
    }

    public function revokeAuthCode($codeId): void
    {
        $code = AuthCode::where('code_id', $codeId)->first();
        if ($code) {
            $this->lastRevokedNonce = $code->nonce;
            $this->lastRevokedUserId = $code->user_id;
            $code->update(['is_revoked' => true]);
        } else {
            $this->lastRevokedNonce = null;
            $this->lastRevokedUserId = null;
        }
    }

    public function getUserAndNonceByEncryptedCode(string $encryptedCode): ?array
    {
        $authCodeId = $this->decryptAuthCode($encryptedCode);
        if (!$authCodeId) return null;

        $code = AuthCode::where('code_id', $authCodeId)->first();
        if (!$code) return null;

        return [
            'user_id' => $code->user_id,
            'nonce'   => $code->nonce,
        ];
    }

    public function isAuthCodeRevoked($codeId): bool
    {
        $code = AuthCode::where('code_id', $codeId)->first();
        return $code ? $code->is_revoked : true;
    }
}
