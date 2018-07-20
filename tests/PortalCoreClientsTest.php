<?php

namespace go1\clients\tests;

use Doctrine\Common\Cache\ArrayCache;
use go1\clients\PortalClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

class PortalCoreClientsTest extends UtilCoreClientsTestCase
{
    public function test404()
    {
        $client = $this
            ->getMockBuilder(Client::class)
            ->setMethods(['request'])
            ->getMock();

        $client
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new BadResponseException(
                'Portal not found',
                $this->createMock(RequestInterface::class),
                new Response(404, [], '"Portal not found"')
            ));

        $cache = $this->getMockBuilder(ArrayCache::class)->setMethods(['contains'])->getMock();
        $cache
            ->expects($this->once())
            ->method('contains')
            ->willReturn(false);

        $portalClient = new PortalClient($client, 'http://portal.test.service', $cache);
        $response = $portalClient->load(123);

        $this->assertEmpty($response);
    }
}
