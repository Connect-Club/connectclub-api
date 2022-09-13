<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211119152003 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<SQL
            DELETE FROM activity a
            WHERE a.type = 'user-club-schedule-event'
            AND NOT EXISTS (
                SELECT * FROM club_participant cp WHERE cp.club_id = a.club_id AND cp.user_id = a.user_id
            );
            SQL
        );

        $this->addSql(
            <<<SQL
            DELETE FROM event_schedule_subscription WHERE id IN (
                SELECT ess.id FROM event_schedule_subscription ess
                JOIN event_schedule es ON ess.event_schedule_id = es.id
                JOIN club c on es.club_id = c.id
                WHERE NOT EXISTS (
                    SELECT 1 FROM club_participant cp WHERE cp.id = es.club_id AND cp.user_id = ess.user_id    
                )    
            )
            SQL
        );
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
