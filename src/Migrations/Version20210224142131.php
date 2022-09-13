<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210224142131 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE phone_contact ADD original_phone VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ALTER phone TYPE VARCHAR(35)');
        $this->addSql('ALTER TABLE users ALTER phone SET DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN users.phone IS \'(DC2Type:phone_number)\'');
        $this->addSql('UPDATE users SET phone = NULL');
        $this->addSql('UPDATE phone_contact SET original_phone = phone_number');
        $this->addSql('ALTER TABLE phone_contact ALTER original_phone SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE users ALTER phone TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE users ALTER phone DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN users.phone IS NULL');
        $this->addSql('ALTER TABLE phone_contact DROP original_phone');
    }
}
