<?php

namespace App\Tests\Mock;

use App\Entity\VideoChat\BackgroundPhoto;
use App\Service\UserFileUploader;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MockUserFileUploader extends UserFileUploader
{
    public $name;

    public function uploadImage(string $bucket, UploadedFile $uploadedFile): array
    {
        $mimeType = mime_content_type($uploadedFile->getRealPath());
        $ext = explode('image/', $mimeType)[1];

        $filename = $this->name.'.png' ?? 'd6719718-7edb-487d-8710-9f5657e790cb.'.$ext;

        return [
            ['object' => $filename],
            ['object' => $filename]
        ];
    }

    public function uploadVideoRoomBackground(User $owner, UploadedFile $uploadedFile): BackgroundPhoto
    {
        return new BackgroundPhoto(
            'bucket',
            'http://resizer.url/bucket/demo.png',
            'http://resizer.url/bucket/demo_src.png',
            200,
            200,
            $owner
        );
    }
}
