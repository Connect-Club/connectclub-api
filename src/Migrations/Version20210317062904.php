<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210317062904 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE event_draft (id UUID NOT NULL, background_photo_id INT DEFAULT NULL, description VARCHAR(255) NOT NULL, background_room_width_multiplier INT NOT NULL, background_room_height_multiplier INT DEFAULT 2 NOT NULL, index INT NOT NULL, with_speakers BOOLEAN NOT NULL, initial_room_scale INT NOT NULL, publisher_radar_size INT NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_76727B8A5E1414B ON event_draft (background_photo_id)');
        $this->addSql('COMMENT ON COLUMN event_draft.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE event_draft ADD CONSTRAINT FK_76727B8A5E1414B FOREIGN KEY (background_photo_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_room ADD done_at INT DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE event_draft');
        $this->addSql('ALTER TABLE video_room DROP done_at');
    }
}
