<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200629134642 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('UPDATE video_room_object SET radius = 0.0 WHERE type = \'fireplace\' AND radius IS NULL');
        $this->addSql('UPDATE video_room_object SET lottie_src = \'\' WHERE type = \'fireplace\' AND lottie_src IS NULL');
        $this->addSql('UPDATE video_room_object SET sound_src = \'\' WHERE type = \'fireplace\' AND sound_src IS NULL');

        $this->addSql('ALTER TABLE video_room_object ADD video_src VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE video_room_object ADD length INT DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE video_room_object DROP video_src');
        $this->addSql('ALTER TABLE video_room_object DROP length');
    }
}
