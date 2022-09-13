<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200824142528 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE chat_settings_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('DELETE FROM chat_participant a USING chat_participant b
                WHERE 
                    a.id < b.id
                AND 
                    a.user_id = b.user_id AND a.chat_id = b.chat_id');

        $this->addSql('CREATE TABLE chat_settings (id INT NOT NULL, user_id INT DEFAULT NULL, chat_id INT DEFAULT NULL, mute BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D16777F5A76ED395 ON chat_settings (user_id)');
        $this->addSql('CREATE INDEX IDX_D16777F51A9A7125 ON chat_settings (chat_id)');
        $this->addSql('CREATE UNIQUE INDEX user_chat ON chat_settings (chat_id, user_id)');
        $this->addSql('ALTER TABLE chat_settings ADD CONSTRAINT FK_D16777F5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chat_settings ADD CONSTRAINT FK_D16777F51A9A7125 FOREIGN KEY (chat_id) REFERENCES chat (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('INSERT INTO chat_settings (id, chat_id, user_id, mute)
                       SELECT nextval(\'chat_settings_id_seq\') AS id, cp.chat_id AS chat_id, cp.user_id AS user_id, false as mute FROM chat_participant cp
                       WHERE NOT EXISTS (SELECT id FROM chat_settings a WHERE a.chat_id = cp.chat_id AND a.user_id = cp.user_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP SEQUENCE chat_settings_id_seq CASCADE');
        $this->addSql('DROP TABLE chat_settings');
    }
}
