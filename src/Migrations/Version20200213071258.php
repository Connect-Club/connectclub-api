<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200213071258 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE users ADD google_profile_picture VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD google_profile_locale VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD google_profile_verified_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_profile_picture VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE users DROP google_profile_picture');
        $this->addSql('ALTER TABLE users DROP google_profile_locale');
        $this->addSql('ALTER TABLE users DROP google_profile_verified_email');
        $this->addSql('ALTER TABLE users DROP facebook_profile_picture');
    }
}
