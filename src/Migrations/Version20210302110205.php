<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210302110205 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE event_schedule (id UUID NOT NULL, name VARCHAR(255) NOT NULL, date_time BIGINT NOT NULL, description VARCHAR(255) NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN event_schedule.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE event_schedule_participant (id UUID NOT NULL, event_id UUID DEFAULT NULL, user_id INT DEFAULT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CF8FBE6671F7E88B ON event_schedule_participant (event_id)');
        $this->addSql('CREATE INDEX IDX_CF8FBE66A76ED395 ON event_schedule_participant (user_id)');
        $this->addSql('COMMENT ON COLUMN event_schedule_participant.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN event_schedule_participant.event_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE event_schedule_participant ADD CONSTRAINT FK_CF8FBE6671F7E88B FOREIGN KEY (event_id) REFERENCES event_schedule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_schedule_participant ADD CONSTRAINT FK_CF8FBE66A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE event_schedule_participant DROP CONSTRAINT FK_CF8FBE6671F7E88B');
        $this->addSql('DROP TABLE event_schedule');
        $this->addSql('DROP TABLE event_schedule_participant');
    }
}
