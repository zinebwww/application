<?php

namespace Services;

use InvalidArgumentException;

/**
 * Service interne de conversion multi-devises vers le dirham marocain (MAD).
 */
class CurrencyConverter
{
    private const RATES = [
        'EUR' => 10.00,
        'USD' => 9.50,
        'MAD' => 1.00,
    ];

    /**
     * Convertit un montant vers le MAD selon la devise fournie.
     *
     * @param float|int|string $amount   Montant source.
     * @param string           $currency Devise source (EUR, USD, MAD).
     *
     * @return float Montant converti arrondi à 2 décimales.
     */
    public static function convertToMAD($amount, string $currency): float
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Montant invalide.');
        }

        $rate = self::getRate($currency);

        return round((float) $amount * $rate, 2);
    }

    /**
     * Retourne le taux de conversion pour la devise demandée.
     *
     * @param string $currency Devise source.
     *
     * @return float Taux de conversion.
     */
    public static function getRate(string $currency): float
    {
        $currencyKey = strtoupper(trim($currency));

        if (!isset(self::RATES[$currencyKey])) {
            throw new InvalidArgumentException('Devise inconnue.');
        }

        return self::RATES[$currencyKey];
    }
}

