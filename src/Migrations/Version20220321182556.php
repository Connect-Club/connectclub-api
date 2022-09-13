<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220321182556 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE event_schedule ADD for_owner_token_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN event_schedule.for_owner_token_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE event_schedule ADD CONSTRAINT FK_1CD4F82B3F99492B FOREIGN KEY (for_owner_token_id) REFERENCES token (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_1CD4F82B3F99492B ON event_schedule (for_owner_token_id)');
        $this->addSql('ALTER TABLE token ADD initialized_at BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE token ADD initialized_data JSON DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE token DROP initialized_at');
        $this->addSql('ALTER TABLE token DROP initialized_data');
        $this->addSql('ALTER TABLE event_schedule DROP CONSTRAINT FK_1CD4F82B3F99492B');
        $this->addSql('DROP INDEX IDX_1CD4F82B3F99492B');
        $this->addSql('ALTER TABLE event_schedule DROP for_owner_token_id');
    }
}
