<?php

namespace App\Models;

use App\Core\Database;

/** Colunas do kanban de Leads. As 4 padrao (novo/contatado/convertido/descartado) vem seedadas
 * e sao compartilhadas por toda a equipe -- qualquer STAFF pode acrescentar novas (ver
 * LeadController::addStage()), tambem compartilhadas, ja que o mesmo lead e visto por varias
 * pessoas dependendo do escopo (nao faz sentido cada usuario ter um status diferente pro mesmo
 * lead). */
class LeadStage
{
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT * FROM lead_stages ORDER BY position ASC, id ASC')
            ->fetchAll();
    }

    public static function allSlugs(): array
    {
        return array_column(self::all(), 'slug');
    }

    public static function create(string $name): array
    {
        $slug = self::uniqueSlug(self::slugify($name));
        $nextPosition = (int) Database::connection()->query('SELECT COALESCE(MAX(position), 0) + 1 FROM lead_stages')->fetchColumn();

        $stmt = Database::connection()->prepare(
            'INSERT INTO lead_stages (slug, name, position) VALUES (:slug, :name, :position)'
        );
        $stmt->execute(['slug' => $slug, 'name' => $name, 'position' => $nextPosition]);

        return ['id' => (int) Database::connection()->lastInsertId(), 'slug' => $slug, 'name' => $name, 'position' => $nextPosition];
    }

    private static function slugify(string $name): string
    {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;
        $slug = strtolower(trim($transliterated));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        return $slug !== '' ? substr($slug, 0, 32) : 'coluna';
    }

    private static function uniqueSlug(string $base): string
    {
        $existing = self::allSlugs();
        if (!in_array($base, $existing, true)) {
            return $base;
        }
        for ($i = 2; $i < 100; $i++) {
            $candidate = $base . '_' . $i;
            if (!in_array($candidate, $existing, true)) {
                return $candidate;
            }
        }
        return $base . '_' . uniqid();
    }
}
