<?php

namespace App\Controller\V1\VideoRoom;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\VideoRoom\VideoRoomStatisticsRequest;
use App\Repository\Community\CommunityRepository;
use App\Repository\UserRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Service\SlackClient;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/video-room/statistics")
 */
class VideoRoomStatisticController extends BaseController
{
    private SlackClient $slackClient;
    private UserRepository $userRepository;
    private CommunityRepository $communityRepository;

    public function __construct(
        SlackClient $slackClient,
        UserRepository $userRepository,
        CommunityRepository $communityRepository
    ) {
        $this->slackClient = $slackClient;
        $this->userRepository = $userRepository;
        $this->communityRepository = $communityRepository;
    }

    /**
     * @SWG\Post(
     *     description="Load data track statistics",
     *     summary="Load data track statistics",
     *     @SWG\Parameter(
     *         in="body",
     *         name="body",
     *         @SWG\Schema(ref=@Model(type=VideoRoomStatisticsRequest::class))
     *     ),
     *     tags={"Internal"},
     *     @SWG\Response(response="200", description="Success"),
     *     security=false,
     * )
     * @Route("", methods={"POST"})
     */
    public function index(Request $req): Response
    {
        /** @var VideoRoomStatisticsRequest $request */
        $request = $this->getEntityFromRequestTo($req, VideoRoomStatisticsRequest::class);

        $userIds = [
            ...array_keys($request->stat),
            ...array_map(fn(array $followedUsers) => array_keys($followedUsers), $request->stat)
        ];

        $this->userRepository->getManager()->getFilters()->disable('softdeleteable');
        $users = [];
        foreach ($this->userRepository->findUsersByIds($userIds) as $user) {
            $users[$user->id] = $user;
        }
        $this->userRepository->getManager()->getFilters()->enable('softdeleteable');

        $community = $this->communityRepository->findOneBy(['name' => $request->roomname]);
        if (!$community) {
            return $this->createErrorResponse([ErrorCode::V1_VIDEO_ROOM_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $stats = array_filter($request->stat, fn($followedUsers) => count($followedUsers) > 0);
        if (!$stats) {
            return $this->handleResponse([]);
        }

        $statisticsMessage = $community->description.':'.PHP_EOL;
        foreach ($stats as $userId => $followedUsers) {
            $messageFollowedPart = [];
            foreach ($followedUsers as $followedUserId => $followedTime) {
                $followedTime = sprintf(
                    '%02d:%02d',
                    ($followedTime / 60 % 60),
                    $followedTime % 60
                );

                $name = $users[$followedUserId]->getFullNameOrId();
                $messageFollowedPart[] = $name.' (id '.$followedUserId.') - '.$followedTime.' min';
            }

            $statisticsMessage .= $users[$userId]->getFullNameOrId().': ';
            $statisticsMessage .= implode(', ', $messageFollowedPart) . PHP_EOL;
        }

        $this->slackClient->sendMessage($_ENV['SLACK_CHANNEL_VIDEO_ROOM_STATISTICS_NAME'], $statisticsMessage);

        return $this->handleResponse([]);
    }
}
