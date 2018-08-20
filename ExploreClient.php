<?php

namespace go1\clients;

use GuzzleHttp\Client;
use RuntimeException;

class ExploreClient
{
    private $httpClient;
    private $exploreUrl;

    public function __construct(Client $httpClient, string $exploreUrl)
    {
        $this->httpClient = $httpClient;
        $this->exploreUrl = $exploreUrl;
    }

    public function canAccess(int $portalId, int $loId, string $authorization = ''): bool
    {
        $response = $this->httpClient->get("$this->exploreUrl/lo", [
            'headers'     => [
                'Authorization' => $authorization,
            ],
            'query'       => [
                'admin'  => 1,
                'field'  => ['id'],
                'portal' => $portalId,
                'id'     => [$loId],
            ],
        ]);

        if (200 == $response->getStatusCode()) {
            $response = json_decode($response->getBody()->getContents());
            return ($response->total) ? true : false;
        }

        return false;
    }
}
