<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211202130901 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE club ADD slug VARCHAR(255) DEFAULT \'\'');
        $this->addSql('UPDATE club SET slug = trim(BOTH \'-\' FROM regexp_replace(lower(trim(case when length(title) > 0 then title else id::text end)), \'[^A-Za-zА-ЯёЁ0-9-]+\', \'-\', \'gi\'))');
        $this->addSql('ALTER TABLE club ALTER slug DROP DEFAULT');
        $this->addSql('ALTER TABLE club ALTER slug SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B8EE38722B36786B ON club (title)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B8EE3872989D9B62 ON club (slug)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP INDEX UNIQ_B8EE38722B36786B');
        $this->addSql('DROP INDEX UNIQ_B8EE3872989D9B62');
        $this->addSql('ALTER TABLE club DROP slug');
    }
}
