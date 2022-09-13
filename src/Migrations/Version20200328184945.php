<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200328184945 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP SEQUENCE video_meeting_event_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE video_meeting_participant_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE video_meeting_participant (id INT NOT NULL, video_meeting_id INT DEFAULT NULL, participant_id INT DEFAULT NULL, sid VARCHAR(255) NOT NULL, start_time BIGINT NOT NULL, end_time BIGINT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F3630FD557167AB4 ON video_meeting_participant (sid)');
        $this->addSql('CREATE INDEX IDX_F3630FD5E7E4252A ON video_meeting_participant (video_meeting_id)');
        $this->addSql('CREATE INDEX IDX_F3630FD59D1C3019 ON video_meeting_participant (participant_id)');
        $this->addSql('ALTER TABLE video_meeting_participant ADD CONSTRAINT FK_F3630FD5E7E4252A FOREIGN KEY (video_meeting_id) REFERENCES video_meeting (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_meeting_participant ADD CONSTRAINT FK_F3630FD59D1C3019 FOREIGN KEY (participant_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE video_meeting_event');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_10F8089457167AB4 ON video_meeting (sid)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE video_meeting_participant_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE video_meeting_event_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE video_meeting_event (id INT NOT NULL, video_meeting_id INT DEFAULT NULL, participant_id INT DEFAULT NULL, event VARCHAR(255) NOT NULL, "time" BIGINT NOT NULL, participant_duration VARCHAR(255) DEFAULT NULL, participant_identity VARCHAR(255) DEFAULT NULL, participant_sid VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_58e56497e7e4252a ON video_meeting_event (video_meeting_id)');
        $this->addSql('CREATE INDEX idx_58e564979d1c3019 ON video_meeting_event (participant_id)');
        $this->addSql('ALTER TABLE video_meeting_event ADD CONSTRAINT fk_58e56497e7e4252a FOREIGN KEY (video_meeting_id) REFERENCES video_meeting (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_meeting_event ADD CONSTRAINT fk_58e564979d1c3019 FOREIGN KEY (participant_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE video_meeting_participant');
        $this->addSql('DROP INDEX UNIQ_10F8089457167AB4');
    }
}
