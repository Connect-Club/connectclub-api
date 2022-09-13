<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200509072238 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE user_contact ADD video_room_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_contact ADD sid VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_contact ADD CONSTRAINT FK_146FF832B1FA993E FOREIGN KEY (video_room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_146FF832B1FA993E ON user_contact (video_room_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE user_contact DROP CONSTRAINT FK_146FF832B1FA993E');
        $this->addSql('DROP INDEX IDX_146FF832B1FA993E');
        $this->addSql('ALTER TABLE user_contact DROP video_room_id');
        $this->addSql('ALTER TABLE user_contact DROP sid');
    }
}
