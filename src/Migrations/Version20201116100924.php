<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201116100924 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('
            INSERT INTO community_interest (community_id, interest_id)
                SELECT DISTINCT c.id, ci.interest_id FROM community c, community_interest ci
                JOIN community c2 on c2.id = ci.community_id
            WHERE c.is_public = true AND c2.description = c.description 
            AND NOT EXISTS(SELECT * FROM community_interest ci2 WHERE ci2.community_id = c.id)
        ');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
