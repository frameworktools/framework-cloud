@props(['metrics', 'title' => 'Model summary'])
@php
    $labels = [
        'nobs' => 'Observations', 'n_events' => 'Events', 'df_resid' => 'Residual degrees of freedom', 'df_model' => 'Model degrees of freedom',
        'aic' => 'AIC', 'bic' => 'BIC', 'rsquared' => 'R²', 'r.squared' => 'R²', 'rsquared_adj' => 'Adjusted R²', 'adj.r.squared' => 'Adjusted R²',
        'within_r.squared' => 'Within R²', 'pseudo_rsquared' => 'Pseudo R²', 'sigma' => 'Residual standard deviation',
        'llf' => 'Log likelihood', 'llnull' => 'Null log likelihood', 'log_likelihood' => 'Log likelihood', 'deviance' => 'Deviance', 'null.deviance' => 'Null deviance',
        'null_deviance' => 'Null deviance', 'deviance_explained' => 'Deviance explained', 'dispersion' => 'Dispersion', 'scale' => 'Scale',
        'theta' => 'Theta', 'alpha' => 'Alpha', 'concordance' => 'Concordance', 'converged' => 'Converged', 'reml' => 'REML',
        'edf' => 'Effective degrees of freedom', 'quantile' => 'Quantile',
        'confidence_level' => 'Confidence level', 'interval_method' => 'Interval method', 'vcov_type' => 'Standard errors',
        'n_estimators' => 'Trees', 'max_depth' => 'Maximum depth', 'learning_rate' => 'Learning rate', 'min_samples_leaf' => 'Minimum samples per leaf',
        'n_features' => 'Features', 'n_classes' => 'Classes', 'objective' => 'Objective', 'importance_type' => 'Importance measure',
    ];
    $groupOf = fn (string $name) => match (true) {
        in_array($name, ['nobs', 'n_events', 'df_resid', 'df_model'], true), str_starts_with($name, 'n_groups') => 'Sample and degrees of freedom',
        in_array($name, ['aic', 'bic', 'rsquared', 'r.squared', 'rsquared_adj', 'adj.r.squared', 'within_r.squared', 'pseudo_rsquared', 'sigma', 'llf',
            'log_likelihood', 'deviance', 'null.deviance', 'null_deviance', 'deviance_explained', 'concordance', 'converged'], true) => 'Model fit',
        in_array($name, ['confidence_level', 'interval_method', 'vcov_type'], true) => 'Inference',
        in_array($name, ['n_estimators', 'max_depth', 'learning_rate', 'min_samples_leaf', 'n_features', 'n_classes', 'objective', 'importance_type'], true) => 'Settings',
        default => 'Additional statistics',
    };
    $label = fn (string $name) => $labels[$name]
        ?? (str_starts_with($name, 'n_groups.') ? 'Groups: '.substr($name, 9) : ucfirst(str_replace(['_', '.'], ' ', $name)));
    $groups = [];
    foreach ($metrics as $metric) {
        $groups[$groupOf($metric['name'])][] = $metric;
    }
@endphp
@if ($metrics)
    <section aria-labelledby="model-summary-title">
        <h2 id="model-summary-title" class="mb-3 font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</h2>
        <div class="w-fit max-w-full overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="w-auto border-collapse bg-white text-sm dark:bg-slate-950">
                <caption class="sr-only">Summary statistics grouped by sample, fit, inference, and settings</caption>
                <thead class="border-b border-slate-200 dark:border-slate-700"><tr><th scope="col" class="px-4 py-2 text-left font-medium text-slate-500 dark:text-slate-400">Statistic</th><th scope="col" class="px-4 py-2 text-right font-medium text-slate-500 dark:text-slate-400">Value</th></tr></thead>
                @foreach (['Sample and degrees of freedom', 'Model fit', 'Inference', 'Settings', 'Additional statistics'] as $group)
                    @if (!empty($groups[$group]))
                        <tbody class="border-b border-slate-200 last:border-0 dark:border-slate-700">
                            <tr><th scope="rowgroup" colspan="2" class="bg-slate-50 px-4 py-2 text-left text-xs font-semibold text-slate-600 dark:bg-slate-900 dark:text-slate-300">{{ $group }}</th></tr>
                            @foreach ($groups[$group] as $metric)
                                @php
                                    $value = $metric['value'];
                                    $isNumber = (is_int($value) || is_float($value)) && ! in_array($metric['name'], ['confidence_level', 'deviance_explained'], true);
                                    $display = match (true) {
                                        in_array($metric['name'], ['confidence_level', 'deviance_explained'], true) && is_numeric($value) => rtrim(rtrim(number_format($value * 100, 2), '0'), '.').'%',
                                        $metric['name'] === 'interval_method' => ['wald-t' => 'Wald t', 'wald-normal' => 'Wald normal'][$value] ?? $value,
                                        is_bool($value) => $value ? 'Yes' : 'No',
                                        default => \App\Support\ArtifactFormatting::value($value),
                                    };
                                @endphp
                                <tr class="border-t border-slate-100 dark:border-slate-800">
                                    <th scope="row" class="px-4 py-2 text-left font-normal text-slate-700 dark:text-slate-300">{{ $label($metric['name']) }}</th>
                                    <td @if ($isNumber) data-model-summary-number="{{ $value }}" @endif class="px-4 py-2 text-right text-slate-900 dark:text-slate-100 {{ $isNumber ? 'font-mono tabular-nums' : '' }}" title="{{ is_bool($value) ? ($value ? 'TRUE' : 'FALSE') : $value }}">{{ $display }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endif
                @endforeach
            </table>
        </div>
    </section>
@endif
