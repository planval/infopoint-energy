<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205115753 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add examples field to financial support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE pv_financial_support ADD examples LONGTEXT NULL COMMENT '(DC2Type:json)'");
        $this->addSql("UPDATE pv_financial_support SET examples = '[]' WHERE examples IS NULL");
        $this->addSql("ALTER TABLE pv_financial_support MODIFY examples LONGTEXT NOT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pv_financial_support DROP examples');
    }

} 