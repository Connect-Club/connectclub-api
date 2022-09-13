<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220410081340 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP INDEX user_phone_number');
        $this->addSql('ALTER TABLE users DROP google_profile_name');
        $this->addSql('ALTER TABLE users DROP google_profile_id');
        $this->addSql('ALTER TABLE users DROP google_profile_surname');
        $this->addSql('ALTER TABLE users DROP google_profile_email');
        $this->addSql('ALTER TABLE users DROP facebook_profile_id');
        $this->addSql('ALTER TABLE users DROP facebook_profile_name');
        $this->addSql('ALTER TABLE users DROP facebook_profile_surname');
        $this->addSql('ALTER TABLE users DROP facebook_profile_email');
        $this->addSql('ALTER TABLE users DROP google_profile_picture');
        $this->addSql('ALTER TABLE users DROP google_profile_locale');
        $this->addSql('ALTER TABLE users DROP facebook_profile_picture');
        $this->addSql('ALTER TABLE users DROP apple_profile_id');
        $this->addSql('ALTER TABLE users DROP apple_profile_email');
        $this->addSql('ALTER TABLE users DROP apple_profile_name');
        $this->addSql('ALTER TABLE users DROP apple_profile_surname');
        $this->addSql('ALTER TABLE users DROP company');
        $this->addSql('ALTER TABLE users DROP "position"');
        $this->addSql('ALTER TABLE users DROP phone_number');
        $this->addSql('CREATE INDEX user_phone_number ON users (phone)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX user_phone_number');
        $this->addSql('ALTER TABLE users ADD google_profile_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD google_profile_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD google_profile_surname VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD google_profile_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_profile_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_profile_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_profile_surname VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_profile_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD google_profile_picture TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD google_profile_locale VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_profile_picture TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD apple_profile_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD apple_profile_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD apple_profile_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD apple_profile_surname VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD company VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD "position" VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD phone_number VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX user_phone_number ON users (phone_number)');
    }
}
