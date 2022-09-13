<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Message\UpdateTelegramEventMessage;
use App\Swagger\ViewResponse;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/telegram")
 */
class TelegramController extends BaseController
{
    /**
     * @SWG\Post(
     *     description="Telegram bot webhook",
     *     summary="Telegram bot webhook",
     *     @SWG\Response(response="200", description="OK"),
     * )
     * @ViewResponse()
     * @Route("/hook", methods={"POST"})
     */
    public function hook(Request $request, MessageBusInterface $bus): JsonResponse
    {
        $update = json_decode($request->getContent(), true);

        if (!$update) {
            return $this->handleResponse([]);
        }

        $bus->dispatch(new UpdateTelegramEventMessage($update));

        return $this->handleResponse([]);
    }
}
