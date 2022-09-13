<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200707084027 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE square_config_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE square_config (id INT NOT NULL, background_photo_id INT DEFAULT NULL, square_id INT DEFAULT NULL, background_room_width_multiplier INT NOT NULL, background_room_height_multiplier INT NOT NULL, initial_room_scale INT NOT NULL, min_room_zoom INT NOT NULL, max_room_zoom INT NOT NULL, bubble_size INT NOT NULL, publisher_radar_size INT NOT NULL, interval_to_send_data_track_in_milliseconds INT NOT NULL, image_memory_multiplier DOUBLE PRECISION DEFAULT \'0.75\' NOT NULL, width INT NOT NULL, height INT NOT NULL, speaker_location_x INT DEFAULT 0 NOT NULL, speaker_location_y INT DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F07E58A1A5E1414B ON square_config (background_photo_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F07E58A124CFF17F ON square_config (square_id)');
        $this->addSql('ALTER TABLE square_config ADD CONSTRAINT FK_F07E58A1A5E1414B FOREIGN KEY (background_photo_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE square_config ADD CONSTRAINT FK_F07E58A124CFF17F FOREIGN KEY (square_id) REFERENCES square (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE square_config_id_seq CASCADE');
        $this->addSql('DROP TABLE square_config');
    }
}
