<?php

namespace Marvel\Database\Repositories\Criteria;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Class MultiLanguageProductCriteria
 * Custom criteria for filtering products by language
 * Supports all_languages and available_languages fields
 */
class MultiLanguageProductCriteria implements CriteriaInterface
{
    /**
     * Apply criteria in query repository
     *
     * @param \Illuminate\Database\Eloquent\Builder $model
     * @param RepositoryInterface $repository
     *
     * @return mixed
     */
    public function apply($model, RepositoryInterface $repository)
    {
        // Get language from request
        $language = request()->get('language');

        // If language is specified, use the forLanguage scope
        if ($language) {
            Log::info('[MultiLanguageProductCriteria] Applying forLanguage scope for language: ' . $language);
            
            // Use the forLanguage scope which handles:
            // - all_languages = true
            // - available_languages contains the language
            // - backward compatibility with old language field
            $model = $model->forLanguage($language);
        }

        return $model;
    }
}
