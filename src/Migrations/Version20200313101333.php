<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200313101333 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE users ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD surname VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE users SET name = facebook_profile_name WHERE facebook_profile_name IS NOT NULL');
        $this->addSql('UPDATE users SET name = google_profile_name WHERE google_profile_name IS NOT NULL AND name IS NOT NULL');
        $this->addSql('UPDATE users SET surname = facebook_profile_surname WHERE facebook_profile_surname IS NOT NULL');
        $this->addSql('UPDATE users SET surname = google_profile_surname WHERE google_profile_surname IS NOT NULL AND surname IS NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE users DROP name');
        $this->addSql('ALTER TABLE users DROP surname');
    }
}
