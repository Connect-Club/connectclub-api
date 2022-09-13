<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200720121052 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('UPDATE video_room_config SET publisher_radar_size = 3000');
        $this->addSql('UPDATE square_config SET publisher_radar_size = 3000');
        $this->addSql('ALTER TABLE video_room_config ALTER publisher_radar_size SET DEFAULT 3000');
        $this->addSql('ALTER TABLE square_config ALTER publisher_radar_size SET DEFAULT 3000');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE video_room_config ALTER publisher_radar_size DROP DEFAULT');
        $this->addSql('ALTER TABLE square_config ALTER publisher_radar_size DROP DEFAULT');
    }
}
