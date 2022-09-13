<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210720160019 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql(<<<SQL
        UPDATE users SET languages = (
            SELECT json_agg(a.lang) FROM (
                SELECT REPLACE(q.lang, '"', '') AS lang FROM (
                    SELECT distinct unnest(ARRAY(
                        SELECT json_array_elements(i.automatic_choose_for_region_codes)
                    )::text[]) as lang
                    FROM user_interest ui
                    JOIN interest i on ui.interest_id = i.id
                    WHERE ui.user_id = users.id
                    AND i.automatic_choose_for_region_codes IS NOT NULL
                ) q
            ) a
        ) WHERE languages IS NULL;
        SQL);

        $this->addSql(<<<SQL
        UPDATE event_schedule SET languages = (
            SELECT json_agg(a.lang) FROM (
                SELECT REPLACE(q.lang, '"', '') AS lang FROM (
                    SELECT distinct unnest(ARRAY(
                        SELECT json_array_elements(i.automatic_choose_for_region_codes
                    ))::text[]) as lang
                    FROM event_schedule_interest ei
                    JOIN interest i on ei.interest_id = i.id
                    WHERE ei.event_schedule_id = event_schedule.id
                    AND i.automatic_choose_for_region_codes IS NOT NULL
                ) q
            ) a
        ) WHERE languages IS NULL;
        SQL);

        $this->addSql(<<<SQL
        UPDATE event_schedule SET languages = (
            SELECT json_agg(a.lang) FROM (
                SELECT REPLACE(q.lang, '"', '') AS lang FROM (
                    SELECT distinct unnest(ARRAY(SELECT i.language_code)::text[]) as lang
                    FROM interest i
                    WHERE i.is_default_interest_for_regions = TRUE
                ) q
            ) a
        ) WHERE languages IS NULL
        SQL);

        $this->addSql(<<<SQL
        UPDATE users SET languages = (
            SELECT json_agg(a.lang) FROM (
                SELECT REPLACE(q.lang, '"', '') AS lang FROM (
                    SELECT distinct unnest(ARRAY(SELECT i.language_code)::text[]) as lang
                    FROM interest i
                    WHERE i.is_default_interest_for_regions = TRUE
                ) q
            ) a
        ) WHERE languages IS NULL
        SQL);
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
    }
}
