<?php

namespace App\Models;

use App\Core\Database;

/** Depoimentos reais de clientes -- Admin/Gerente cadastra, todo STAFF ve e manda por WhatsApp
 *  como prova social durante a venda. Comeca vazio de proposito -- nunca inventar depoimento. */
class Testimonial
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM testimonials ORDER BY id DESC')->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO testimonials (client_name, city, vehicle, testimonial_text) VALUES (:name, :city, :vehicle, :text)'
        );
        $stmt->execute([
            'name' => $data['client_name'],
            'city' => $data['city'] ?: null,
            'vehicle' => $data['vehicle'] ?: null,
            'text' => $data['testimonial_text'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM testimonials WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
