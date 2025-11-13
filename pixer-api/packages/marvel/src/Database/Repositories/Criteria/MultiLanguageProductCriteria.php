<?php

namespace Marvel\Database\Repositories\Criteria;

use Illuminate\Support\Facades\Log;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Class MultiLanguageProductCriteria
 * Custom criteria for filtering products by language
 * Supports all_languages and available_languages fields
 * 
 * This criteria REPLACES the simple WHERE language = ? 
 * with our advanced forLanguage scope
 */
class MultiLanguageProductCriteria implements CriteriaInterface
{
    /**
     * Apply criteria in query repository
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model $model
     * @param RepositoryInterface $repository
     *
     * @return mixed
     */
    public function apply($model, RepositoryInterface $repository)
    {
        try {
            // Get language from request
            $language = request()->input('language');

            // If language parameter exists, we need to replace the simple WHERE
            // with our advanced scope
            if (!empty($language)) {
                Log::info('[MultiLanguageProductCriteria] Language detected: ' . $language);
                
                // Get the current query's wheres
                $wheres = $model->getQuery()->wheres ?? [];
                
                // Remove any existing simple WHERE language = ? conditions
                // that were added by RequestCriteria
                $filteredWheres = [];
                foreach ($wheres as $where) {
                    // Skip basic where conditions on 'language' column
                    if (isset($where['column']) && $where['column'] === 'language' && $where['type'] === 'Basic') {
                        Log::info('[MultiLanguageProductCriteria] Removing simple WHERE language condition');
                        continue;
                    }
                    // Also check for qualified column names
                    if (isset($where['column']) && $where['column'] === 'products.language' && $where['type'] === 'Basic') {
                        Log::info('[MultiLanguageProductCriteria] Removing simple WHERE products.language condition');
                        continue;
                    }
                    $filteredWheres[] = $where;
                }
                
                // Reset wheres
                $model->getQuery()->wheres = $filteredWheres;
                
                // Also remove language from bindings
                // This is trickier - we need to find and remove the binding
                $bindings = $model->getQuery()->getRawBindings();
                if (isset($bindings['where'])) {
                    // Find the position of language value in bindings
                    $key = array_search($language, $bindings['where'], true);
                    if ($key !== false) {
                        unset($bindings['where'][$key]);
                        $bindings['where'] = array_values($bindings['where']); // re-index
                        $model->getQuery()->setBindings($bindings);
                        Log::info('[MultiLanguageProductCriteria] Removed language binding');
                    }
                }
                
                // Now apply our advanced scope
                Log::info('[MultiLanguageProductCriteria] Applying forLanguage scope');
                $model = $model->forLanguage($language);
            }
        } catch (\Exception $e) {
            // Log error but don't break the query
            Log::error('[MultiLanguageProductCriteria] Error: ' . $e->getMessage());
            Log::error('[MultiLanguageProductCriteria] Stack trace: ' . $e->getTraceAsString());
        }

        return $model;
    }
}
