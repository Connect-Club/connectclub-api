<?php

namespace App\Doctrine\SQL\Snippet;

class SQLEventScheduleMutualLanguagesSnippet
{
    public static function sql(string $aliasEventSchedule = 'es', string $userIdParameter = 'userId'): string
    {
        return 'jsonb_exists_any('.$aliasEventSchedule.'.languages::jsonb,
               ARRAY(
                   SELECT jsonb_array_elements_text(languages::jsonb) FROM users _u WHERE _u.id = :'.$userIdParameter.'
               )::text[])';
    }
}
