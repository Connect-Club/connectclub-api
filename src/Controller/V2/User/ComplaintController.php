<?php

namespace App\Controller\V2\User;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V2\User\CreateComplaintRequest;
use App\Entity\User;
use App\Message\HandleComplaintMessage;
use App\Repository\User\ComplaintRepository;
use App\Repository\UserRepository;
use App\Repository\VideoChat\VideoMeetingParticipantRepository;
use App\Service\SlackClient;
use App\Service\Transaction\TransactionManager;
use App\Swagger\ViewResponse;
use App\Transaction\FlushEntityManagerTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/complaint")
 */
class ComplaintController extends BaseController
{
    private ComplaintRepository $complaintRepository;
    private UserRepository $userRepository;

    public function __construct(ComplaintRepository $complaintRepository, UserRepository $userRepository)
    {
        $this->complaintRepository = $complaintRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/{abuserId}", methods={"POST"}, requirements={"abuserId": "\d+"})
     * @SWG\Post(
     *     description="Create complaint",
     *     summary="Create complaint",
     *     tags={"User"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=CreateComplaintRequest::class))),
     *     @SWG\Response(response=Response::HTTP_CREATED, description="Success response")
     * )
     * @ViewResponse(
     *     errorCodesMap={
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_COMPLAINT_ABUSER_NOT_FOUND, "Abuser not found"},
     *         {Response::HTTP_CONFLICT, ErrorCode::V1_COMPLAINT_ALREADY_EXISTS, "Complaint already exists"},
     *         {Response::HTTP_UNPROCESSABLE_ENTITY, "reason:cannot_be_empty", "Validation error empty reason"},
     *     }
     * )
     */
    public function create(
        Request $request,
        int $abuserId,
        EntityManagerInterface $entityManager,
        SlackClient $slackClient,
        TransactionManager $transactionManager,
        MessageBusInterface $bus,
        VideoMeetingParticipantRepository $videoMeetingParticipantRepository
    ): JsonResponse {
        /** @var CreateComplaintRequest $createComplaintRequest */
        $createComplaintRequest = $this->getEntityFromRequestTo($request, CreateComplaintRequest::class);
        $user = $this->getUser();

        $this->unprocessableUnlessValid($createComplaintRequest);

        /** @var User|null $abuser */
        $abuser = $this->userRepository->find($abuserId);
        if (!$abuser) {
            return $this->createErrorResponse(ErrorCode::V1_COMPLAINT_ABUSER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $complaint = new User\Complaint($user, null, $abuser, $createComplaintRequest->reason);
        $complaint->description = $createComplaintRequest->description;

        $participant = $videoMeetingParticipantRepository->findOneBy(['participant' => $abuser], ['id' => 'DESC']);
        $conferenceId = null;
        if ($participant) {
            $conferenceId = $participant->videoMeeting->videoRoom->community->name;
        }

        $message = sprintf(
            'Received user complaint'.PHP_EOL.
            'Author: %s'.PHP_EOL.
            'Abuser: %s'.PHP_EOL.
            'Reason: %s'.PHP_EOL.
            'Description: %s',
            $complaint->author->getFullNameOrId() . ' (id '.$complaint->author->id.')',
            $complaint->abuser->getFullNameOrId() . ' (id '.$complaint->abuser->id.')',
            $complaint->reason,
            $complaint->description
        );

        $threadId = $slackClient->sendMessage($_ENV['SLACK_CHANNEL_COMPLAINT_NAME'], $message, null, false)['ts'];

        if ($conferenceId) {
            $message = new HandleComplaintMessage((string) $abuserId, $conferenceId, $message, $threadId);
            $transactionManager->addTransaction(fn() => $bus->dispatch($message));
        }

        $transactionManager->addTransaction(new FlushEntityManagerTransaction($entityManager, $complaint));
        $transactionManager->run();

        return $this->handleResponse([], Response::HTTP_CREATED);
    }
}
