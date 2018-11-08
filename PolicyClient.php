<?php

namespace go1\clients;

use go1\util\user\UserHelper;
use GuzzleHttp\Client;
use stdClass;

class PolicyClient
{
    private $httpClient;
    private $policyUrl;

    public function __construct(Client $httpClient, string $policyUrl)
    {
        $this->httpClient = $httpClient;
        $this->policyUrl = $policyUrl;
    }

    public function createPolicy($portalNameOrId, string $hostEntityType, int $hostEntityId, array $items, array $query = []):? stdClass
    {
        $response = $this->httpClient->request('PUT', "$this->policyUrl/{$portalNameOrId}/{$hostEntityType}/{$hostEntityId}/items", [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . UserHelper::ROOT_JWT,
            ],
            'query' => $query,
            'body'    => json_encode($items),
        ]);

        if (200 == $response->getStatusCode()) {
            return json_decode($response->getBody()->getContents()) ?? null;
        }

        return null;
    }
}
