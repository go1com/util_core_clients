<?php


namespace go1\clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Ramsey\Uuid\Uuid;

class AtlantisClient
{
    private Client $client;
    private string $serviceUrl;

    public function __construct(Client $client, string $serviceUrl)
    {
        $this->client = $client;
        $this->serviceUrl = $serviceUrl;
    }

    public function isEnabled(string $featureName, string $jwt = null, array $options = []): bool
    {
        $feature = $this->getFeatureData($featureName, $jwt, $options);

        return !is_null($feature);
    }

    public function getFeatureData(string $featureName, string $jwt = null, array $options = []): ?array
    {
        $res = $this->client->get("{$this->serviceUrl}/features", $options + [
            'query' => array_filter([
                'jwt'    => $jwt,
                'anonID' => $jwt ? null : Uuid::uuid4()
            ]),
        ]);

        $features = json_decode($res->getBody()->getContents(), true);

        return $features[$featureName] ?? null;
    }
}
