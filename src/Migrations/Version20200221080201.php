<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200221080201 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE token DROP CONSTRAINT fk_5f37a13b54177093');
        $this->addSql('DROP SEQUENCE room_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE token_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE video_room_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE video_room_token_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE video_room (id INT NOT NULL, owner_id INT DEFAULT NULL, sid VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_75080C475E237E06 ON video_room (name)');
        $this->addSql('CREATE INDEX IDX_75080C477E3C61F9 ON video_room (owner_id)');
        $this->addSql('CREATE TABLE video_room_token (id INT NOT NULL, room_id INT DEFAULT NULL, user_id INT DEFAULT NULL, created_at BIGINT NOT NULL, expired_at BIGINT NOT NULL, token TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_952C182654177093 ON video_room_token (room_id)');
        $this->addSql('CREATE INDEX IDX_952C1826A76ED395 ON video_room_token (user_id)');
        $this->addSql('ALTER TABLE video_room ADD CONSTRAINT FK_75080C477E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_room_token ADD CONSTRAINT FK_952C182654177093 FOREIGN KEY (room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_room_token ADD CONSTRAINT FK_952C1826A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE room');
        $this->addSql('DROP TABLE token');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE video_room_token DROP CONSTRAINT FK_952C182654177093');
        $this->addSql('DROP SEQUENCE video_room_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE video_room_token_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE room_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE token_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE room (id INT NOT NULL, owner_id INT DEFAULT NULL, sid VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_729f519b7e3c61f9 ON room (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_729f519b5e237e06 ON room (name)');
        $this->addSql('CREATE TABLE token (id INT NOT NULL, room_id INT DEFAULT NULL, user_id INT DEFAULT NULL, created_at BIGINT NOT NULL, expired_at BIGINT NOT NULL, token TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_5f37a13ba76ed395 ON token (user_id)');
        $this->addSql('CREATE INDEX idx_5f37a13b54177093 ON token (room_id)');
        $this->addSql('ALTER TABLE room ADD CONSTRAINT fk_729f519b7e3c61f9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE token ADD CONSTRAINT fk_5f37a13b54177093 FOREIGN KEY (room_id) REFERENCES room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE token ADD CONSTRAINT fk_5f37a13ba76ed395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE video_room');
        $this->addSql('DROP TABLE video_room_token');
    }
}
