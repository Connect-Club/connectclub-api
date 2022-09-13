<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200327174909 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE video_meeting_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE video_meeting_event_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE video_meeting (id INT NOT NULL, video_room_id INT DEFAULT NULL, sid VARCHAR(255) NOT NULL, start_time BIGINT NOT NULL, end_time BIGINT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_10F80894B1FA993E ON video_meeting (video_room_id)');
        $this->addSql('CREATE TABLE video_meeting_event (id INT NOT NULL, video_meeting_id INT DEFAULT NULL, participant_id INT DEFAULT NULL, event VARCHAR(255) NOT NULL, time BIGINT NOT NULL, participant_duration VARCHAR(255) DEFAULT NULL, participant_identity VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_58E56497E7E4252A ON video_meeting_event (video_meeting_id)');
        $this->addSql('CREATE INDEX IDX_58E564979D1C3019 ON video_meeting_event (participant_id)');
        $this->addSql('ALTER TABLE video_meeting ADD CONSTRAINT FK_10F80894B1FA993E FOREIGN KEY (video_room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_meeting_event ADD CONSTRAINT FK_58E56497E7E4252A FOREIGN KEY (video_meeting_id) REFERENCES video_meeting (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_meeting_event ADD CONSTRAINT FK_58E564979D1C3019 FOREIGN KEY (participant_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE video_meeting_event DROP CONSTRAINT FK_58E56497E7E4252A');
        $this->addSql('DROP SEQUENCE video_meeting_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE video_meeting_event_id_seq CASCADE');
        $this->addSql('DROP TABLE video_meeting');
        $this->addSql('DROP TABLE video_meeting_event');
    }
}
