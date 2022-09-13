<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200923082944 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE interest_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE interest_group_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE user_interest (user_id INT NOT NULL, interest_id INT NOT NULL, PRIMARY KEY(user_id, interest_id))');
        $this->addSql('CREATE INDEX IDX_8CB3FE67A76ED395 ON user_interest (user_id)');
        $this->addSql('CREATE INDEX IDX_8CB3FE675A95FF89 ON user_interest (interest_id)');
        $this->addSql('CREATE TABLE interest (id INT NOT NULL, group_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6C3E1A67FE54D947 ON interest (group_id)');
        $this->addSql('CREATE TABLE interest_group (id INT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE user_interest ADD CONSTRAINT FK_8CB3FE67A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_interest ADD CONSTRAINT FK_8CB3FE675A95FF89 FOREIGN KEY (interest_id) REFERENCES interest (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE interest ADD CONSTRAINT FK_6C3E1A67FE54D947 FOREIGN KEY (group_id) REFERENCES interest_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE user_interest DROP CONSTRAINT FK_8CB3FE675A95FF89');
        $this->addSql('ALTER TABLE interest DROP CONSTRAINT FK_6C3E1A67FE54D947');
        $this->addSql('DROP SEQUENCE interest_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE interest_group_id_seq CASCADE');
        $this->addSql('DROP TABLE user_interest');
        $this->addSql('DROP TABLE interest');
        $this->addSql('DROP TABLE interest_group');
    }
}
