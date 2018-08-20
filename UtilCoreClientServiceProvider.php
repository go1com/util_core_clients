<?php

namespace go1\clients;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class UtilCoreClientServiceProvider implements ServiceProviderInterface
{
    public function register(Container $c)
    {
        $c['go1.client.portal'] = function (Container $c) {
            return new PortalClient($c['client'], $c['portal_url'], $c['cache']);
        };

        $c['go1.client.user'] = function (Container $c) {
            return new UserClient($c['client'], $c['user_url'], $c['go1.client.mq']);
        };

        $c['go1.client.mail'] = function (Container $c) {
            return new MailClient($c['go1.client.mq']);
        };

        $c['go1.client.lo'] = function (Container $c) {
            return new LoClient($c['client'], $c['lo_url'], $c['go1.client.mq']);
        };

        $c['go1.client.explore'] = function (Container $c) {
            return new ExploreClient($c['client'], $c['explore_url']);
        };

        $c['go1.client.mq'] = function (Container $c) {
            $logger = null;
            $o = $c['queueOptions'];

            if ($c->offsetExists('profiler.do') && $c->offsetGet('profiler.do')) {
                $logger = $c['profiler.collectors.mq'];
            }

            $currentRequest = $c->offsetExists('request_stack') ? $c['request_stack']->getCurrentRequest() : null;

            return new MqClient($o['host'], $o['port'], $o['user'], $o['pass'], $logger, $c['access_checker'], $c, $currentRequest);
        };
    }
}
