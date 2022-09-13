<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210506090058 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE phone_contact_number (id UUID NOT NULL, phone_contact_id UUID DEFAULT NULL, phone_number VARCHAR(35) NOT NULL, original_phone VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CFFFA280CFAAE3DF ON phone_contact_number (phone_contact_id)');
        $this->addSql('DROP INDEX phone_number');
        $this->addSql('CREATE INDEX phone_number ON phone_contact_number (phone_number)');
        $this->addSql('COMMENT ON COLUMN phone_contact_number.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN phone_contact_number.phone_contact_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN phone_contact_number.phone_number IS \'(DC2Type:phone_number)\'');
        $this->addSql('ALTER TABLE phone_contact_number ADD CONSTRAINT FK_CFFFA280CFAAE3DF FOREIGN KEY (phone_contact_id) REFERENCES phone_contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('INSERT INTO phone_contact_number (id, phone_contact_id, phone_number, original_phone)
                       SELECT id, id, phone_number, original_phone FROM phone_contact');
        $this->addSql('CREATE INDEX phone_contact_phone_number ON phone_contact (phone_number)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE phone_contact_number');
        $this->addSql('COMMENT ON COLUMN phone_contact.phone_number IS \'(DC2Type:phone_number)\'');
        $this->addSql('DROP INDEX IF EXISTS phone_number');
        $this->addSql('CREATE INDEX phone_number ON phone_contact (phone_number)');
    }
}
