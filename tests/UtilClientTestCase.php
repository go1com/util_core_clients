<?php

namespace go1\clients\tests;

use go1\clients\MqClient;
use go1\clients\UtilCoreClientServiceProvider;
use go1\util\tests\QueueMockTrait;
use go1\util\tests\UtilCoreTestCase;
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
        $c->register(new UtilCoreClientServiceProvider);

        return $c;
    }
}
