<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200818103015 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('INSERT INTO chat_access (id, chat_id, user_id)
                       SELECT nextval(\'chat_access_id_seq\') AS id, cp.chat_id AS chat_id, cp.user_id AS user_id FROM chat_participant cp
                       WHERE NOT EXISTS (SELECT id FROM chat_access a WHERE a.chat_id = cp.chat_id AND a.user_id = cp.user_id)');
    }

    public function down(Schema $schema) : void
    {
    }
}
