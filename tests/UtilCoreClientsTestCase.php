<?php

namespace go1\clients\tests;

use Doctrine\Common\Cache\ArrayCache;
use go1\clients\UtilCoreClientServiceProvider;
use go1\util\Service;
use go1\util\tests\UtilCoreTestCase;
use GuzzleHttp\Client;
use Pimple\Container;
use RuntimeException;

class UtilCoreClientsTestCase extends UtilCoreTestCase
{
    public function setupContainer(Container &$container)
    {
        if (!$this->queue) {
            throw new RuntimeException('Please call ::setUp() before using this.');
        }

        $container['client'] = function (Container $c) {
            return new Client();
        };

        $container['cache'] = new ArrayCache;
        $container->register(new UtilCoreClientServiceProvider, [
            'queueOptions' => [
                'host' => '172.31.11.129',
                'port' => '5672',
                'user' => 'go1',
                'pass' => 'go1',
            ],
        ]);

        $serviceNames = ['lo', 'user', 'mail', 'portal', 'currency', 'rules', 'sms', 'graphin', 's3', 'realtime', 'explore'];
        foreach ($serviceNames as $serviceName) {
            $container[$serviceName . '_url'] = Service::url($serviceName, 'qa');
        }

        $container->extend('go1.client.mq', function () {
            return $this->queue;
        });
    }
}
