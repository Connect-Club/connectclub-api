<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210908165842 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE video_room_event (id UUID NOT NULL, video_room_id INT DEFAULT NULL, user_id INT DEFAULT NULL, event VARCHAR(255) NOT NULL, time BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F1B5B3BAB1FA993E ON video_room_event (video_room_id)');
        $this->addSql('CREATE INDEX IDX_F1B5B3BAA76ED395 ON video_room_event (user_id)');
        $this->addSql('COMMENT ON COLUMN video_room_event.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE video_room_event ADD CONSTRAINT FK_F1B5B3BAB1FA993E FOREIGN KEY (video_room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_room_event ADD CONSTRAINT FK_F1B5B3BAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE video_room_event');
    }
}
