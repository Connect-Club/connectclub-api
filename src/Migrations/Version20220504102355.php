<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220504102355 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add new interests';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql(<<<SQL
            SELECT setval('interest_id_seq', (SELECT MAX(id) FROM interest))
        SQL);
        $this->addSql(<<<SQL
            INSERT INTO interest (id, group_id, name, automatic_choose_for_region_codes, is_default_interest_for_regions, row, is_old)
            VALUES 
            (nextval('interest_id_seq'), null, 'πΆ Music', null, false, 6, false),
            (nextval('interest_id_seq'), null, 'π₯ Networking', null, false, 7, false),
            (nextval('interest_id_seq'), null, 'π Blockchain', null, false, 8, false),
            (nextval('interest_id_seq'), null, 'π Crypto', null, false, 9, false),
            (nextval('interest_id_seq'), null, 'π» Web 3.0', null, false, 10, false),
            (nextval('interest_id_seq'), null, 'πΈ Photography', null, false, 11, false),
            (nextval('interest_id_seq'), null, 'π¨ Design', null, false, 12, false),
            (nextval('interest_id_seq'), null, 'π Marketing', null, false, 13, false),
            (nextval('interest_id_seq'), null, 'π§Chill Vibes', null, false, 14, false),
            (nextval('interest_id_seq'), null, 'π¦ Digital economy', null, false, 15, false),
            (nextval('interest_id_seq'), null, 'π€ Collaboration', null, false, 16, false),
            (nextval('interest_id_seq'), null, 'βοΈ Travelling', null, false, 17, false),
            (nextval('interest_id_seq'), null, 'π Psychology', null, false, 18, false),
            (nextval('interest_id_seq'), null, 'π Startups', null, false, 19, false),
            (nextval('interest_id_seq'), null, 'π§  AI', null, false, 20, false),
            (nextval('interest_id_seq'), null, 'π± Technology', null, false, 21, false),
            (nextval('interest_id_seq'), null, 'π Entertainment', null, false, 22, false),
            (nextval('interest_id_seq'), null, 'πStorytelling', null, false, 23, false),
            (nextval('interest_id_seq'), null, 'πΌ Business', null, false, 24, false),
            (nextval('interest_id_seq'), null, 'βοΈοΈ DeFi', null, false, 25, false),
            (nextval('interest_id_seq'), null, 'π Ethereum', null, false, 26, false),
            (nextval('interest_id_seq'), null, 'π DAO', null, false, 27, false),
            (nextval('interest_id_seq'), null, 'π°Investments', null, false, 28, false),
            (nextval('interest_id_seq'), null, 'πCommunity', null, false, 29, false),
            (nextval('interest_id_seq'), null, 'π¨βπ»Coworking', null, false, 30, false)
        SQL);
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql(<<<SQL
            DELETE FROM interest WHERE name in (
                'πΆ Music',
                'π₯ Networking',
                'π Blockchain',
                'π Crypto',
                'π» Web 3.0',
                'πΈ Photography',
                'π¨ Design',
                'π Marketing',
                'π§Chill Vibes',
                'π¦ Digital economy',
                'π€ Collaboration',
                'βοΈ Travelling',
                'π Psychology',
                'π Startups',
                'π§  AI',
                'π± Technology',
                'π Entertainment',
                'πStorytelling',
                'πΌ Business',
                'βοΈοΈ DeFi',
                'π Ethereum',
                'π DAO',
                'π°Investments',
                'πCommunity',
                'π¨βπ»Coworking'
            )       
        SQL);
    }
}


























