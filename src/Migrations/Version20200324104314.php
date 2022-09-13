<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200324104314 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE country (id INT NOT NULL, continent_code VARCHAR(255) NOT NULL, continent_name VARCHAR(255) NOT NULL, iso_code VARCHAR(255) NOT NULL, is_in_european_union BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE city (id BIGINT NOT NULL, country_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, subdivision_first_iso_code VARCHAR(255) NOT NULL, subdivision_first_name VARCHAR(255) NOT NULL, subdivision_second_iso_code VARCHAR(255) NOT NULL, subdivision_second_name VARCHAR(255) NOT NULL, metro_code VARCHAR(255) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, accuracy_radius INT NOT NULL, time_zone VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2D5B0234F92F3E70 ON city (country_id)');
        $this->addSql('ALTER TABLE city ADD CONSTRAINT FK_2D5B0234F92F3E70 FOREIGN KEY (country_id) REFERENCES country (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE users ADD city_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE users DROP geo_name_country_id');
        $this->addSql('ALTER TABLE users DROP geo_name_city_id');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E98BAC62AF FOREIGN KEY (city_id) REFERENCES city (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_1483A5E98BAC62AF ON users (city_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE city DROP CONSTRAINT FK_2D5B0234F92F3E70');
        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_1483A5E98BAC62AF');
        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE city');
        $this->addSql('DROP INDEX IDX_1483A5E98BAC62AF');
        $this->addSql('ALTER TABLE users ADD geo_name_city_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE users RENAME COLUMN city_id TO geo_name_country_id');
    }
}
