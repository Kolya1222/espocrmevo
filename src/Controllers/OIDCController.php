<?php

namespace roilafx\Espocrmevo\Controllers;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use roilafx\Espocrmevo\Grants\NonceAuthCodeGrant;
use roilafx\Espocrmevo\RequestTypes\NonceAuthorizationRequest;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;
use roilafx\Espocrmevo\Entities\UserEntity;
use Laminas\Diactoros\Response;
use roilafx\Espocrmevo\Repositories\ClientRepository;
use roilafx\Espocrmevo\Repositories\ScopeRepository;
use roilafx\Espocrmevo\Repositories\AccessTokenRepository;
use roilafx\Espocrmevo\Repositories\AuthCodeRepository;
use roilafx\Espocrmevo\Repositories\RefreshTokenRepository;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\UnixTimestampDates;

class OIDCController
{
    protected AuthorizationServer $authServer;
    protected ResourceServer $resourceServer;
    protected AuthCodeRepository $authCodeRepository;
    protected string $privateKeyPath;
    protected string $publicKeyPath;
    protected string $encryptionKey;

    protected string $issuer;
    protected string $audience;
    protected string $keyId;

    public function __construct()
    {
        $this->issuer   = env('OIDC_ISSUER', 'route.local');
        $this->audience = env('OIDC_AUDIENCE', 'espocrm');
        $this->keyId    = env('OIDC_KEY_ID', '1');

        $storagePath = rtrim(storage_path('keys'), '/');
        $this->privateKeyPath = $storagePath . '/private.key';
        $this->publicKeyPath = $storagePath . '/public.key';
        $this->encryptionKey = env('encryptionKey');

        if (!file_exists($this->privateKeyPath) || !is_readable($this->privateKeyPath)) {
            throw new \RuntimeException(
                'Приватный ключ не найден или недоступен для чтения: ' . $this->privateKeyPath
            );
        }
        if (!file_exists($this->publicKeyPath) || !is_readable($this->publicKeyPath)) {
            throw new \RuntimeException(
                'Публичный ключ не найден или недоступен для чтения: ' . $this->publicKeyPath
            );
        }

        $clientRepository = new ClientRepository();
        $scopeRepository = new ScopeRepository();
        $accessTokenRepository = new AccessTokenRepository();
        $authCodeRepository = new AuthCodeRepository();
        $authCodeRepository->setEncryptionKey($this->encryptionKey);
        $this->authCodeRepository = $authCodeRepository;
        $refreshTokenRepository = new RefreshTokenRepository();

        $this->authServer = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            new CryptKey('file://' . $this->privateKeyPath, null, false),
            $this->encryptionKey
        );

        $grant = new NonceAuthCodeGrant($authCodeRepository, $refreshTokenRepository, new \DateInterval('PT10M'));
        $this->authServer->enableGrantType($grant, new \DateInterval('PT1H'));

