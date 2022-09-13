<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210719215605 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('INSERT INTO user_interest (user_id, interest_id)
        SELECT u.id AS user_id, i.id AS interest_id FROM users u
        JOIN country c on u.country_id = c.id
        JOIN interest i ON \'"\' || c.iso_code || \'"\' = ANY (ARRAY(SELECT json_array_elements(i.automatic_choose_for_region_codes))::text[])
        ON CONFLICT DO NOTHING;');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');
    }
}
