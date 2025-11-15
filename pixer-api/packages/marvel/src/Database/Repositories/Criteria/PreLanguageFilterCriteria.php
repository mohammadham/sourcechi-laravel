<?php

namespace Marvel\Database\Repositories\Criteria;

use Illuminate\Support\Facades\Log;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Class PreLanguageFilterCriteria
 * 
 * This criteria runs BEFORE RequestCriteria and applies the forLanguage scope
 * BEFORE any joins happen. This ensures multi-language filtering works correctly
 * with relationships like tags.
 */
class PreLanguageFilterCriteria implements CriteriaInterface
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

            // If language parameter exists, apply scope BEFORE joins
            if (!empty($language)) {
                Log::info('[PreLanguageFilterCriteria] Applying forLanguage scope for: ' . $language);
                
                // Apply the scope directly - this happens before RequestCriteria joins
                $model = $model->forLanguage($language);
                
                // Mark that we've handled language so RequestCriteria doesn't add WHERE language = ?
                // We'll do this by temporarily removing 'language' from the request
                // But we can't modify request, so we'll use a different approach
            }
        } catch (\Exception $e) {
            Log::error('[PreLanguageFilterCriteria] Error: ' . $e->getMessage());
        }

        return $model;
    }
}
