<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210208151114 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE archetype (id UUID NOT NULL, code VARCHAR(255) NOT NULL, configuration JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E1D5BCE377153098 ON archetype (code)');
        $this->addSql('COMMENT ON COLUMN archetype.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE video_room ADD archetype_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN video_room.archetype_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE video_room ADD CONSTRAINT FK_75080C47732C6CC7 FOREIGN KEY (archetype_id) REFERENCES archetype (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_75080C47732C6CC7 ON video_room (archetype_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE video_room DROP CONSTRAINT FK_75080C47732C6CC7');
        $this->addSql('DROP TABLE archetype');
        $this->addSql('DROP INDEX IDX_75080C47732C6CC7');
        $this->addSql('ALTER TABLE video_room DROP archetype_id');
    }
}
