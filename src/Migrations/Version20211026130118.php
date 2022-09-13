<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211026130118 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE event_schedule ADD club_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN event_schedule.club_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE event_schedule ADD CONSTRAINT FK_1CD4F82B61190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_1CD4F82B61190A32 ON event_schedule (club_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE event_schedule DROP CONSTRAINT FK_1CD4F82B61190A32');
        $this->addSql('DROP INDEX IDX_1CD4F82B61190A32');
        $this->addSql('ALTER TABLE event_schedule DROP club_id');
    }
}
