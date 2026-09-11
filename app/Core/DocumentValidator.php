<?php

namespace App\Core;

/**
 * Validacao de digito verificador de CPF/CNPJ -- o ClickSign recusa (422 "documentation invalido")
 * qualquer CPF/CNPJ que falhe nesse calculo, mesmo com 11/14 digitos. Sem isso, o Licenciado so
 * descobre o erro de digitacao depois de enviar todo o formulario de onboarding (documentos
 * inclusive), com uma mensagem generica que nao aponta o campo.
 */
class DocumentValidator
{
    public static function isValidCpf(string $digits): bool
    {
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        for ($pos = 9; $pos <= 10; $pos++) {
            $sum = 0;
            for ($i = 0; $i < $pos; $i++) {
                $sum += (int) $digits[$i] * (($pos + 1) - $i);
            }
            $remainder = $sum % 11;
            $check = $remainder < 2 ? 0 : 11 - $remainder;
            if ((int) $digits[$pos] !== $check) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCnpj(string $digits): bool
    {
        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([$weights1, $weights2] as $weights) {
            $pos = count($weights);
            $sum = 0;
            for ($i = 0; $i < $pos; $i++) {
                $sum += (int) $digits[$i] * $weights[$i];
            }
            $check = $sum % 11;
            $check = $check < 2 ? 0 : 11 - $check;
            if ((int) $digits[$pos] !== $check) {
                return false;
            }
        }

        return true;
    }
}
