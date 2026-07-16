<?php

declare(strict_types=1);

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

    public function __construct()
    {
        $storagePath = rtrim(storage_path('keys'), '/');
        $this->privateKeyPath = $storagePath . '/private.key';
        $this->publicKeyPath = $storagePath . '/public.key';
        $this->encryptionKey = env('encryptionKey');

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
            $returnUrl = rawurlencode($_SERVER['REQUEST_URI']);
            evo()->redirect('/manager/?return=' . $returnUrl);
            exit;
        }

        try {
            $authRequest = $this->authServer->validateAuthorizationRequest($request);
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse(new Response());
        } catch (\Exception $e) {
            throw $e;
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
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function token(ServerRequestInterface $request)
    {
        $bodyParams = (array) $request->getParsedBody();
        $codeId = $bodyParams['code'] ?? null;
        $nonce = null;

        if ($codeId) {
            $nonce = $this->authCodeRepository->getNonceByEncryptedCode($codeId);
        }

        $response = new Response();
        try {
            $result = $this->authServer->respondToAccessTokenRequest($request, $response);
            $body = json_decode((string)$result->getBody(), true);

            if (is_array($body) && !isset($body['id_token'])) {
                $accessToken = $body['access_token'] ?? null;
                if ($accessToken) {
                    $parts = explode('.', $accessToken);
                    if (count($parts) === 3) {
                        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                        $userId = $payload['sub'] ?? null;
                        if ($userId) {
                            $userData = evo()->getUserInfo($userId);
                            if ($userData !== false) {
                                $idToken = $this->generateIdToken($userId, (array)$userData, $nonce);
                                if ($idToken) {
                                    $body['id_token'] = $idToken;
                                }
                            }
                        }
                    }
                }
            }

            if (isset($body['id_token'])) {
                $newBody = json_encode($body);
                $response = new Response('php://memory', $result->getStatusCode());
                $response->getBody()->write($newBody);
                $response = $response->withHeader('Content-Type', 'application/json');
                foreach ($result->getHeaders() as $headerName => $headerValues) {
                    $headerName = (string) $headerName;
                    if (!in_array($headerName, ['Content-Type', 'Content-Length'], true)) {
                        $values = (array) $headerValues;
                        foreach ($values as $value) {
                            $response = $response->withAddedHeader($headerName, (string) $value);
                        }
                    }
                }
                return $response;
            }

            return $result;
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse($response);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function generateIdToken(string $userId, array $userData, ?string $nonce = null): ?string
    {
        try {
            if (!file_exists($this->privateKeyPath)) {
                return null;
            }

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
                ->withHeader('kid', '1')
                ->issuedBy('route.local')
                ->permittedFor('espocrm')
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
        } catch (\Exception $e) {
            return null;
        }
    }

    public function userinfo(ServerRequestInterface $request)
    {
        if (!$request->hasHeader('Authorization')) {
            $allHeaders = getallheaders();
            $authHeader = $allHeaders['Authorization'] ?? $allHeaders['authorization'] ?? null;
            if ($authHeader) {
                $request = $request->withHeader('Authorization', $authHeader);
            } else {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
                if ($authHeader) {
                    $request = $request->withHeader('Authorization', $authHeader);
                }
            }
        }

        try {
            $request = $this->resourceServer->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse(new Response());
        } catch (\Exception $e) {
            throw $e;
        }

        $userId = $request->getAttribute('oauth_user_id');
        $userData = evo()->getUserInfo($userId);
        if (!$userData) {
            $response = new Response('php://memory', 401);
            $response->getBody()->write(json_encode(['error' => 'invalid_token']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $groups = [];
        if ($userData['role'] == 1) {
            $groups[] = 'admin';
        } else {
            $groups[] = 'user';
        }

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
            $response = new Response('php://memory', 500);
            $response->getBody()->write(json_encode(['error' => 'Unable to read public key']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $res = openssl_pkey_get_public($keyContent);
        if ($res === false) {
            $response = new Response('php://memory', 500);
            $response->getBody()->write(json_encode(['error' => 'Invalid public key format']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $details = openssl_pkey_get_details($res);
        if ($details === false) {
            $response = new Response('php://memory', 500);
            $response->getBody()->write(json_encode(['error' => 'Unable to extract key details']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $mod = base64_encode($details['rsa']['n']);
        $exp = base64_encode($details['rsa']['e']);
        $jwks = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'n' => $mod,
                    'e' => $exp,
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => '1',
                ]
            ]
        ];
        $response = new Response('php://memory', 200);
        $response->getBody()->write(json_encode($jwks));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