        $this->resourceServer = new ResourceServer(
            $accessTokenRepository,
            new CryptKey('file://' . $this->publicKeyPath)
        );
    }

    public function authorize(ServerRequestInterface $request)
    {
        $userId = evo()->getLoginUserID('mgr');
        if (empty($userId)) {
            $_SESSION['oidc_authorize_return'] = $_SERVER['REQUEST_URI'];
            evo()->redirect('/manager/');
            exit;
        }

        try {
            $authRequest = $this->authServer->validateAuthorizationRequest($request);
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse(new Response());
        }

        $queryParams = $request->getQueryParams();
        $nonce = $queryParams['nonce'] ?? null;
        if ($authRequest instanceof NonceAuthorizationRequest) {
            $authRequest->setNonce($nonce);
        }

        $userData = evo()->getUserInfo($userId);
        if (!$userData) {
            $response = new Response('php://memory', 400);
            $response->getBody()->write(json_encode(['error' => 'user_not_found']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $authRequest->setUser(new UserEntity($userId));
        $authRequest->setAuthorizationApproved(true);

        $response = new Response();
        try {
            return $this->authServer->completeAuthorizationRequest($authRequest, $response);
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse($response);
        }
    }

    public function token(ServerRequestInterface $request)
    {
        $response = new Response();
        try {
            $result = $this->authServer->respondToAccessTokenRequest($request, $response);
            $body = json_decode((string)$result->getBody(), true);

            $nonce = $this->authCodeRepository->getLastRevokedNonce();
            $userId = $this->authCodeRepository->getLastRevokedUserId();

            if (!isset($body['id_token']) && $userId) {
                $userData = evo()->getUserInfo($userId);
                if ($userData !== false) {
                    $idToken = $this->generateIdToken((string)$userId, (array)$userData, $nonce);
                    if ($idToken) {
                        $body['id_token'] = $idToken;
                    }
                }
            }

            if (isset($body['id_token'])) {
                $newBody = json_encode($body);
                $newResponse = new Response('php://memory', $result->getStatusCode());
                $newResponse->getBody()->write($newBody);
                $newResponse = $newResponse->withHeader('Content-Type', 'application/json');
                foreach ($result->getHeaders() as $headerName => $headerValues) {
                    $headerName = (string) $headerName;
                    if (strtolower($headerName) === 'content-length' || strtolower($headerName) === 'content-type') {
                        continue;
                    }
                    foreach ((array)$headerValues as $value) {
                        $newResponse = $newResponse->withAddedHeader($headerName, (string)$value);
                    }
                }
                $newResponse = $newResponse->withHeader('Content-Length', (string)strlen($newBody));
                return $newResponse;
            }

            return $result;
        } catch (\Throwable $e) {
            \Log::error('Ошибка в токен-эндпоинте: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            $response = new Response('php://memory', 500);
            $response->getBody()->write(json_encode([
                'error' => 'internal_server_error'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    protected function generateIdToken(string $userId, array $userData, ?string $nonce = null): ?string
    {
        $keyContent = file_get_contents($this->privateKeyPath);
        if ($keyContent === false || trim($keyContent) === '') {
            return null;
        }

        $privateKey = InMemory::plainText($keyContent);
        $now = new \DateTimeImmutable();
        $exp = $now->modify('+1 hour');

        $encoder = new JoseEncoder();
        $formatter = new ChainedFormatter(new UnixTimestampDates());
        $builder = \Lcobucci\JWT\Token\Builder::new($encoder, $formatter);

        $token = $builder
            ->withHeader('kid', $this->keyId)
            ->issuedBy($this->issuer)
            ->permittedFor($this->audience)
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($exp)
            ->relatedTo((string)$userId)
            ->withClaim('email', $userData['email'] ?? '')
            ->withClaim('name', $userData['fullname'] ?? $userData['username'] ?? '')
            ->withClaim('username', $userData['username'] ?? '')
            ->withClaim('nonce', $nonce ?? '')
            ->getToken(new Sha256(), $privateKey);

        return $token->toString();
    }

    public function userinfo(ServerRequestInterface $request)
    {
        $authHeader = null;
        if ($request->hasHeader('Authorization')) {
            $authHeader = $request->getHeaderLine('Authorization');
        } else {
            $allHeaders = getallheaders();
            $authHeader = $allHeaders['Authorization'] ?? $allHeaders['authorization'] ?? null;
            if (!$authHeader) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
            }
            if ($authHeader) {
                $request = $request->withHeader('Authorization', $authHeader);
            }
        }

        try {
            $request = $this->resourceServer->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse(new Response());
        }

        $userId = $request->getAttribute('oauth_user_id');
        $userData = evo()->getUserInfo($userId);
        if (!$userData) {
            $response = new Response('php://memory', 401);
            $response->getBody()->write(json_encode(['error' => 'invalid_token']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $groups = ($userData['role'] == 1) ? ['admin'] : ['user'];

        $data = [
            'sub' => (string)$userData['id'],
            'email' => $userData['email'] ?? '',
            'name' => $userData['fullname'] ?? $userData['username'] ?? '',
            'username' => $userData['username'] ?? '',
            'groups' => $groups,
        ];

        $response = new Response('php://memory', 200);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function jwks()
    {
        $keyContent = file_get_contents($this->publicKeyPath);
        if ($keyContent === false) {
            return $this->jsonErrorResponse('Не удалось прочитать публичный ключ', 500);
        }

        $res = openssl_pkey_get_public($keyContent);
        if ($res === false) {
            return $this->jsonErrorResponse('Неверный формат публичного ключа', 500);
        }

        $details = openssl_pkey_get_details($res);
        if ($details === false || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            return $this->jsonErrorResponse('Не удалось извлечь параметры ключа', 500);
        }

        $mod = self::base64urlEncode($details['rsa']['n']);
        $exp = self::base64urlEncode($details['rsa']['e']);

        $jwks = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'n' => $mod,
                    'e' => $exp,
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => $this->keyId,
                ]
            ]
        ];

        $response = new Response('php://memory', 200);
        $response->getBody()->write(json_encode($jwks));
        return $response->withHeader('Content-Type', 'application/json');
    }

    protected static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function jsonErrorResponse(string $message, int $status = 500): Response
    {
        $response = new Response('php://memory', $status);
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}