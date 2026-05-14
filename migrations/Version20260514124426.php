<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260514124426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE channels (id BLOB NOT NULL, position INTEGER NOT NULL, name VARCHAR(255) NOT NULL, url CLOB NOT NULL, tvg_id VARCHAR(255) DEFAULT NULL, tvg_name VARCHAR(255) DEFAULT NULL, tvg_logo CLOB DEFAULT NULL, enabled BOOLEAN NOT NULL, created_at DATETIME NOT NULL, playlist_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_F314E2B66BBD148 FOREIGN KEY (playlist_id) REFERENCES playlists (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_F314E2B66BBD148 ON channels (playlist_id)');
        $this->addSql('CREATE TABLE playlists (id BLOB NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_5E06116FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5E06116F989D9B62 ON playlists (slug)');
        $this->addSql('CREATE INDEX IDX_5E06116FA76ED395 ON playlists (user_id)');
        $this->addSql('CREATE TABLE request_logs (id BLOB NOT NULL, requested_at DATETIME NOT NULL, ip_address VARCHAR(45) NOT NULL, user_agent CLOB DEFAULT NULL, headers CLOB NOT NULL, playlist_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_8F28E1A66BBD148 FOREIGN KEY (playlist_id) REFERENCES playlists (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8F28E1A66BBD148 ON request_logs (playlist_id)');
        $this->addSql('CREATE TABLE users (id BLOB NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE channels');
        $this->addSql('DROP TABLE playlists');
        $this->addSql('DROP TABLE request_logs');
        $this->addSql('DROP TABLE users');
    }
}
