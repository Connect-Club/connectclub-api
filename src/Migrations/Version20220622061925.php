<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220622061925 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE land ALTER x TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE land ALTER x DROP DEFAULT');
        $this->addSql('ALTER TABLE land ALTER y TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE land ALTER y DROP DEFAULT');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE land_number_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('ALTER TABLE land ALTER x TYPE INT');
        $this->addSql('ALTER TABLE land ALTER x DROP DEFAULT');
        $this->addSql('ALTER TABLE land ALTER y TYPE INT');
        $this->addSql('ALTER TABLE land ALTER y DROP DEFAULT');
    }
}
