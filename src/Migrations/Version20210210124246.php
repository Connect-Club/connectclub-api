<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210210124246 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sms_verification_code (id UUID NOT NULL, phone_number VARCHAR(35) NOT NULL, code VARCHAR(4) NOT NULL, used_at BOOLEAN DEFAULT NULL, expires_at BIGINT NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN sms_verification_code.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sms_verification_code.phone_number IS \'(DC2Type:phone_number)\'');
        $this->addSql('CREATE TABLE invite (id UUID NOT NULL, author_id INT DEFAULT NULL, registered_user_id INT DEFAULT NULL, phone_number VARCHAR(35) NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C7E210D7F675F31B ON invite (author_id)');
        $this->addSql('CREATE INDEX IDX_C7E210D7A6A12EC1 ON invite (registered_user_id)');
        $this->addSql('COMMENT ON COLUMN invite.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN invite.phone_number IS \'(DC2Type:phone_number)\'');
        $this->addSql('ALTER TABLE invite ADD CONSTRAINT FK_C7E210D7F675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invite ADD CONSTRAINT FK_C7E210D7A6A12EC1 FOREIGN KEY (registered_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE sms_verification_code');
        $this->addSql('DROP TABLE invite');
    }
}
