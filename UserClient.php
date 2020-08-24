<?php

namespace go1\clients;

use go1\core\util\client\UserDomainHelper;
use go1\util\queue\Queue;
use go1\util\user\UserHelper;
use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;
use stdClass;

class UserClient
{
    use BearerClientTrait;

    private Client           $client;
    private string           $userUrl;
    private MqClient         $mqClient;
    private UserDomainHelper $helper;

    public function __construct(Client $client, string $userUrl, MqClient $mqClient, UserDomainHelper $userDomainHelper)
    {
        $this->client = $client;
        $this->userUrl = rtrim($userUrl, '/');
        $this->mqClient = $mqClient;
        $this->helper = $userDomainHelper;
    }

    public function userUrl(): string
    {
        return $this->userUrl;
    }

    public function client(): Client
    {
        return $this->client;
    }
    
    public function helper()
    {
        return $this->helper;
    }

    public function unblockEmail($mail)
    {
        $this->mqClient->publish(['mail' => $mail], Queue::DO_USER_UNBLOCK_MAIL);
    }

    public function unblockIp($ip)
    {
        $this->mqClient->publish(['ip' => $ip], Queue::DO_USER_UNBLOCK_IP);
    }

    public function login(string $name, string $pass, string $instance = null, $jwtExpire = '+ 1 month'): stdClass
    {
        $json = $this
            ->client
            ->post("{$this->userUrl}/account/login", [
                'headers' => ['JWT-Expire-Time' => $jwtExpire],
                'json'    => array_filter([
                    'portal'   => $instance,
                    'username' => $name,
                    'password' => $pass,
                ]),
            ])
            ->getBody()
            ->getContents();

        return json_decode($json);
    }

    public function current($uuid, $instance, $jwtExpire = '+ 1 month')
    {
        $body = $this
            ->client
            ->get(
                "{$this->userUrl}/account/current/{$uuid}/$instance", [
                'headers' => ['JWT-Expire-Time' => $jwtExpire],
            ])
            ->getBody()
            ->getContents();

        return json_decode($body);
    }

    public function register($accountsName, $portalName, $mail, $pass, $first, $last, $data = null, $jwtExpire = '+ 1 month', array $options = [])
    {
        return $this->client->post("$this->userUrl/account", $options + [
                'http_errors' => false,
                'headers'     => ['JWT-Expire-Time' => $jwtExpire],
                'json'        => array_filter([
                    'instance'   => $accountsName,
                    'portal'     => $portalName,
                    'email'      => $mail,
                    'password'   => $pass ?: Uuid::uuid4()->toString(),
                    'random'     => !$pass,
                    'first_name' => $first,
                    'last_name'  => $last,
                    'data'       => $data,
                ]),
            ]);
    }

    /**
     * @param string   $portalName
     * @param string[] $roles
     * @param bool     $all
     * @param int      $limit
     * @param int      $offset
     * @param array    $options
     * @return \Generator
     */
    public function findUsers($portalName, array $roles, $all = false, $limit = 50, $offset = 0, array $options = [])
    {
        $roles = implode(',', $roles);
        do {
            $res = $this->client->get("{$this->userUrl}/account/find/{$portalName}/{$roles}?limit=$limit&offset=$offset", $options);
            $users = json_decode($res->getBody()->getContents());
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($users)) {
                return;
            }
            foreach ($users as $user) {
                yield $user;
            }

            $offset += $limit;
        } while ($all && count($users) === $limit);
    }

    public function findAdministrators($portalName, $all = false, $limit = 10, $offset = 0, array $options = [])
    {
        return $this->findUsers($portalName, ['administrator'], $all, $limit, $offset, $options);
    }

    /**
     * @param string      $uuid
     * @param string|null $portalName
     * @return string|bool
     */
    public function uuid2jwt($uuid, string $portalName = null)
    {
        //Backwards compatible signature
        if ($uuid instanceof Client) {
            [$client, $userUrl, $uuid, $portalName] = array_merge(func_get_args(), [null]);
        } else {
            $client = $this->client;
            $userUrl = $this->userUrl;
        }

        $url = rtrim($userUrl, '/') . "/account/current/{$uuid}" . (!is_null($portalName) ? "/{$portalName}" : '');
        $res = $client->get($url, ['http_errors' => false]);

        return (200 == $res->getStatusCode())
            ? json_decode($res->getBody()->getContents())->jwt
            : false;
    }

    /**
     * @param int         $profileId
     * @param string|null $portalName
     * @return bool|string
     */
    public function profileId2jwt($profileId, string $portalName = null)
    {
        //Backwards compatible signature
        if ($profileId instanceof Client) {
            [$client, $userUrl, $profileId, $portalName] = array_merge(func_get_args(), [null]);
        } else {
            $client = $this->client;
            $userUrl = $this->userUrl;
        }

        return ($uuid = (new UserHelper())->profileId2uuid($client, $userUrl, $profileId))
            ? $this->uuid2jwt($client, $userUrl, $uuid, $portalName)
            : false;
    }

    public function postLogin($portalIdOrTitle, string $jwt, array $need = []): array
    {
        $queryString = '';
        !empty($need) && $queryString = '&need[]=' . implode('&need[]=', $need);
        $portalIdOrTitle && $queryString .= '&instance=' . $portalIdOrTitle;
        $res = $this->client->get("{$this->userUrl}/post-login?jwt=" . $jwt . $queryString, []);

        if (200 != $res->getStatusCode()) {
            return [];
        }

        return json_decode($res->getBody(), true);
    }

    public function isCourseAuthor($portalIdOrTitle, string $jwt): bool
    {
        $account = $this->postLogin($portalIdOrTitle, $jwt, ['courseAuthor']);

        return $account['accounts'][0]['courseAuthor'] ?? false;
    }
}
