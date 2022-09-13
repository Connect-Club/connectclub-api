<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201014142433 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql(
            'INSERT INTO client (random_id, redirect_uris, secret, allowed_grant_types, scopes) 
            VALUES (\'3u3bpqxw736s4kgo0gsco4kw48gos800gscg4s4w8w80oogc8c\', \'a:0:{}\', \'6cja0geitwsok4gckw0cc0c04sc0sgwgo8kggcoc08wocsw8wg\', \'a:6:{i:0;s:5:"token";i:1;s:18:"client_credentials";i:2;s:8:"password";i:3;s:13:"refresh_token";i:4;s:24:"https://connect.club/sms";i:5;s:29:"https://connect.club/metamask";}\', \'["android"]\')'
        );
        $this->addSql(
            'INSERT INTO client (random_id, redirect_uris, secret, allowed_grant_types, scopes) 
            VALUES (\'2jnsvg452lt4g7yk685htl5mv2bp0j3cmpfj2bdjs7jxbtee9x\', \'a:0:{}\', \'u688nrwvlp85oqvswdn52xdaii8cig29khuv7a4c46dsrrjb9b\', \'a:6:{i:0;s:5:"token";i:1;s:18:"client_credentials";i:2;s:8:"password";i:3;s:13:"refresh_token";i:4;s:24:"https://connect.club/sms";i:5;s:29:"https://connect.club/metamask";}\', \'["ios"]\')'
        );
        $this->addSql(
            'INSERT INTO client (random_id, redirect_uris, secret, allowed_grant_types, scopes) 
            VALUES (\'w75581169072706329345647123725564517776625301496592\', \'a:0:{}\', \'q29537214568152841289037313417989539943824460713969\', \'a:6:{i:0;s:5:"token";i:1;s:18:"client_credentials";i:2;s:8:"password";i:3;s:13:"refresh_token";i:4;s:24:"https://connect.club/sms";i:5;s:29:"https://connect.club/metamask";}\', \'["desktop"]\')'
        );
    }

    public function down(Schema $schema) : void
    {
    }
}
