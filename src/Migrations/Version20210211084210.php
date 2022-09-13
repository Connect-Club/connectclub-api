<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210211084210 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE phone_contact (id UUID NOT NULL, owner_id INT DEFAULT NULL, phone_number VARCHAR(35) NOT NULL, full_name VARCHAR(255) NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DC206A0A7E3C61F9 ON phone_contact (owner_id)');
        $this->addSql('COMMENT ON COLUMN phone_contact.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN phone_contact.phone_number IS \'(DC2Type:phone_number)\'');
        $this->addSql('ALTER TABLE phone_contact ADD CONSTRAINT FK_DC206A0A7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE phone_contact');
    }
}
