<?php

namespace go1\clients;

use Aws\Credentials\CredentialProvider;
use Aws\S3\S3Client;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Psr\Log\NullLogger;

class UtilCoreClientServiceProvider implements ServiceProviderInterface
{
    public function register(Container $c)
    {
        $c['go1.client.portal'] = function (Container $c) {
            return new PortalClient($c['client'], $c['portal_url'], $c['cache']);
        };

        $c['go1.client.policy'] = function (Container $c) {
            return new PolicyClient($c['client'], $c['policy_url']);
        };

        $c['go1.client.user'] = function (Container $c) {
            return new UserClient($c['client'], $c['user_url'], $c['go1.client.mq'], $c['go1.client.user-domain-helper']);
        };

        $c['go1.client.mail'] = function (Container $c) {
            return new MailClient($c['go1.client.mq']);
        };

        $c['go1.client.lo'] = function (Container $c) {
            return new LoClient($c['client'], $c['lo_url'], $c['go1.client.mq']);
        };

        $c['go1.client.event'] = function (Container $c) {
            return new EventClient($c['client'], $c['event_url']);
        };

        $c['go1.client.explore'] = function (Container $c) {
            return new ExploreClient($c['client'], $c['explore_url']);
        };

        $c['go1.client.feature-toggle'] = function (Container $c) {
            return new FeatureToggleClient($c['client'], $c['featuretoggle_url']);
        };

        $c['go1.client.atlantis'] = function (Container $c) {
            return new AtlantisClient($c['client'], $c['atlantis_url']);
        };

        $c['go1.client.s3Video'] = function (Container $c) {
            $o = $c['videoS3Options'];
            $args = [
                'region'      => $o['region'],
                'version'     => $o['version'],
                'credentials' => CredentialProvider::defaultProvider(),
            ];
            if (getenv('MONOLITH')) {
                // https://github.com/minio/cookbook/blob/master/docs/aws-sdk-for-php-with-minio.md
                $args['endpoint'] = $o['endpoint'];
                $args['use_path_style_endpoint'] = true;
            }

            return new S3Client($args);
        };

        $c['go1.client.mq'] = function (Container $c) {
            $logger = $c->offsetExists('logger') ? $c['logger'] : new NullLogger();
            $o = $c['queueOptions'];

            $currentRequest = $c->offsetExists('request_stack') ? $c['request_stack']->getCurrentRequest() : null;
            $defaultPriority = $o['defaultPriority'] ?? MqClient::PRIORITY_NORMAL;

            return new MqClient($o['host'], $o['port'], $o['user'], $o['pass'], $logger, $c['access_checker'], $c, $currentRequest, $defaultPriority);
        };
    }
}
