<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220113141920 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sms_verification ADD ip_country_iso_code VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sms_verification ADD phone_country_iso_code VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sms_verification RENAME COLUMN vonage_request_id TO remote_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE sms_verification DROP ip_country_iso_code');
        $this->addSql('ALTER TABLE sms_verification DROP phone_country_iso_code');
        $this->addSql('ALTER TABLE sms_verification RENAME COLUMN remote_id TO vonage_request_id');
    }
}
