<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220211083504 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE video_room_participant_statistic (id UUID NOT NULL, video_room_id INT DEFAULT NULL, participant_id INT DEFAULT NULL, endpoint_uuid VARCHAR(255) NOT NULL, conference_id VARCHAR(255) NOT NULL, rtt DOUBLE PRECISION NOT NULL, jitter DOUBLE PRECISION NOT NULL, commutative_packets_lost BIGINT NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B2946661B1FA993E ON video_room_participant_statistic (video_room_id)');
        $this->addSql('CREATE INDEX IDX_B29466619D1C3019 ON video_room_participant_statistic (participant_id)');
        $this->addSql('COMMENT ON COLUMN video_room_participant_statistic.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE video_room_participant_statistic ADD CONSTRAINT FK_B2946661B1FA993E FOREIGN KEY (video_room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_room_participant_statistic ADD CONSTRAINT FK_B29466619D1C3019 FOREIGN KEY (participant_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE video_room_participant_statistic');
    }
}
