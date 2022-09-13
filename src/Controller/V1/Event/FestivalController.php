<?php

namespace App\Controller\V1\Event;

use App\Controller\BaseController;
use App\DTO\V1\Event\EventFestivalResponse;
use App\DTO\V1\Event\EventFestivalSceneResponse;
use App\DTO\V1\Event\EventScheduleParticipantResponse;
use App\DTO\V1\PaginatedResponse;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\EventScheduleFestivalScene;
use App\Entity\User;
use App\Repository\Event\EventScheduleParticipantRepository;
use App\Repository\Event\EventScheduleRepository;
use App\Repository\EventScheduleFestivalSceneRepository;
use App\Service\Transaction\TransactionManager;
use App\Swagger\ListResponse;
use DateTime;
use DateTimeZone;
use Exception;
use MaxMind\Db\Reader;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Date;

/**
 * @Route("/festival")
 */
class FestivalController extends BaseController
{
    private EventScheduleRepository $eventScheduleRepository;

    public function __construct(EventScheduleRepository $eventScheduleRepository)
    {
        $this->eventScheduleRepository = $eventScheduleRepository;
    }

    /**
     * @SWG\Get(
     *     description="List festival events",
     *     summary="List festival events",
     *     tags={"Festival"},
     *     @SWG\Parameter(
     *         in="query",
     *         name="festivalCode",
     *         type="string",
     *         schema=@SWG\Schema(type="festivalCode")
     *     ),
     *     @SWG\Parameter(
     *         in="query",
     *         name="festivalSceneId",
     *         type="string",
     *         schema=@SWG\Schema(type="string")
     *     ),
     *     @SWG\Parameter(
     *         in="query",
     *         name="speakerId",
     *         type="string",
     *         schema=@SWG\Schema(type="integer")
     *     ),
     *     @SWG\Parameter(
     *         in="query",
     *         name="clubId",
     *         type="string",
     *         schema=@SWG\Schema(type="integer")
     *     ),
     *     @SWG\Parameter(
     *         in="query",
     *         name="dateStart",
     *         type="string",
     *         schema=@SWG\Schema(type="date")
     *     ),
     *     @SWG\Parameter(
     *         in="query",
     *         name="dateEnd",
     *         type="string",
     *         schema=@SWG\Schema(type="date")
     *     ),
     *     @SWG\Parameter(
     *         in="query",
     *         name="timezone",
     *         type="string",
     *         schema=@SWG\Schema(type="date")
     *     ),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(entityClass=EventFestivalResponse::class, pagination=true, paginationByLastValue=true)
     * @Route("/event", methods={"GET"})
     */
    public function items(Request $request, Reader $reader): JsonResponse
    {
        $limit = $request->query->getInt('limit', 20);
        $lastValue = $request->query->getInt('lastValue');

        $festivalCode = $request->query->get('festivalCode');
        $festivalSceneCode = $request->query->get('festivalSceneId');
        $speakerId = $request->query->getInt('speakerId');
        $dateStart = $request->query->get('dateStart');
        $dateEnd = $request->query->get('dateEnd');
        $timeZone = $request->query->get('timezone');
        $clubId = $request->query->get('clubId');

        if (!$timeZone || !in_array($timeZone, DateTimeZone::listIdentifiers())) {
            try {
                $ip = $request->getClientIp() ?? '';
                $locationData = $reader->get($ip);
                $timeZone = $locationData['location']['time_zone'] ?? null;
            } catch (Exception $exception) {
            }
        }

        if (!$timeZone) {
            $timeZone = 'Europe/Moscow';
        }


        $dateTimeUserTimeZone = new DateTime('now', new DateTimeZone($timeZone));
        $offset = $dateTimeUserTimeZone->getOffset();

        if ($dateStart) {
            $dateStart = strtotime($dateStart . ' 00:00:00 UTC') - $offset;
        }

        if ($dateEnd) {
            $dateEnd = strtotime($dateEnd . ' 00:00:00 UTC') - $offset;
        }

        list($eventSchedules, $lastValue) = $this->eventScheduleRepository->findEventSchedulesForFestival(
            $lastValue,
            $limit,
            $speakerId,
            $festivalCode,
            $festivalSceneCode,
            $dateStart,
            $dateEnd,
            $clubId
        );

        return $this->handleResponse(
            new PaginatedResponse(
                array_map(
                    fn(EventSchedule $es) => new EventFestivalResponse($es),
                    $eventSchedules
                ),
                $lastValue
            )
        );
    }

    /**
     * @SWG\Get(
     *     description="List festival scenes",
     *     summary="List festival scenes",
     *     tags={"Festival"},
     *     @SWG\Parameter(in="query", name="festivalCode", description="Festival code filter", type="string"),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(entityClass=EventFestivalResponse::class, pagination=true, paginationByLastValue=true)
     * @Route("/scene", methods={"GET"})
     */
    public function scenes(
        Request $request,
        EventScheduleFestivalSceneRepository $eventScheduleFestivalSceneRepository
    ): JsonResponse {
        $festivalCode = $request->query->get('festivalCode');

        $criteria = [];
        if ($festivalCode !== null) {
            $criteria = ['festivalCode' => $festivalCode];
        }

        return $this->handleResponse(
            array_map(
                fn(EventScheduleFestivalScene $s) => new EventFestivalSceneResponse($s),
                $eventScheduleFestivalSceneRepository->findBy($criteria)
            )
        );
    }

    /**
     * @SWG\Get(
     *     description="List festival speakers",
     *     summary="List festival speakers",
     *     tags={"Festival"},
     *     @SWG\Parameter(in="query", name="festivalCode", description="Festival code filter", type="string"),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(entityClass=EventScheduleParticipantResponse::class)
     * @Route("/speakers", methods={"GET"})
     */
    public function speakers(
        EventScheduleParticipantRepository $eventScheduleParticipantRepository,
        Request $request
    ): JsonResponse {
        $users = [];
        $festivalCode = $request->query->get('festivalCode');

        /** @var EventScheduleParticipant $festivalSpeaker */
        foreach ($eventScheduleParticipantRepository->findFestivalSpeakers($festivalCode) as $festivalSpeaker) {
            $users[$festivalSpeaker->user->id] = $festivalSpeaker->user;
        }

        return $this->handleResponse(
            array_map(
                fn(User $s) => new EventScheduleParticipantResponse($s, false),
                array_values($users)
            )
        );
    }
}
