<?php


namespace go1\clients;

use go1\clients\exception\access_client\BadRequestException;
use go1\clients\exception\access_client\ServerErrorException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ServerException;

class AccessClient
{
    private Client $client;
    private string $accessServiceUrl;
    private string $serviceAuthUserName;
    private string $serviceAuthPassword;
    private bool   $cacheEnabled;

    public function __construct(
        Client $client,
        string $serviceAuthUserName,
        string $serviceAuthPassword,
        string $accessServiceUrl
    )
    {
        $this->client = $client;
        $this->accessServiceUrl = $accessServiceUrl;
        $this->serviceAuthUserName = $serviceAuthUserName;
        $this->serviceAuthPassword = $serviceAuthPassword;
        $this->cacheEnabled = extension_loaded('apcu');
    }

    public function signServerJwt(string $audience, array $scopes): string
    {
        try {
            if (!$this->cacheEnabled) {
                return $this->signNewServerJwt($audience, $scopes);
            }

            $cacheId = md5($audience . ':' . implode(' ', $scopes));
            if ($token = apcu_fetch($cacheId)) {
                return $token;
            }

            $token = $this->signNewServerJwt($audience, $scopes);
            apcu_add($cacheId, $token, 300);

            return $token;
        } catch (ServerException $e) {
            throw new ServerErrorException('Access service has internal error.');
        } catch (BadResponseException $e) {
            throw new BadRequestException('Service authentication or scopes is invalid.');
        }
    }

    public function translateToken(string $sessionToken): string
    {
        try {
            $res = $this->client->get("{$this->accessServiceUrl}/v1.0/translateToken", [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => "Bearer {$sessionToken}"
                ]
            ]);

            $contents = json_decode($res->getBody()->getContents());

            return $contents->jwt;
        } catch (ServerException $e) {
            throw new ServerErrorException('Access service has internal error.');
        } catch (BadResponseException $e) {
            throw new BadRequestException('Invalid session token.');
        }
    }

    public function generateSession(int $userId, int $portalId, string $serviceAccessToken = null)
    {
        $scope = 'ott';
        $serviceAccessToken = $serviceAccessToken ?: $this->signServerJwt('access', [$scope]);

        try {
            $res = $this->client->post("{$this->accessServiceUrl}/v1.0/session", [
                'json'    => [
                    'userId'   => $userId,
                    'portalId' => $portalId,
                    'scope'    => $scope,
                ],
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => "Bearer {$serviceAccessToken}"
                ]
            ]);

            $contents = json_decode($res->getBody()->getContents());

            return $contents->token;
        } catch (ServerException $e) {
            throw new ServerErrorException('Access service has internal error.');
        } catch (BadResponseException $e) {
            throw new BadRequestException('Invalid user or service access token.');
        }
    }

    private function signNewServerJwt(string $audience, array $scopes): string
    {
        $res = $this->client->post("{$this->accessServiceUrl}/v1.0/serverJwt/sign", [
            'json'    => [
                'service'  => $this->serviceAuthUserName,
                'password' => $this->serviceAuthPassword,
                'scopes'   => $scopes,
                'audience' => $audience,
            ],
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);

        $contents = json_decode($res->getBody()->getContents());

        return $contents->jwt;
    }

}
