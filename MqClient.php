<?php

namespace go1\clients;

use DDTrace\Configuration;
use DDTrace\Format;
use DDTrace\GlobalTracer;
use Exception;
use go1\util\AccessChecker;
use go1\util\publishing\event\EventInterface;
use go1\util\queue\Queue;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Pimple\Container;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use function class_exists;
use function is_scalar;
use function json_encode;

class MqClient
{
    /** @var AMQPChannel[] */
    private        $channels;
    private        $host;
    private        $port;
    private        $user;
    private        $pass;
    private        $logger;
    private        $accessChecker;
    private        $container;
    private        $request;
    private        $propertyAccessor;
    private string $batchExchange;

    const CONTEXT_ACTOR_ID    = 'actor_id';
    const CONTEXT_ACTION      = 'action';
    const CONTEXT_DESCRIPTION = 'description';
    /*** @deprecated */
    const CONTEXT_PORTAL     = 'instance';
    const CONTEXT_INTERNAL   = 'internal';
    const CONTEXT_REQUEST_ID = 'request_id';
    const CONTEXT_SESSION_ID = 'sessionId';
    const CONTEXT_TIMESTAMP  = 'timestamp';

    # For message splitting
    const CONTEXT_ENTITY_TYPE = 'entity-type';
    const CONTEXT_PORTAL_NAME = 'portal-name';

    public function __construct(
        $host, $port, $user, $pass,
        LoggerInterface $logger = null,
        AccessChecker $accessChecker = null,
        Container $container = null,
        Request $request = null
    )
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->logger = $logger ?: new NullLogger;
        $this->accessChecker = $accessChecker;
        $this->container = $container;
        $this->request = $request;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function channel($exchange = 'events', $type = 'topic'): AMQPChannel
    {
        if (!isset($this->channels[$exchange][$type])) {
            $connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->pass);
            $channel = $connection->channel();
            $channel->exchange_declare($exchange, $type, false, false, false);
            $this->channels[$exchange][$type] = $channel;
        }

