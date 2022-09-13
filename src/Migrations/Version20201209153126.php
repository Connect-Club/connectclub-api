<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201209153126 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE community ADD password VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE community c SET password = (SELECT password FROM video_room v WHERE c.video_room_id = v.id)');
        $this->addSql('ALTER TABLE community ALTER password SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1B6040335E237E06 ON community (name)');
        $this->addSql('DROP INDEX uniq_75080c475e237e06');
        $this->addSql('ALTER TABLE video_room DROP name');
        $this->addSql('ALTER TABLE video_room DROP description');
        $this->addSql('ALTER TABLE video_room DROP password');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX UNIQ_1B6040335E237E06');
        $this->addSql('ALTER TABLE community DROP password');
        $this->addSql('ALTER TABLE video_room ADD name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE video_room ADD password VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_75080c475e237e06 ON video_room (name)');
    }
}
