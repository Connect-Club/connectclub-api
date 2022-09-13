<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200706080230 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE opened_video_room ADD width INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE opened_video_room ADD height INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE opened_video_room ADD location_x INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE opened_video_room ADD location_y INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE opened_video_room ALTER width DROP DEFAULT');
        $this->addSql('ALTER TABLE opened_video_room ALTER height DROP DEFAULT');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE opened_video_room DROP width');
        $this->addSql('ALTER TABLE opened_video_room DROP height');
        $this->addSql('ALTER TABLE opened_video_room DROP location_x');
        $this->addSql('ALTER TABLE opened_video_room DROP location_y');
    }
}
