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
            (nextval('interest_id_seq'), null, '🎶 Music', null, false, 6, false),
            (nextval('interest_id_seq'), null, '👥 Networking', null, false, 7, false),
            (nextval('interest_id_seq'), null, '🔗 Blockchain', null, false, 8, false),
            (nextval('interest_id_seq'), null, '🔐 Crypto', null, false, 9, false),
            (nextval('interest_id_seq'), null, '💻 Web 3.0', null, false, 10, false),
            (nextval('interest_id_seq'), null, '📸 Photography', null, false, 11, false),
            (nextval('interest_id_seq'), null, '🎨 Design', null, false, 12, false),
            (nextval('interest_id_seq'), null, '📈 Marketing', null, false, 13, false),
            (nextval('interest_id_seq'), null, '🧊Chill Vibes', null, false, 14, false),
            (nextval('interest_id_seq'), null, '🏦 Digital economy', null, false, 15, false),
            (nextval('interest_id_seq'), null, '🤝 Collaboration', null, false, 16, false),
            (nextval('interest_id_seq'), null, '✈️ Travelling', null, false, 17, false),
            (nextval('interest_id_seq'), null, '📚 Psychology', null, false, 18, false),
            (nextval('interest_id_seq'), null, '🚀 Startups', null, false, 19, false),
            (nextval('interest_id_seq'), null, '🧠 AI', null, false, 20, false),
            (nextval('interest_id_seq'), null, '📱 Technology', null, false, 21, false),
            (nextval('interest_id_seq'), null, '💃 Entertainment', null, false, 22, false),
            (nextval('interest_id_seq'), null, '🎙Storytelling', null, false, 23, false),
            (nextval('interest_id_seq'), null, '💼 Business', null, false, 24, false),
            (nextval('interest_id_seq'), null, '⚙️️ DeFi', null, false, 25, false),
            (nextval('interest_id_seq'), null, '💎 Ethereum', null, false, 26, false),
            (nextval('interest_id_seq'), null, '🌏 DAO', null, false, 27, false),
            (nextval('interest_id_seq'), null, '💰Investments', null, false, 28, false),
            (nextval('interest_id_seq'), null, '🌟Community', null, false, 29, false),
            (nextval('interest_id_seq'), null, '👨‍💻Coworking', null, false, 30, false)
        SQL);
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql(<<<SQL
            DELETE FROM interest WHERE name in (
                '🎶 Music',
                '👥 Networking',
                '🔗 Blockchain',
                '🔐 Crypto',
                '💻 Web 3.0',
                '📸 Photography',
                '🎨 Design',
                '📈 Marketing',
                '🧊Chill Vibes',
                '🏦 Digital economy',
                '🤝 Collaboration',
                '✈️ Travelling',
                '📚 Psychology',
                '🚀 Startups',
                '🧠 AI',
                '📱 Technology',
                '💃 Entertainment',
                '🎙Storytelling',
                '💼 Business',
                '⚙️️ DeFi',
                '💎 Ethereum',
                '🌏 DAO',
                '💰Investments',
                '🌟Community',
                '👨‍💻Coworking'
            )       
        SQL);
    }
}


























