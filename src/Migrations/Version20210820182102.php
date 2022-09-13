<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210820182102 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE event_schedule ADD language_id INT DEFAULT NULL');
        $this->addSql('UPDATE event_schedule SET language_id = (
                           SELECT id 
                           FROM interest 
                           WHERE is_default_interest_for_regions IS NOT NULL 
                           LIMIT 1
                       )');
        $this->addSql('ALTER TABLE event_schedule ADD CONSTRAINT FK_1CD4F82B82F1BAF4 FOREIGN KEY (language_id) REFERENCES interest (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_1CD4F82B82F1BAF4 ON event_schedule (language_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE event_schedule DROP CONSTRAINT FK_1CD4F82B82F1BAF4');
        $this->addSql('DROP INDEX IDX_1CD4F82B82F1BAF4');
        $this->addSql('ALTER TABLE event_schedule DROP language_id');
    }
}
