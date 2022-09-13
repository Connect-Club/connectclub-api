<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200702095845 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE square_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE opened_video_room_variant_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE opened_video_room_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE square (id INT NOT NULL, code VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE opened_video_room_variant (id INT NOT NULL, opened_video_room_id INT DEFAULT NULL, video_room_id INT DEFAULT NULL, available_for_continent JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FB2A3447513B6C6E ON opened_video_room_variant (opened_video_room_id)');
        $this->addSql('CREATE INDEX IDX_FB2A3447B1FA993E ON opened_video_room_variant (video_room_id)');
        $this->addSql('CREATE TABLE opened_video_room (id INT NOT NULL, square_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D05D745724CFF17F ON opened_video_room (square_id)');
        $this->addSql('ALTER TABLE opened_video_room_variant ADD CONSTRAINT FK_FB2A3447513B6C6E FOREIGN KEY (opened_video_room_id) REFERENCES opened_video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE opened_video_room_variant ADD CONSTRAINT FK_FB2A3447B1FA993E FOREIGN KEY (video_room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE opened_video_room ADD CONSTRAINT FK_D05D745724CFF17F FOREIGN KEY (square_id) REFERENCES square (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_room ADD open BOOLEAN DEFAULT \'false\' NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE opened_video_room DROP CONSTRAINT FK_D05D745724CFF17F');
        $this->addSql('ALTER TABLE opened_video_room_variant DROP CONSTRAINT FK_FB2A3447513B6C6E');
        $this->addSql('DROP SEQUENCE square_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE opened_video_room_variant_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE opened_video_room_id_seq CASCADE');
        $this->addSql('DROP TABLE square');
        $this->addSql('DROP TABLE opened_video_room_variant');
        $this->addSql('DROP TABLE opened_video_room');
        $this->addSql('ALTER TABLE video_room DROP open');
    }
}
