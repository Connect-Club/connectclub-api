<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210217111153 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE sms_verification_code');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE sms_verification_code (id UUID NOT NULL, phone_number VARCHAR(35) NOT NULL, code VARCHAR(4) NOT NULL, used_at BOOLEAN DEFAULT NULL, expires_at BIGINT NOT NULL, created_at BIGINT NOT NULL, send_at BOOLEAN DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN sms_verification_code.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN sms_verification_code.phone_number IS \'(DC2Type:phone_number)\'');
    }
}
