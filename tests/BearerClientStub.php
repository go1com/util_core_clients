<?php
namespace go1\clients\tests;

use go1\clients\BearerClientTrait;
use GuzzleHttp\Client;

class BearerClientStub
{
    use BearerClientTrait;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getClient()
    {
        return $this->client;
    }
}
