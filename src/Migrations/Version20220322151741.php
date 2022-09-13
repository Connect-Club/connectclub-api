<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220322151741 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE event_token (id UUID NOT NULL, event_schedule_id UUID DEFAULT NULL, token_id UUID DEFAULT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_1E2C1017F821155 ON event_token (event_schedule_id)');
        $this->addSql('CREATE INDEX IDX_1E2C101741DEE7B9 ON event_token (token_id)');
        $this->addSql('COMMENT ON COLUMN event_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN event_token.event_schedule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN event_token.token_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE event_token ADD CONSTRAINT FK_1E2C1017F821155 FOREIGN KEY (event_schedule_id) REFERENCES event_schedule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_token ADD CONSTRAINT FK_1E2C101741DEE7B9 FOREIGN KEY (token_id) REFERENCES token (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_schedule DROP CONSTRAINT fk_1cd4f82b3f99492b');
        $this->addSql('DROP INDEX idx_1cd4f82b3f99492b');
        $this->addSql('ALTER TABLE event_schedule DROP for_owner_token_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE event_token');
        $this->addSql('ALTER TABLE event_schedule ADD for_owner_token_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN event_schedule.for_owner_token_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE event_schedule ADD CONSTRAINT fk_1cd4f82b3f99492b FOREIGN KEY (for_owner_token_id) REFERENCES token (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_1cd4f82b3f99492b ON event_schedule (for_owner_token_id)');
    }
}
