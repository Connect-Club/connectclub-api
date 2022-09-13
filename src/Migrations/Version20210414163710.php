<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210414163710 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE video_room_user (video_room_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(video_room_id, user_id))');
        $this->addSql('CREATE INDEX IDX_2CE841B8B1FA993E ON video_room_user (video_room_id)');
        $this->addSql('CREATE INDEX IDX_2CE841B8A76ED395 ON video_room_user (user_id)');
        $this->addSql('ALTER TABLE video_room_user ADD CONSTRAINT FK_2CE841B8B1FA993E FOREIGN KEY (video_room_id) REFERENCES video_room (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_room_user ADD CONSTRAINT FK_2CE841B8A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_room ADD is_private BOOLEAN DEFAULT \'false\' NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE video_room_user');
        $this->addSql('ALTER TABLE video_room DROP is_private');
    }
}
