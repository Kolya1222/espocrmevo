<?php

namespace roilafx\Espocrmevo\Repositories;

use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use roilafx\Espocrmevo\Entities\AuthCodeEntity;
use roilafx\Espocrmevo\Models\AuthCode;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    private ?string $currentNonce = null;
    private string $encryptionKey = '';

    public function setCurrentNonce(?string $nonce): void
    {
        $this->currentNonce = $nonce;
    }

    public function setEncryptionKey(string $key): void
    {
        $this->encryptionKey = $key;
    }

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        AuthCode::create([
            'code_id'    => $authCodeEntity->getIdentifier(),
            'user_id'    => $authCodeEntity->getUserIdentifier(),
            'client_id'  => $authCodeEntity->getClient()->getIdentifier(),
            'scopes'     => json_encode($authCodeEntity->getScopes()),
            'nonce'      => $this->currentNonce,
            'is_revoked' => false,
            'expires_at' => $authCodeEntity->getExpiryDateTime(),
        ]);

        $this->currentNonce = null;
    }

    public function revokeAuthCode($codeId): void
    {
        AuthCode::where('code_id', $codeId)->update(['is_revoked' => true]);
    }

    public function isAuthCodeRevoked($codeId): bool
    {
        $code = AuthCode::where('code_id', $codeId)->first();
        return $code ? $code->is_revoked : true;
    }

    public function getNonceByEncryptedCode(string $encryptedCode): ?string
    {
        $authCodeId = $this->decryptAuthCode($encryptedCode);
        if (!$authCodeId) {
            return null;
        }

        $code = AuthCode::where('code_id', $authCodeId)->first();
        return $code?->nonce;
    }

    private function decryptAuthCode(string $encryptedCode): ?string
    {
        if (empty($this->encryptionKey)) {
            return null;
        }

        try {
            if (is_string($this->encryptionKey)) {
                $decrypted = Crypto::decryptWithPassword($encryptedCode, $this->encryptionKey);
            } else {
                return null;
            }
        } catch (WrongKeyOrModifiedCiphertextException $e) {
            return null;
        } catch (EnvironmentIsBrokenException $e) {
            return null;
        } catch (Exception $e) {
            return null;
        }

        $payload = json_decode($decrypted);
        if (!$payload || !isset($payload->auth_code_id)) {
            return null;
        }

        return $payload->auth_code_id;
    }
}