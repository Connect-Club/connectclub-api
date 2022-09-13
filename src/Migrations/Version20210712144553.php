<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210712144553 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE event_schedule_subscription (id UUID NOT NULL, event_schedule_id UUID DEFAULT NULL, user_id INT DEFAULT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6DBF9099F821155 ON event_schedule_subscription (event_schedule_id)');
        $this->addSql('CREATE INDEX IDX_6DBF9099A76ED395 ON event_schedule_subscription (user_id)');
        $this->addSql('COMMENT ON COLUMN event_schedule_subscription.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN event_schedule_subscription.event_schedule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE event_schedule_subscription ADD CONSTRAINT FK_6DBF9099F821155 FOREIGN KEY (event_schedule_id) REFERENCES event_schedule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_schedule_subscription ADD CONSTRAINT FK_6DBF9099A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE event_schedule_subscription');
    }
}
