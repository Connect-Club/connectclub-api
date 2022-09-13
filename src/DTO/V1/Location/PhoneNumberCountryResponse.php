<?php

namespace App\DTO\V1\Location;

use App\Annotation\SerializationContext;
use Swagger\Annotations as SWG;

class PhoneNumberCountryResponse
{
    /**
     * @var string|null
     */
    public ?string $detectRegionCode;

    /**
     * @SerializationContext(serializeAsObject=true)
     * @var PhoneNumberCountryItemResponse[]
     * @SWG\Property(
     *     type="object",
     *     additionalProperties=@SWG\Schema(type="integer")
     * )
     */
    public array $availableRegions;

    public function __construct(?string $detectRegionCode, array $availableRegions)
    {
        $this->detectRegionCode = $detectRegionCode;
        $this->availableRegions = $availableRegions;
    }
}
