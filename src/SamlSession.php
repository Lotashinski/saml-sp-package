<?php

namespace Grsu\SamlSpService;


final class SamlSession
{

    private array $samlUserdata;
    private string $samlNameId;
    private string $samlNameIdFormat;
    private ?string $samlNameIdNameQualifier;
    private string $samlNameIdSPNameQualifier;
    private ?string $samlSessionIndex;


    public function __construct(
        array   $samlUserdata,
        string  $samlNameId,
        string  $samlNameIdFormat,
        ?string  $samlNameIdNameQualifier,
        string  $samlNameIdSPNameQualifier,
        ?string $samlSessionIndex
    )
    {
        $this->samlUserdata = $samlUserdata;
        $this->samlNameId = $samlNameId;
        $this->samlNameIdFormat = $samlNameIdFormat;
        $this->samlNameIdNameQualifier = $samlNameIdNameQualifier;
        $this->samlNameIdSPNameQualifier = $samlNameIdSPNameQualifier;
        $this->samlSessionIndex = $samlSessionIndex;
    }


    public function getSamlUserdata(): array
    {
        return $this->samlUserdata;
    }

    public function getSamlNameId(): string
    {
        return $this->samlNameId;
    }

    public function getSamlNameIdFormat(): string
    {
        return $this->samlNameIdFormat;
    }

    public function getSamlNameIdNameQualifier(): ?string
    {
        return $this->samlNameIdNameQualifier;
    }

    public function getSamlNameIdSPNameQualifier(): string
    {
        return $this->samlNameIdSPNameQualifier;
    }

    public function getSamlSessionIndex(): ?string
    {
        return $this->samlSessionIndex;
    }

}