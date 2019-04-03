<?php

namespace go1\clients\tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class FeatureToggleClientTest extends UtilCoreClientsTestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testGetStatusWithGroupV2()
    {
        $c = $this->getContainer();
        $c->extend('client', function () use ($c) {
            $client =
                $this->getMockBuilder(Client::class)
                    ->setMethods(['get'])
                    ->disableOriginalConstructor()
                    ->getMock();

            $client
                ->expects($this->any())
                ->method('get')
                ->willReturnCallback(function (string $url, array $options) use ($c) {
                    $this->assertContains("{$c['featuretoggle_url']}/feature/groups_management_v2", $url);

                    return new Response(200, [], json_encode([
                        'groups_management_v2' => true
                    ]));
                });

            return $client;
        });

        $client = $c['go1.client.feature-toggle'];
        $this->assertEquals(true, $client->getStatus('qa.go1.com', 'groups_management_v2'));
    }
}
