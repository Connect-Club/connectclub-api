<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200917094958 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE networking_meeting_user RENAME TO networking_meeting_user_tmp');
        $this->addSql('DROP INDEX IF EXISTS IDX_31FC841AD83CFD5C');
        $this->addSql('DROP INDEX IF EXISTS IDX_31FC841AA76ED395');
        $this->addSql('DROP INDEX IF EXISTS FK_31FC841AD83CFD5C');
        $this->addSql('DROP INDEX IF EXISTS FK_31FC841AA76ED395');
        $this->addSql('DROP SEQUENCE IF EXISTS networking_meeting_user_id_seq CASCADE');

        $this->addSql('CREATE SEQUENCE networking_meeting_user_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE networking_meeting_user (id INT NOT NULL, networking_meeting_id INT DEFAULT NULL, user_id INT DEFAULT NULL, subscribe BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_31FC841AD83CFD5C ON networking_meeting_user (networking_meeting_id)');
        $this->addSql('CREATE INDEX IDX_31FC841AA76ED395 ON networking_meeting_user (user_id)');
        $this->addSql('ALTER TABLE networking_meeting_user ADD CONSTRAINT FK_31FC841AD83CFD5C FOREIGN KEY (networking_meeting_id) REFERENCES networking_meeting (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting_user ADD CONSTRAINT FK_31FC841AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('INSERT INTO networking_meeting_user (id, user_id, networking_meeting_id, subscribe) SELECT nextval(\'networking_meeting_user_id_seq\') as id, user_id as user_id, networking_meeting_id as networking_meeting_id, true as subscribe FROM networking_meeting_user_tmp');
        $this->addSql('DROP TABLE networking_meeting_user_tmp');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');
        $this->addSql('DROP SEQUENCE networking_meeting_user_id_seq CASCADE');
        $this->addSql('CREATE INDEX idx_31fc841aa76ed395 ON networking_meeting_user_tmp (user_id)');
        $this->addSql('CREATE INDEX idx_31fc841ad83cfd5c ON networking_meeting_user_tmp (networking_meeting_id)');
        $this->addSql('ALTER TABLE networking_meeting_user_tmp ADD CONSTRAINT fk_31fc841ad83cfd5c FOREIGN KEY (networking_meeting_id) REFERENCES networking_meeting (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting_user_tmp ADD CONSTRAINT fk_31fc841aa76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE networking_meeting_user');
    }
}
