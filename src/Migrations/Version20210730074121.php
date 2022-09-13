<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210730074121 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE event_schedule_festival_scene (id UUID NOT NULL, scene_code VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_78E48276CB35B9C ON event_schedule_festival_scene (scene_code)');
        $this->addSql('COMMENT ON COLUMN event_schedule_festival_scene.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE event_schedule ADD festival_scene_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE event_schedule DROP festival_scene_code');
        $this->addSql('COMMENT ON COLUMN event_schedule.festival_scene_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE event_schedule ADD CONSTRAINT FK_1CD4F82B7CFD967F FOREIGN KEY (festival_scene_id) REFERENCES event_schedule_festival_scene (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_1CD4F82B7CFD967F ON event_schedule (festival_scene_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE event_schedule DROP CONSTRAINT FK_1CD4F82B7CFD967F');
        $this->addSql('DROP TABLE event_schedule_festival_scene');
        $this->addSql('DROP INDEX IDX_1CD4F82B7CFD967F');
        $this->addSql('ALTER TABLE event_schedule ADD festival_scene_code VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE event_schedule DROP festival_scene_id');
    }
}
