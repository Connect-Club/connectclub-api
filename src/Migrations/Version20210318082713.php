<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210318082713 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE video_room ADD started_at INT DEFAULT NULL');
        $this->addSql('UPDATE video_room SET done_at = created_at WHERE id IN (
            SELECT vr.id FROM video_room vr
            JOIN community c on vr.id = c.video_room_id
            WHERE vr.type = \'new\' AND vr.done_at IS NULL
            AND (SELECT COUNT(*) FROM video_meeting vm WHERE vm.video_room_id = vr.id AND vm.end_time IS NOT NULL) = 0
        )');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE video_room DROP started_at');
    }
}
