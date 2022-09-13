<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220413232633 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE video_room DROP CONSTRAINT fk_75080c47732c6cc7');
        $this->addSql('DROP INDEX idx_75080c47732c6cc7');
        $this->addSql('ALTER TABLE video_room DROP archetype_id');
        $this->addSql('ALTER TABLE video_room DROP join_notification_enabled');
        $this->addSql('ALTER TABLE video_room DROP web_rtc_bot_enabled');
        $this->addSql('ALTER TABLE video_room DROP join_community_notification_enabled');
        $this->addSql('ALTER TABLE video_room DROP custom');
        $this->addSql('ALTER TABLE video_room DROP online_notification_for_admin_only');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE video_room ADD archetype_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE video_room ADD join_notification_enabled BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD web_rtc_bot_enabled BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD join_community_notification_enabled BOOLEAN DEFAULT \'true\' NOT NULL');
        $this->addSql('ALTER TABLE video_room ADD custom JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE video_room ADD online_notification_for_admin_only BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('COMMENT ON COLUMN video_room.archetype_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE video_room ADD CONSTRAINT fk_75080c47732c6cc7 FOREIGN KEY (archetype_id) REFERENCES archetype (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_75080c47732c6cc7 ON video_room (archetype_id)');
    }
}
