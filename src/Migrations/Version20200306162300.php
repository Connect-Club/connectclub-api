<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200306162300 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE video_room ADD config_background_room VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_initial_room_scale INT NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_min_room_zoom INT NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_max_room_zoom INT NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_video_bubble INT NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_interval_to_send_data_track_in_milliseconds INT NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_videoQuality_width INT NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD config_videoQuality_height INT NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE video_room DROP config_background_room');
        $this->addSql('ALTER TABLE video_room DROP config_initial_room_scale');
        $this->addSql('ALTER TABLE video_room DROP config_min_room_zoom');
        $this->addSql('ALTER TABLE video_room DROP config_max_room_zoom');
        $this->addSql('ALTER TABLE video_room DROP config_video_bubble');
        $this->addSql('ALTER TABLE video_room DROP config_interval_to_send_data_track_in_milliseconds');
        $this->addSql('ALTER TABLE video_room DROP config_videoQuality_width');
        $this->addSql('ALTER TABLE video_room DROP config_videoQuality_height');
    }
}
