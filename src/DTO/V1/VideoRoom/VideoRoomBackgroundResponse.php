<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\VideoChat\BackgroundPhoto;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomBackgroundResponse
{
    /**
     * @var int
     * @Groups({"default"})
     */
    public ?int $id;
    /**
     * @var string
     * @Groups({"default"})
     */
    public string $originalName;
    /**
     * @var string
     * @Groups({"default"})
     */
    public string $processedName;
    /**
     * @var int
     * @Groups({"default"})
     */
    public ?int $width;
    /**
     * @var int
     * @Groups({"default"})
     */
    public ?int $height;
    /**
     * @var string
     * @Groups({"default"})
     */
    public string $originalUrl;
    /**
     * @var string
     * @Groups({"default"})
     */
    public string $resizerUrl;
    /**
     * @var string
     * @Groups({"default"})
     */
    public string $bucket;
    /**
     * @var int
     * @Groups({"default"})
     */
    public int $uploadAt;

    public function __construct(BackgroundPhoto $backgroundPhoto)
    {
        $this->id = $backgroundPhoto->id;
        $this->originalName = $backgroundPhoto->originalName;
        $this->processedName = $backgroundPhoto->processedName;
        $this->width = $backgroundPhoto->width;
        $this->height = $backgroundPhoto->height;
        $this->originalUrl = $backgroundPhoto->getOriginalUrl();
        $this->resizerUrl = $backgroundPhoto->getResizerUrl();
        $this->bucket = $backgroundPhoto->bucket;
        $this->uploadAt = $backgroundPhoto->uploadAt;
    }
}
