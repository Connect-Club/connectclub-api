<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200413081232 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE video_room ADD password VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE video_room_history ADD password VARCHAR(255) DEFAULT NULL');

        $this->addSql('UPDATE video_room SET password = substring(md5(random()::text), 0, 16)');
        $this->addSql('UPDATE video_room_history h SET password = (SELECT password FROM video_room r WHERE h.video_room_id = r.id)');

        $this->addSql('ALTER TABLE video_room_history ALTER password SET NOT NULL');
        $this->addSql('ALTER TABLE video_room ALTER password SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE video_room DROP password');
        $this->addSql('ALTER TABLE video_room_history DROP password');
    }
}
