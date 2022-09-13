<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200311111829 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE video_room_config_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE video_room_config (id INT NOT NULL, background_room_id INT DEFAULT NULL, initial_room_scale INT NOT NULL, min_room_zoom INT NOT NULL, max_room_zoom INT NOT NULL, video_bubble_size INT NOT NULL, interval_to_send_data_track_in_milliseconds INT NOT NULL, videoQuality_width INT NOT NULL, videoQuality_height INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B746581C90D361B9 ON video_room_config (background_room_id)');
        $this->addSql('ALTER TABLE video_room_config ADD CONSTRAINT FK_B746581C90D361B9 FOREIGN KEY (background_room_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_room ADD config_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE video_room DROP config_background_room');
        $this->addSql('ALTER TABLE video_room DROP config_initial_room_scale');
        $this->addSql('ALTER TABLE video_room DROP config_min_room_zoom');
        $this->addSql('ALTER TABLE video_room DROP config_max_room_zoom');
        $this->addSql('ALTER TABLE video_room DROP config_video_bubble_size');
        $this->addSql('ALTER TABLE video_room DROP config_interval_to_send_data_track_in_milliseconds');
        $this->addSql('ALTER TABLE video_room DROP config_videoquality_width');
        $this->addSql('ALTER TABLE video_room DROP config_videoquality_height');
        $this->addSql('ALTER TABLE video_room ADD CONSTRAINT FK_75080C4724DB0683 FOREIGN KEY (config_id) REFERENCES video_room_config (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_75080C4724DB0683 ON video_room (config_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE video_room DROP CONSTRAINT FK_75080C4724DB0683');
        $this->addSql('DROP SEQUENCE video_room_config_id_seq CASCADE');
        $this->addSql('DROP TABLE video_room_config');
        $this->addSql('DROP INDEX UNIQ_75080C4724DB0683');
        $this->addSql('ALTER TABLE video_room ADD config_background_room VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_initial_room_scale INT NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_min_room_zoom INT NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_max_room_zoom INT NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_video_bubble_size INT NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_interval_to_send_data_track_in_milliseconds INT NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_videoquality_width INT NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_videoquality_height INT NOT NULL');
        $this->addSql('ALTER TABLE video_room DROP config_id');
    }
}
