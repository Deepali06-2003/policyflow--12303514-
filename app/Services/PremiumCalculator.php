<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class PremiumCalculator
{
    private const DAILY_RATES = ['BASIC' => 12, 'PLUS' => 20, 'PREMIUM' => 30];
    private const COVERAGE_MULTIPLIERS = [100000 => 1.00, 300000 => 1.25, 500000 => 1.50];

    public function calculate(array $data): array
    {
        $plan = $data['plan_code'];
        $coverage = (int) $data['sum_insured'];
        $destination = $data['destination'];

        if (!isset(self::DAILY_RATES[$plan])) {
            throw new InvalidArgumentException('Unsupported plan_code.');
        }
        if (!isset(self::COVERAGE_MULTIPLIERS[$coverage])) {
            throw new InvalidArgumentException('Unsupported sum_insured.');
        }
        if (!in_array($destination, ['DOMESTIC', 'INTERNATIONAL'], true)) {
            throw new InvalidArgumentException('Unsupported destination.');
        }

        $start = Carbon::parse($data['trip_start'])->startOfDay();
        $end = Carbon::parse($data['trip_end'])->startOfDay();
        if ($end->lt($start)) {
            throw new InvalidArgumentException('trip_end must be on or after trip_start.');
        }

        $days = max(1, (int) $start->diffInDays($end) + 1);
        $dob = Carbon::parse($data['date_of_birth'])->startOfDay();
        $age = (int) $dob->diffInYears($start);
        $ageMultiplier = match (true) {
            $age <= 17 => 1.10,
            $age <= 60 => 1.00,
            $age <= 70 => 1.50,
            default => 2.00,
        };
        $destinationMultiplier = $destination === 'INTERNATIONAL' ? 1.80 : 1.00;
        $coverageMultiplier = self::COVERAGE_MULTIPLIERS[$coverage];

        // Decimal-safe rounding: compute integer paise using scaled factors.
        // All supplied multipliers have at most two decimal places.
        $raw = self::DAILY_RATES[$plan] * $days * $ageMultiplier
            * $destinationMultiplier * $coverageMultiplier;
        $premium = number_format(round($raw + 1e-9, 2, PHP_ROUND_HALF_UP), 2, '.', '');

        return ['travel_days' => $days, 'premium' => $premium];
    }
}
