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
        $response = $this->getLearningObject($portalId, $loId, $authorization, ['id']);

        return $response ? true : false;
    }

    public function getLearningObject(int $portalId, int $loId, string $authorization = '', array $fields = null): ?stdClass
    {
        $query = [
            'admin'  => 1,
            'portal' => $portalId,
            'id'     => [$loId],
        ];

        $fields && $query + $fields;
        $response = $this->httpClient->get("$this->exploreUrl/lo", [
            'headers' => [
                'Authorization' => $authorization,
            ],
            'query'   => $query,
        ]);

        if (200 == $response->getStatusCode()) {
            return json_decode($response->getBody()->getContents())->hits[0] ?? null;
        }

        return null;
    }
}
