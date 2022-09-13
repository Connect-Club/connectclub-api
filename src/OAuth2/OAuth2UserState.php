<?php

namespace App\OAuth2;

/**
 * Class OAuth2UserState.
 */
trait OAuth2UserState
{
    private string $currentOAuth2Event = 'login';

    public function getCurrentOAuth2Event(): string
    {
        return $this->currentOAuth2Event;
    }

    public function setCurrentOAuth2Event(string $currentOAuth2Event): void
    {
        $this->currentOAuth2Event = $currentOAuth2Event;
    }
}
