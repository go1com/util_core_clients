<?php

namespace go1\clients;

use GuzzleHttp\Client;
use stdClass;

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
        $response = $this->getLearningObjects($portalId, [$loId], $authorization, ['id']);

        return $response ? true : false;
    }

    public function getLearningObject(int $portalId, int $loId, string $authorization = '', array $query = []): ?stdClass
    {
        $response = $this->getLearningObjects($portalId, [$loId], $authorization, $query);

        return $response->hits[0] ?? null;
    }

    public function getLearningObjects(int $portalId, array $loIds = [], string $authorization = '', array $query = [], int $limit = 20): ?stdClass
    {
        $default = [
            'admin'  => 1,
            'portal' => $portalId,
            'limit'  => $limit
        ];

        if (!empty($loIds)) {
            $default['id'] = $loIds;
        }

        $query = $query + $default;

        $response = $this->httpClient->get("$this->exploreUrl/lo", [
            'headers' => [
                'Authorization' => $authorization,
            ],
            'query'   => $query,
        ]);

        if (200 == $response->getStatusCode()) {
            return json_decode($response->getBody()->getContents()) ?? null;
        }

        return null;
    }
}
