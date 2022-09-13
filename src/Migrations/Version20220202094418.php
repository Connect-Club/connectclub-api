<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220202094418 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql(
            <<<SQL
            UPDATE users SET username = username || id::text WHERE id IN (
                SELECT u1.id FROM users u1
                JOIN users u2 ON u2.id < u1.id AND lower(u2.username) = lower(u1.username)
                AND u1.username IS NOT NULL AND u2.username IS NOT NULL
            )
            SQL
        );
        $this->addSql('UPDATE users SET username = lower(username) WHERE username IS NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
