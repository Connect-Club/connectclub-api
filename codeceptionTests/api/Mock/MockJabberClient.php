<?php

namespace App\Tests\Mock;

use App\Jabber\JabberClient;

class MockJabberClient extends JabberClient
{
    public function registerNick(string $username)
    {
    }

    public function registerUser(string $username, string $password)
    {
    }

    public function setRoomMemberAffiliation(string $username, string $roomName)
    {
    }

    public function subscribeRoomMessages(string $username, string $roomName)
    {
    }

    public function createRoom(string $name)
    {
    }
}
