<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200212111751 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE users ADD created_at BIGINT DEFAULT NULL');
        $this->addSql('UPDATE users SET created_at = '.time());
        $this->addSql('ALTER TABLE users ALTER created_at SET NOT NULL');
        $this->addSql('ALTER TABLE users ADD google_profile_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD google_profile_surname VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD google_profile_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_profile_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_profile_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_profile_surname VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_profile_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users DROP roles');
        $this->addSql('ALTER TABLE users ALTER password SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE users RENAME COLUMN facebook_id TO google_profile_name');
        $this->addSql('ALTER TABLE photo ALTER upload_by_id SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE users ADD roles JSON NOT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users DROP created_at');
        $this->addSql('ALTER TABLE users DROP google_profile_id');
        $this->addSql('ALTER TABLE users DROP google_profile_name');
        $this->addSql('ALTER TABLE users DROP google_profile_surname');
        $this->addSql('ALTER TABLE users DROP google_profile_email');
        $this->addSql('ALTER TABLE users DROP facebook_profile_id');
        $this->addSql('ALTER TABLE users DROP facebook_profile_name');
        $this->addSql('ALTER TABLE users DROP facebook_profile_surname');
        $this->addSql('ALTER TABLE users DROP facebook_profile_email');
        $this->addSql('ALTER TABLE users ALTER password DROP DEFAULT');
        $this->addSql('ALTER TABLE photo ALTER upload_by_id DROP NOT NULL');
    }
}
