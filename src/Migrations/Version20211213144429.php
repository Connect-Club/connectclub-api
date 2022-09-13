<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211213144429 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DELETE FROM user_interest WHERE interest_id IN (SELECT id FROM interest WHERE is_old = true)');
        $this->addSql('DELETE FROM event_schedule_interest WHERE interest_id IN (SELECT id FROM interest WHERE is_old = true)');
        $this->addSql('DELETE FROM interest WHERE is_old = true');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
