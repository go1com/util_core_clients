<?php
namespace go1\clients\tests;

use go1\util\user\UserHelper;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;

class BearerClientTraitTest extends TestCase
{
    private $client;

    private $testSubject;

    protected function setUp() : void
    {
        $this->client = new Client([RequestOptions::HTTP_ERRORS => false]);
        $this->testSubject = new BearerClientStub($this->client);
    }

    public function testWithBearerToken()
    {
        $testSubjectWithRootJwt = $this->testSubject->withRootJwt();

        $initialClient = $this->testSubject->getClient();
        $finalClient = $testSubjectWithRootJwt->getClient();
        $this->assertNotSame($finalClient, $initialClient);

        $headers = $finalClient->getConfig(RequestOptions::HEADERS);
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer '.UserHelper::ROOT_JWT, $headers['Authorization']);
        $this->assertFalse($finalClient->getConfig(RequestOptions::HTTP_ERRORS));
    }
}
