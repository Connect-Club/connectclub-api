<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210205172941 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_log ADD entity_id_tmp VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE event_log ADD event_code_tmp VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE event_log SET event_code_tmp = event_code, entity_id_tmp = entity_id');
        $this->addSql('UPDATE event_log SET entity_id = event_code_tmp, event_code = entity_id_tmp');
        $this->addSql('ALTER TABLE event_log DROP entity_id_tmp');
        $this->addSql('ALTER TABLE event_log DROP event_code_tmp');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
