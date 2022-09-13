<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200707122856 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE square_config RENAME COLUMN bubble_size TO video_bubble_size');
        $this->addSql('ALTER TABLE square_config ADD video_quality_width INT NOT NULL');
        $this->addSql('ALTER TABLE square_config ADD video_quality_height INT NOT NULL');
        $this->addSql('ALTER TABLE square_config DROP speaker_location_x');
        $this->addSql('ALTER TABLE square_config DROP speaker_location_y');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE square_config RENAME COLUMN video_bubble_size TO bubble_size');
        $this->addSql('ALTER TABLE square_config ADD speaker_location_x INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE square_config ADD speaker_location_y INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE square_config DROP video_quality_width');
        $this->addSql('ALTER TABLE square_config DROP video_quality_height');
    }
}
