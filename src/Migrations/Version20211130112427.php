<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211130112427 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<SQL
            SELECT setval('interest_id_seq', (SELECT MAX(id) FROM interest))
        SQL);
        $this->addSql(<<<SQL
            INSERT INTO interest (id, group_id, name, automatic_choose_for_region_codes, is_default_interest_for_regions, row, is_old)
            VALUES (nextval('interest_id_seq'), null, '💎 NFT', null, false, 1, false),
            (nextval('interest_id_seq'), null, '🦄 Metaverse', null, false, 2, false),
            (nextval('interest_id_seq'), null, '🎓 Education', null, false, 3, false),
            (nextval('interest_id_seq'), null, '🎨 Art', null, false, 4, false),
            (nextval('interest_id_seq'), null, '🎯 Product', null, false, 5, false)
        SQL);
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
    }
}
