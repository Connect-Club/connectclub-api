<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210512154617 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('INSERT INTO phone_contact_number (id, phone_contact_id, phone_number, original_phone)
        SELECT id, id, phone_number, original_phone FROM phone_contact WHERE NOT EXISTS(
            SELECT * FROM phone_contact_number pcn WHERE pcn.phone_contact_id = phone_contact.id AND pcn.phone_number = phone_contact.phone_number
        )');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
