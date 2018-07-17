<?php

namespace go1\clients\tests;

use Doctrine\Common\Cache\ArrayCache;
use go1\clients\MqClient;
use go1\clients\UtilCoreClientServiceProvider;
use go1\util\Service;
use go1\util\tests\QueueMockTrait;
use go1\util\tests\UtilCoreTestCase;
use GuzzleHttp\Client;
use Pimple\Container;

class UtilClientTestCase extends UtilCoreTestCase
{
    use QueueMockTrait;

    /** @var MqClient */
    protected $queue;

    public function setUp()
    {
        parent::setUp();

        $c = $this->getContainer();
        $this->mockMqClient($c);
        $this->queue = $c['go1.client.mq'];
    }

    protected function getContainer(): Container
    {
        $c = parent::getContainer();
        $c['client'] = new Client;
        $c['cache'] = new ArrayCache;
        $c->register(new UtilCoreClientServiceProvider, [
            'queueOptions' => [
                    'host' => '172.31.11.129',
                    'port' => '5672',
                    'user' => 'go1',
                    'pass' => 'go1',
                ] + Service::urls(['queue', 'user', 'mail', 'portal', 'rules', 'currency', 'lo', 'sms', 'graphin', 's3', 'realtime'], 'qa'),
        ]);

        return $c;
    }
}
