<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211118132055 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE user_industry (user_id INT NOT NULL, industry_id UUID NOT NULL, PRIMARY KEY(user_id, industry_id))');
        $this->addSql('CREATE INDEX IDX_2D7788A0A76ED395 ON user_industry (user_id)');
        $this->addSql('CREATE INDEX IDX_2D7788A02B19A734 ON user_industry (industry_id)');
        $this->addSql('COMMENT ON COLUMN user_industry.industry_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE user_goal (user_id INT NOT NULL, goal_id UUID NOT NULL, PRIMARY KEY(user_id, goal_id))');
        $this->addSql('CREATE INDEX IDX_865DA7E7A76ED395 ON user_goal (user_id)');
        $this->addSql('CREATE INDEX IDX_865DA7E7667D1AFE ON user_goal (goal_id)');
        $this->addSql('COMMENT ON COLUMN user_goal.goal_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE user_skill (user_id INT NOT NULL, skill_id UUID NOT NULL, PRIMARY KEY(user_id, skill_id))');
        $this->addSql('CREATE INDEX IDX_BCFF1F2FA76ED395 ON user_skill (user_id)');
        $this->addSql('CREATE INDEX IDX_BCFF1F2F5585C142 ON user_skill (skill_id)');
        $this->addSql('COMMENT ON COLUMN user_skill.skill_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user_industry ADD CONSTRAINT FK_2D7788A0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_industry ADD CONSTRAINT FK_2D7788A02B19A734 FOREIGN KEY (industry_id) REFERENCES industry (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_goal ADD CONSTRAINT FK_865DA7E7A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_goal ADD CONSTRAINT FK_865DA7E7667D1AFE FOREIGN KEY (goal_id) REFERENCES goal (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_skill ADD CONSTRAINT FK_BCFF1F2FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_skill ADD CONSTRAINT FK_BCFF1F2F5585C142 FOREIGN KEY (skill_id) REFERENCES skill (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE user_industry');
        $this->addSql('DROP TABLE user_goal');
        $this->addSql('DROP TABLE user_skill');
    }
}
