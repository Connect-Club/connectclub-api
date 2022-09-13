<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210512103103 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DELETE FROM phone_contact_number t1 
                       USING phone_contact_number t2 
                       WHERE t1.phone_contact_id = t2.phone_contact_id 
                       AND t1.phone_number = t2.phone_number 
                       AND t1.id != t2.id');

        $this->addSql('DELETE FROM phone_contact t1 
                       USING phone_contact t2 
                       WHERE t1.owner_id = t2.owner_id 
                       AND t1.phone_number = t2.phone_number 
                       AND t1.id != t2.id');

        $this->addSql('CREATE UNIQUE INDEX owner_id_phone_number_unique ON phone_contact (owner_id, phone_number)');
        $this->addSql('CREATE UNIQUE INDEX phone_contact_id_phone_number_unique ON phone_contact_number (phone_contact_id, phone_number)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX owner_id_phone_number_unique');
        $this->addSql('DROP INDEX phone_contact_id_phone_number_unique');
    }
}
