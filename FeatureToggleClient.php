<?php

namespace go1\clients;

use go1\util\user\UserHelper;
use GuzzleHttp\Client;

class FeatureToggleClient
{
    private $client;
    private $featureToggleUrl;

    public function __construct(Client $client, string $featureToggleUrl)
    {
        $this->client = $client;
        $this->featureToggleUrl = $featureToggleUrl;
    }

    public function load(string $portal, string $key): ?object
    {
        $jwt = UserHelper::ROOT_JWT;
        $res = $this->client->get("$this->featureToggleUrl/feature/{$key}?context[portal][]={$portal}&jwt=$jwt", ['http_errors' => false]);
        if (200 == $res->getStatusCode()) {
            return json_decode($res->getBody()->getContents());
        }

        return null;
    }

    public function getStatus(string $portal, string $key): bool
    {
        if (!$content = $this->load($portal, $key)) {
            return false;
        }

        return $content->$key ?? false;
    }
}
