<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211210123342 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE language_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE language (id INT NOT NULL, code VARCHAR(4) NOT NULL, name VARCHAR(50) NOT NULL, is_default_interest_for_regions BOOLEAN DEFAULT NULL, sort INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE language ADD automatic_choose_for_region_codes JSON DEFAULT NULL');
        $this->addSql('CREATE TABLE user_language (user_id INT NOT NULL, language_id INT NOT NULL, PRIMARY KEY(user_id, language_id))');
        $this->addSql('CREATE INDEX IDX_345695B5A76ED395 ON user_language (user_id)');
        $this->addSql('CREATE INDEX IDX_345695B582F1BAF4 ON user_language (language_id)');
        $this->addSql('ALTER TABLE user_language ADD CONSTRAINT FK_345695B5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_language ADD CONSTRAINT FK_345695B582F1BAF4 FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE users DROP CONSTRAINT fk_1483a5e9f44d7b10');
        $this->addSql('DROP INDEX idx_1483a5e9f44d7b10');
        $this->addSql('INSERT INTO language (id, code, name, is_default_interest_for_regions, automatic_choose_for_region_codes, sort) SELECT id, language_code, name, is_default_interest_for_regions, automatic_choose_for_region_codes, row FROM interest WHERE language_code IS NOT NULL');
        $this->addSql('INSERT INTO user_language (user_id, language_id) SELECT id, native_language_id FROM users WHERE native_language_id IS NOT NULL');
        $this->addSql('ALTER TABLE users DROP native_language_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE user_language DROP CONSTRAINT FK_345695B582F1BAF4');
        $this->addSql('DROP SEQUENCE language_id_seq CASCADE');
        $this->addSql('DROP TABLE language');
        $this->addSql('DROP TABLE user_language');
        $this->addSql('ALTER TABLE users ADD native_language_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT fk_1483a5e9f44d7b10 FOREIGN KEY (native_language_id) REFERENCES interest (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_1483a5e9f44d7b10 ON users (native_language_id)');
    }
}
