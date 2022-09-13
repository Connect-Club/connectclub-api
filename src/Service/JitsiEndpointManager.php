<?php

namespace App\Service;

use App\ConnectClub;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use Throwable;

class JitsiEndpointManager
{
    private ClientInterface $client;
    private EventLogManager $eventLogManager;

    public function __construct(ClientInterface $client, EventLogManager $eventLogManager)
    {
        $this->client = $client;
        $this->eventLogManager = $eventLogManager;
    }

    public function disconnectUserFromRoom(User $user, VideoRoom $videoRoom)
    {
        try {
            $url = $videoRoom->config->dataTrackApiUrl;

            if (!$url) {
                $url = $videoRoom->config->dataTrackUrl ?
                    $videoRoom->config->dataTrackUrl . '/ws' :
                    $_ENV['DATA_TRUCK_URL_API'];

                $url = str_replace('wss://', 'https://', $url);
                $url = str_replace('.club/ws', '.club:8083/ws', $url);
            }

            $body = $this->client->request('POST', $url.'/ban', [
                RequestOptions::JSON => [
                    'roomSid' => $videoRoom->community->name,
                    'userId' => (string) $user->id
                ],
                RequestOptions::CONNECT_TIMEOUT => 2,
                RequestOptions::TIMEOUT => 5,
                RequestOptions::READ_TIMEOUT => 2,
            ])->getBody();
        } catch (RequestException $exception) {
            $body = $exception->getResponse() ? $exception->getResponse()->getBody() : $exception->getMessage();
        } catch (Throwable $exception) {
            $body = $exception->getMessage();
        }

        $this->eventLogManager->logEvent($videoRoom, 'disconnect_user_from_room', [
            'user_id' => (string) $user->id,
            'response' => $body,
        ]);
    }

    public function generateTokenJWTForUser(User $user, VideoRoom $room): Token
    {
        return $this->generateJWTTokenFor($room->community->name, (string) $user->getId());
    }

    public function generateJWTTokenFor(string $conferenceGid, string $endpoint): Token
    {
        $time = time();

        return (new Builder())
            ->issuedAt($time)
            ->canOnlyBeUsedAfter($time)
            ->expiresAt($time + ConnectClub::VIDEO_ROOM_SESSION_EXPIRES_AT)
            ->withClaim('endpoint', $endpoint)
            ->withClaim('conferenceGid', $conferenceGid)
            ->getToken(new Sha256(), new Key($_ENV['JWT_TOKEN_PRIVATE_KEY']));
    }

    public function request(string $method, string $uri, array $headers = [])
    {
        $this->client->request($method, $_ENV['JITSI_SERVER'].'/'.$uri, [
            'headers' => $headers,
        ]);
    }
}
