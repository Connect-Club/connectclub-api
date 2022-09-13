<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200925180921 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE photo DROP CONSTRAINT fk_14b784181a9a7125');
        $this->addSql('DROP INDEX idx_14b784181a9a7125');
        $this->addSql('ALTER TABLE chat ADD photo_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE chat ADD CONSTRAINT FK_659DF2AA7E9E4C8C FOREIGN KEY (photo_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_659DF2AA7E9E4C8C ON chat (photo_id)');
        $this->addSql('UPDATE chat c SET photo_id = p.id FROM photo p WHERE p.chat_id = c.id');
        $this->addSql('ALTER TABLE photo DROP chat_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE photo ADD chat_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT fk_14b784181a9a7125 FOREIGN KEY (chat_id) REFERENCES chat (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_14b784181a9a7125 ON photo (chat_id)');
        $this->addSql('ALTER TABLE chat DROP CONSTRAINT FK_659DF2AA7E9E4C8C');
        $this->addSql('DROP INDEX IDX_659DF2AA7E9E4C8C');
        $this->addSql('ALTER TABLE chat DROP photo_id');
    }
}
