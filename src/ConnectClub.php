<?php

namespace App;

use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;

class ConnectClub
{
    public const SMS_VERIFICATION_EXPIRES_IN = 60;

    public const VIDEO_ROOM_SESSION_EXPIRES_AT = 24 * 3600;
    public const DEFAULT_LANG = 'en_en';
    public const MAX_VIDEO_ROOM_PARTICIPANTS = 60;

    public const ONLINE_USER_ACTIVITY_LIMIT = 60 * 5;

    public static function shortVideoRoomLink(VideoRoom $videoRoom): string
    {
        return 'https://app.cnnct.club/a/key_live_lbUKXoq5Mu4PgpAt7S4v8kceutij138R'
               .'?room='.urlencode($videoRoom->community->name).'&pswd='.urlencode($videoRoom->community->password);
    }

    public static function generateString(int $length, string $alphabet = null): string
    {
        $alphabet = $alphabet ?? 'abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789';
        $pass = [];

        for ($i = 0; $i < $length; ++$i) {
            $n = mt_rand(0, mb_strlen($alphabet) - 1);
            $pass[$i] = $alphabet[$n];
        }

        return implode('', $pass);
    }

    public static function getTelegramChannelForLanguage(User $user): string
    {
        $languageCode = $user->languages[0] ?? null;

        return $languageCode == 'RU' ? 'https://t.me/connect_club_chat' : 'https://t.me/connect_club_eng';
    }

    public static function getDiscordLink(): string
    {
        return $_ENV['JOIN_DISCORD_LINK'];
    }

    public static function generateMetamaskMessageForUser(User $user): string
    {
        return self::generateMetamaskMessageForNonce($user->metaMaskNonce);
    }

    public static function generateMetamaskMessageForNonce(string $nonce): string
    {
        return 'Connect my wallet with Connect.Club account. Nonce: '.$nonce;
    }
}
