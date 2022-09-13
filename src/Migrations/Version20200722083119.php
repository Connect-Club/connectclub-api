<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200722083119 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('
            INSERT INTO video_room_object
            (background_id, id, type, name, password, width, height, position_x, position_y)
            SELECT
                DISTINCT ON (p.id) p.id AS background_id,
                nextval(\'video_room_object_id_seq\') AS id,
                \'speaker_location\' AS type,
                \'speaker_location\' AS name,
                NULL AS password,
                480 AS width,
                480 AS height,
                vrc.speaker_location_x - 240 AS position_x,
                vrc.speaker_location_y - 240 AS position_y
            FROM photo p
                INNER JOIN video_room_config vrc ON vrc.background_room_id = p.id
            WHERE p.type = \'videoRoomBackground\' AND vrc.speaker_location_x != 0 AND vrc.speaker_location_y != 0
            AND p.id NOT IN (SELECT background_id FROM video_room_object WHERE type = \'speaker_location\');
        ');

        $this->addSql('ALTER TABLE video_room_draft DROP speaker_location_x');
        $this->addSql('ALTER TABLE video_room_draft DROP speaker_location_y');
        $this->addSql('ALTER TABLE video_room_config DROP speaker_location_x');
        $this->addSql('ALTER TABLE video_room_config DROP speaker_location_y');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE video_room_config ADD speaker_location_x INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE video_room_config ADD speaker_location_y INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE video_room_draft ADD speaker_location_x INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE video_room_draft ADD speaker_location_y INT DEFAULT 0 NOT NULL');
    }
}
