<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200911144609 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE networking_meeting_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE networking_meeting_match_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE networking_meeting (id INT NOT NULL, video_room_id INT DEFAULT NULL, networking_time_range_start BIGINT NOT NULL, networking_time_range_end BIGINT NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E9571116B1FA993E ON networking_meeting (video_room_id)');
        $this->addSql('CREATE TABLE networking_meeting_user (networking_meeting_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(networking_meeting_id, user_id))');
        $this->addSql('CREATE INDEX IDX_31FC841AD83CFD5C ON networking_meeting_user (networking_meeting_id)');
        $this->addSql('CREATE INDEX IDX_31FC841AA76ED395 ON networking_meeting_user (user_id)');
        $this->addSql('CREATE TABLE networking_meeting_match (id INT NOT NULL, networking_meeting_id INT DEFAULT NULL, first_user_id INT DEFAULT NULL, second_user_id INT DEFAULT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8885AA19D83CFD5C ON networking_meeting_match (networking_meeting_id)');
        $this->addSql('CREATE INDEX IDX_8885AA19B4E2BF69 ON networking_meeting_match (first_user_id)');
        $this->addSql('CREATE INDEX IDX_8885AA19B02C53F8 ON networking_meeting_match (second_user_id)');
        $this->addSql('ALTER TABLE networking_meeting ADD CONSTRAINT FK_E9571116B1FA993E FOREIGN KEY (video_room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting_user ADD CONSTRAINT FK_31FC841AD83CFD5C FOREIGN KEY (networking_meeting_id) REFERENCES networking_meeting (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting_user ADD CONSTRAINT FK_31FC841AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting_match ADD CONSTRAINT FK_8885AA19D83CFD5C FOREIGN KEY (networking_meeting_id) REFERENCES networking_meeting (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting_match ADD CONSTRAINT FK_8885AA19B4E2BF69 FOREIGN KEY (first_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting_match ADD CONSTRAINT FK_8885AA19B02C53F8 FOREIGN KEY (second_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE networking_meeting_user DROP CONSTRAINT FK_31FC841AD83CFD5C');
        $this->addSql('ALTER TABLE networking_meeting_match DROP CONSTRAINT FK_8885AA19D83CFD5C');
        $this->addSql('DROP SEQUENCE networking_meeting_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE networking_meeting_match_id_seq CASCADE');
        $this->addSql('DROP TABLE networking_meeting');
        $this->addSql('DROP TABLE networking_meeting_user');
        $this->addSql('DROP TABLE networking_meeting_match');
    }
}
