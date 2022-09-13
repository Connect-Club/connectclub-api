<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210322160002 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE event_schedule ADD video_room_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event_schedule ADD CONSTRAINT FK_1CD4F82BB1FA993E FOREIGN KEY (video_room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1CD4F82BB1FA993E ON event_schedule (video_room_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE event_schedule DROP CONSTRAINT FK_1CD4F82BB1FA993E');
        $this->addSql('DROP INDEX UNIQ_1CD4F82BB1FA993E');
        $this->addSql('ALTER TABLE event_schedule DROP video_room_id');
    }
}
