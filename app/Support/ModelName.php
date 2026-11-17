<?php

namespace App\Support;

/**
 * A model's common name ("Logistic regression", "Ordinary least squares
 * regression") from its type, family, link, and exponentiate label. Ported from
 * framework-embed's renderer (spec/model-names.json) so the reader page and
 * embeds name models the same way. Null for an unknown combination.
 */
class ModelName
{
    public static function for(array $model): ?string
    {
        $lower = fn (mixed $value): ?string => self::text($value) === null ? null : strtolower($value);
        $type = $lower($model['type'] ?? null);
        $family = $lower($model['family'] ?? null);
        $link = $lower($model['link'] ?? null);
        $withLink = fn (string $name, string $implied): string => $link === null || $link === $implied ? $name : $name.' ('.$link.' link)';
        if ($type === 'linear') {
            return 'Ordinary least squares regression';
        }
        if ($type === 'glm') {
            if ($family === 'gaussian' && ($link === null || $link === 'identity')) {
                return 'Ordinary least squares regression';
            }
            if ($family === 'binomial') {
                return match (true) {
                    $link === null || $link === 'logit' => 'Logistic regression',
                    $link === 'probit' => 'Probit regression',
                    $link === 'cloglog' => 'Complementary log-log regression',
                    default => 'Binomial regression ('.$link.' link)',
                };
            }
            if ($family === 'poisson') {
                return $withLink('Poisson regression', 'log');
            }
            if ($family === 'negative binomial') {
                return $withLink('Negative binomial regression', 'log');
            }
            if ($family === 'gamma') {
                return $link === null ? 'Gamma regression' : 'Gamma regression ('.$link.' link)';
            }
            if ($family !== null && str_starts_with($family, 'quasi')) {
                $rest = preg_replace('/^[- ]+/', '', substr($family, 5));

                return $rest === '' ? 'Quasi-likelihood regression' : 'Quasi-'.($rest === 'poisson' ? 'Poisson' : $rest).' regression';
            }

            return null;
        }
        if ($type === 'mixed') {
            if ($family === null || $family === 'gaussian') {
                return $withLink('Linear mixed model', 'identity');
            }
            if ($family === 'binomial') {
                return match (true) {
                    $link === null || $link === 'logit' => 'Logistic mixed model',
                    $link === 'probit' => 'Probit mixed model',
                    default => 'Binomial mixed model ('.$link.' link)',
                };
            }
            if ($family === 'poisson') {
                return $withLink('Poisson mixed model', 'log');
            }
            if ($family === 'negative binomial') {
                return $withLink('Negative binomial mixed model', 'log');
            }
            if ($family === 'gamma') {
                return $link === null ? 'Gamma mixed model' : 'Gamma mixed model ('.$link.' link)';
            }

            return null;
        }
        if ($type === 'survival') {
            return self::text($model['exponentiate'] ?? null) === 'Hazard ratio' ? 'Cox proportional hazards model' : 'Parametric survival model';
        }
        if ($type === 'ordinal') {
            return match (true) {
                $link === null || $link === 'logit' => 'Ordinal logistic regression',
                $link === 'probit' => 'Ordinal probit regression',
                default => 'Ordinal regression ('.$link.' link)',
            };
        }
        if ($type === 'multinomial') {
            return 'Multinomial logistic regression';
        }
        if ($type === 'additive') {
            $detail = $family === null || $family === 'gaussian' ? null : self::familyLink($family, $link);

            return $detail === null ? 'Generalized additive model' : 'Generalized additive model ('.$detail.')';
        }
        if ($type === 'fixed_effects') {
            if ($family === 'poisson') {
                return 'Fixed-effects Poisson regression';
            }
            if ($family === 'binomial' && ($link === null || $link === 'logit')) {
                return 'Fixed-effects logistic regression';
            }

            return 'Fixed-effects regression';
        }

        return match ($type) {
            'quantile' => 'Quantile regression',
            'linear_ml' => 'Linear model (machine learning)',
            'tree_ensemble' => 'Tree ensemble',
            default => null,
        };
    }

    private static function familyLink(?string $family, ?string $link): ?string
    {
        if ($family !== null && $link !== null) {
            return $family.' family, '.$link.' link';
        }
        if ($family !== null) {
            return $family.' family';
        }
        if ($link !== null) {
            return $link.' link';
        }

        return null;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
