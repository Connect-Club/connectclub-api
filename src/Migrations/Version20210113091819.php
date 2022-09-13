<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210113091819 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');


        $this->addSql('ALTER TABLE chat_participant ADD mute BOOLEAN DEFAULT NULL');
        $this->addSql('UPDATE chat_participant cp SET mute = (SELECT ca.mute FROM chat_settings ca WHERE ca.user_id = cp.user_id AND ca.chat_id = cp.chat_id)');
        $this->addSql('ALTER TABLE chat_participant ALTER mute SET NOT NULL');
        $this->addSql('DROP SEQUENCE chat_settings_id_seq CASCADE');
        $this->addSql('DROP TABLE chat_settings');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE chat_settings_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE chat_settings (id INT NOT NULL, user_id INT DEFAULT NULL, chat_id INT DEFAULT NULL, mute BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_d16777f5a76ed395 ON chat_settings (user_id)');
        $this->addSql('CREATE INDEX idx_d16777f51a9a7125 ON chat_settings (chat_id)');
        $this->addSql('CREATE UNIQUE INDEX user_chat ON chat_settings (chat_id, user_id)');
        $this->addSql('ALTER TABLE chat_settings ADD CONSTRAINT fk_d16777f5a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chat_settings ADD CONSTRAINT fk_d16777f51a9a7125 FOREIGN KEY (chat_id) REFERENCES chat (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chat_participant DROP mute');
    }
}
