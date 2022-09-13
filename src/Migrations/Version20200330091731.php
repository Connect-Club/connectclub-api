<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200330091731 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP SEQUENCE video_room_token_id_seq CASCADE');
        $this->addSql('DROP TABLE video_room_token');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE video_room_token_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE video_room_token (id INT NOT NULL, room_id INT DEFAULT NULL, user_id INT DEFAULT NULL, created_at BIGINT NOT NULL, expired_at BIGINT NOT NULL, token TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_952c182654177093 ON video_room_token (room_id)');
        $this->addSql('CREATE INDEX idx_952c1826a76ed395 ON video_room_token (user_id)');
        $this->addSql('ALTER TABLE video_room_token ADD CONSTRAINT fk_952c182654177093 FOREIGN KEY (room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_room_token ADD CONSTRAINT fk_952c1826a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
