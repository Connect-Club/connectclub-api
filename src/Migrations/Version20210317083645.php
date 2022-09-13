<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210317083645 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE video_room ADD event_schedule_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN video_room.event_schedule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE video_room ADD CONSTRAINT FK_75080C47F821155 FOREIGN KEY (event_schedule_id) REFERENCES event_schedule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_75080C47F821155 ON video_room (event_schedule_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE video_room DROP CONSTRAINT FK_75080C47F821155');
        $this->addSql('DROP INDEX IDX_75080C47F821155');
        $this->addSql('ALTER TABLE video_room DROP event_schedule_id');
    }
}
