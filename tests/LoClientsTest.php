<?php

namespace go1\clients\tests;

use go1\clients\LoClient;
use go1\util\queue\Queue;

class LoClientsTest extends UtilCoreClientsTestCase
{
    public function testShareLo()
    {
        /** @var LoClient $client */
        $container = $this->getContainer();
        $client = $container['go1.client.lo'];
        $client->share(1000, 10000);

        $message = $this->queueMessages[Queue::DO_CONSUMER_HTTP_REQUEST][0];
        $this->assertEquals("POST", $message['method']);
        $this->assertEquals($container['lo_url'] . "/lo/10000/share/1000", $message['url']);
    }

    /**
     * @runInSeparateProcess
     */
    public function testUnShareLo()
    {
        /** @var LoClient $client */
        $container = $this->getContainer();
        $client = $container['go1.client.lo'];
        $client->share(1000, 10000, true);

        $message = $this->queueMessages[Queue::DO_CONSUMER_HTTP_REQUEST][0];
        $this->assertEquals("DELETE", $message['method']);
        $this->assertEquals($container['lo_url'] . "/lo/10000/share/1000", $message['url']);
    }
}
