<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220420103322 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE request_approve_private_meeting_change (id UUID NOT NULL, event_schedule_id UUID DEFAULT NULL, user_id INT DEFAULT NULL, reviewed BOOLEAN NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FF648871F821155 ON request_approve_private_meeting_change (event_schedule_id)');
        $this->addSql('CREATE INDEX IDX_FF648871A76ED395 ON request_approve_private_meeting_change (user_id)');
        $this->addSql('COMMENT ON COLUMN request_approve_private_meeting_change.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN request_approve_private_meeting_change.event_schedule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE request_approve_private_meeting_change ADD CONSTRAINT FK_FF648871F821155 FOREIGN KEY (event_schedule_id) REFERENCES event_schedule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE request_approve_private_meeting_change ADD CONSTRAINT FK_FF648871A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE archetype');
        $this->addSql('ALTER TABLE event_schedule ADD is_private BOOLEAN DEFAULT \'false\' NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE archetype (id UUID NOT NULL, code VARCHAR(255) NOT NULL, configuration JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_e1d5bce377153098 ON archetype (code)');
        $this->addSql('COMMENT ON COLUMN archetype.id IS \'(DC2Type:uuid)\'');
        $this->addSql('DROP TABLE request_approve_private_meeting_change');
        $this->addSql('ALTER TABLE event_schedule DROP is_private');
    }
}