        return $this->channels[$exchange][$type];
    }

    public function close()
    {
        $this->channel()->close();
    }

    public function batchAdd($body, string $routingKey, array $context = [], int $priority = 10)
    {
        $this->queue($body, $routingKey, $context, 'events', true, $priority);
    }

    public function batchDone()
    {
        if (isset($this->batchExchange)) {
            $this->channel()->publish_batch();
            $this->channel()->basic_publish(new AMQPMessage('quit'), $this->batchExchange);
            unset($this->batchExchange);
        }
    }

    public function publish($body, string $routingKey, array $context = [], int $priority = 10)
    {
        $this->queue($body, $routingKey, $context, 'events', $priority);
    }

    private function currentRequest()
    {
        if ($this->request) {
            return $this->request;
        }

        return ($this->container && $this->container->offsetExists('request_stack'))
            ? $this->container['request_stack']->getCurrentRequest()
            : null;
    }

    public function queue(
        $body,
        string $routingKey,
        array $context = [],
        $exchange = '',
        bool $batch = false,
        int $priority = 10
    ) {
        $body = is_scalar($body) ? json_decode($body) : $body;
        $this->processMessage($body, $routingKey);

        if ($request = $this->currentRequest()) {
            self::parseRequestContext($request, $context, $this->accessChecker);
        }

        if ($service = getenv('SERVICE_80_NAME')) {
            $context['app'] = $service;
        }
        $context[static::CONTEXT_TIMESTAMP] = $context[static::CONTEXT_TIMESTAMP] ?? time();

        if (!$exchange) {
            # TODO: Consumer will need parse this. For saving time, we can move routingKey to msg.headers[X-ROUTING-KEY]
            $body = json_encode(['routingKey' => $routingKey, 'body' => $body]);
            $routingKey = Queue::WORKER_QUEUE_NAME;
        }

        $body = $body = is_scalar($body) ? $body : json_encode($body);
        $this->doQueue($exchange, $routingKey, $body, $context, $batch, $priority);
        $this->logger->debug($body, ['exchange' => $exchange, 'routingKey' => $routingKey, 'context' => $context]);
    }

    protected function doQueue(
        string $exchange,
        string $routingKey,
        string $body,
        array $headers,
        bool $batch = false,
        int $priority = 10
    ) {
        // add root span ID.
        if (class_exists(Configuration::class)) {
            if (Configuration::get()->isDistributedTracingEnabled()) {
                $tracer = GlobalTracer::get();
                if ($span = $tracer->getActiveSpan()) {
                    $ctx = $span->getContext();
                    $tracer->inject($ctx, Format::TEXT_MAP, $headers);
                }
            }
        }

        $msg = new AMQPMessage($body, array_filter([
            'content_type'        => 'application/json',
            'application_headers' => new AMQPTable($headers),
            'priority'            => $priority
        ]));

        $batch
            ? $this->channel()->batch_basic_publish($msg, $exchange, $routingKey)
            : $this->channel()->basic_publish($msg, $exchange, $routingKey);

        if ($batch) {
            if (!isset($this->batchExchange)) {
                $this->batchExchange = $exchange;
            } else if ($this->batchExchange != $exchange) {
                throw new \BadMethodCallException('[batch] unmatch exchange');
            }
        }
    }

    private function processMessage($body, string $routingKey)
    {
        # Quiz does not have `id` property.
        if (Queue::QUIZ_USER_ANSWER_UPDATE == $routingKey) {
            return null;
        }

        $explode = explode('.', $routingKey);
        $isLazy = isset($explode[0]) && ('do' == $explode[0]); # Lazy = do.SERVICE.#

        if (strpos($routingKey, '.update') && !$isLazy) {
            if ('post_' === substr($routingKey, 0, 5)) {
                return null;
            }

            if (
                (
                    is_array($body)
                    && !(2 === count(array_filter($body, function ($value, $key) {
                            return (in_array($key, ['id', 'original']) && $value);
                        }, ARRAY_FILTER_USE_BOTH)))
                )
                ||
                (
                    is_object($body)
                    && (!(property_exists($body, 'id') && $this->propertyAccessor->getValue($body, 'id'))
                        || !(property_exists($body, 'original') && $this->propertyAccessor->getValue($body, 'original')))
                )
            ) {
                throw new Exception("Missing entity ID or original data.");
            }
        }
    }

    public static function parseRequestContext(Request $request, array &$context = [], AccessChecker $accessChecker = null)
    {
        if (!isset($context[self::CONTEXT_REQUEST_ID])) {
            if ($requestId = $request->headers->get('X-Request-Id')) {
                $context[self::CONTEXT_REQUEST_ID] = $requestId;
            }
        }

        $accessChecker = $accessChecker ?: new AccessChecker;
        if (!isset($context[self::CONTEXT_ACTOR_ID]) && $accessChecker) {
            $user = $accessChecker->validUser($request);
            $user && $context[self::CONTEXT_ACTOR_ID] = $user->id;
        }

        if (!isset($context[self::CONTEXT_SESSION_ID])) {
            if ($sessionId = self::getRequestSessionId($request)) {
                $context[self::CONTEXT_SESSION_ID] = $sessionId;
            }
        }
    }

    public function publishEvent(EventInterface $event, string $exchange = 'events')
    {
        $context = $event->getContext();
        if ($request = $this->currentRequest()) {
            if (!isset($context[self::CONTEXT_REQUEST_ID])) {
                if ($requestId = $request->headers->get('X-Request-Id')) {
                    $event->addContext(self::CONTEXT_REQUEST_ID, $requestId);
                }
            }

            $accessChecker = new AccessChecker;
            if (!isset($context[self::CONTEXT_ACTOR_ID]) && $accessChecker) {
                $user = $accessChecker->validUser($request);
                $user && $event->addContext(self::CONTEXT_ACTOR_ID, $user->id);
            }

            if (!isset($context[self::CONTEXT_SESSION_ID])) {
                if ($sessionId = self::getRequestSessionId($request)) {
                    $event->addContext(self::CONTEXT_SESSION_ID, $sessionId);
                }
            }
        }

        if (!isset($context['app']) && ($service = getenv('SERVICE_80_NAME'))) {
            $event->addContext('app', $service);
        }

        if (!isset($context[static::CONTEXT_TIMESTAMP])) {
            $event->addContext(static::CONTEXT_TIMESTAMP, time());
        }

        $properties = [
            'content_type'        => 'application/json',
            'application_headers' => new AMQPTable($event->getContext()),
        ];
        $this->channel()->basic_publish(
            new AMQPMessage(json_encode($event->getPayload()), $properties),
            $exchange,
            $event->getSubject()
        );

        $this->logger->debug('published an event', [
            'exchange'   => $exchange,
            'routingKey' => $event->getSubject(),
            'payload'    => $event->getPayload(),
            'context'    => $event->getContext(),
        ]);
    }

    private static function getRequestSessionId(Request $request): ?string
    {
        if ('POST' === $request->getMethod() && ('/consume' === substr($request->getRequestUri(), 0, 8))) {
            $msgContext = $request->get('context');
            if (!empty($msgContext)) {
                $msgContext = is_scalar($msgContext) ? json_decode($msgContext) : json_decode(json_encode($msgContext, JSON_FORCE_OBJECT));
                if (isset($msgContext->{self::CONTEXT_SESSION_ID})) {
                    return $msgContext->{self::CONTEXT_SESSION_ID};
                }
            }
        }

        return Uuid::uuid4()->toString();
    }
}
