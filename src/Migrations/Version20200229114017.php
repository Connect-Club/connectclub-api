<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200229114017 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE users ADD apple_profile_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD apple_profile_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD apple_profile_email_verified BOOLEAN DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9772F153B ON users (apple_profile_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX UNIQ_1483A5E9772F153B');
        $this->addSql('ALTER TABLE users DROP apple_profile_id');
        $this->addSql('ALTER TABLE users DROP apple_profile_email');
        $this->addSql('ALTER TABLE users DROP apple_profile_email_verified');
    }
}
