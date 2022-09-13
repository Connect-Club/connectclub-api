<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210311194624 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE activity ADD event_schedule_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN activity.event_schedule_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AF821155 FOREIGN KEY (event_schedule_id) REFERENCES event_schedule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_AC74095AF821155 ON activity (event_schedule_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095AF821155');
        $this->addSql('DROP INDEX IDX_AC74095AF821155');
        $this->addSql('ALTER TABLE activity DROP event_schedule_id');
    }
}
