<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210623141211 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE event_schedule_interest (event_schedule_id UUID NOT NULL, interest_id INT NOT NULL, PRIMARY KEY(event_schedule_id, interest_id))');
        $this->addSql('CREATE INDEX IDX_454CA930F821155 ON event_schedule_interest (event_schedule_id)');
        $this->addSql('CREATE INDEX IDX_454CA9305A95FF89 ON event_schedule_interest (interest_id)');
        $this->addSql('COMMENT ON COLUMN event_schedule_interest.event_schedule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE event_schedule_interest ADD CONSTRAINT FK_454CA930F821155 FOREIGN KEY (event_schedule_id) REFERENCES event_schedule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_schedule_interest ADD CONSTRAINT FK_454CA9305A95FF89 FOREIGN KEY (interest_id) REFERENCES interest (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE event_schedule_interest');
    }
}
