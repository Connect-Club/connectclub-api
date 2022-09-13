<?php

namespace App\DTO\V1\Location;

class PhoneNumberCountryItemResponse
{
    public string $regionPrefix;
    public string $pattern;
    public array $possibleLength = [];
    public string $example;
    public string $examplePattern;
    public string $name;

    public function __construct(
        string $regionPrefix,
        string $pattern,
        array $possibleLength,
        string $example,
        string $examplePattern,
        string $name
    ) {
        $this->regionPrefix = $regionPrefix;
        $this->pattern = $pattern;
        $this->possibleLength = $possibleLength;
        $this->example = $example;
        $this->examplePattern = $examplePattern;
        $this->name = $name;
    }
}
