<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201008095958 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE video_meeting SET end_time = extract(epoch from now()) WHERE extract(epoch from now()) - start_time > 14400 AND end_time IS NULL');
        $this->addSql('UPDATE video_meeting_participant SET end_time = extract(epoch from now()) WHERE extract(epoch from now()) - start_time > 14400 AND end_time IS NULL;');
    }

    public function down(Schema $schema) : void
    {
    }
}
