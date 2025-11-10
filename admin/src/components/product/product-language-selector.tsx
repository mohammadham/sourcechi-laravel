import { useEffect, useState } from 'react';
import { Control, Controller, useWatch } from 'react-hook-form';
import Card from '@/components/common/card';
import Label from '@/components/ui/label';
import Checkbox from '@/components/ui/checkbox/checkbox';
import { useTranslation } from 'next-i18next';
import { useRouter } from 'next/router';
import { Config } from '@/config';

interface LanguageSelectorProps {
  control: Control<any>;
  currentLanguage: string;
}

// Language configurations with native names
const LANGUAGE_CONFIGS = [
  { code: 'fa', nativeName: 'فارسی', englishName: 'Persian' },
  { code: 'en', nativeName: 'English', englishName: 'English' },
  { code: 'de', nativeName: 'Deutsch', englishName: 'German' },
  { code: 'ar', nativeName: 'العربية', englishName: 'Arabic' },
];

export default function ProductLanguageSelector({
  control,
  currentLanguage,
}: LanguageSelectorProps) {
  const { t } = useTranslation();
  const router = useRouter();

  // Get enabled languages from Config
  const enabledLanguages = Config.availableLanguages.length > 0 
    ? Config.availableLanguages 
    : ['en', 'fa', 'de', 'ar'];
  // Filter language configs to only show enabled languages
  const availableLanguages = LANGUAGE_CONFIGS.filter((lang) =>
    enabledLanguages.includes(lang.code)
  );

  // Watch form values
  const allLanguagesEnabled = useWatch({
    control,
    name: 'all_languages',
    defaultValue: false,
  });

  const selectedLanguages = useWatch({
    control,
    name: 'available_languages',
    defaultValue: [currentLanguage],
  });

  console.log('[Language Selector] Current language:', currentLanguage);
  console.log('[Language Selector] All languages enabled:', allLanguagesEnabled);
  console.log('[Language Selector] Selected languages:', selectedLanguages);

  return (
    <Card className="w-full mb-5">
      <div className="mb-5">
        <Label className="text-lg font-semibold">
          {t('form:input-label-product-languages')}
        </Label>
        <p className="text-sm text-gray-500 mt-1">
          {t('form:input-description-product-languages')}
        </p>
      </div>

      <div className="space-y-4">
        {/* All Languages Toggle */}
        <Controller
          name="all_languages"
          control={control}
          defaultValue={false}
          render={({ field }) => (
            <div className="border-2 border-gray-200 rounded-lg p-4 hover:border-accent transition-colors">
              <Checkbox
                {...field}
                id="all_languages"
                label={
                  <div className="flex items-center gap-2">
                    <span className="text-lg">🌍</span>
                    <div>
                      <div className="font-semibold text-base">
                        {t('form:input-label-all-languages')}
                      </div>
                      <div className="text-xs text-gray-500 mt-1">
                        {t('form:input-description-all-languages')}
                      </div>
                    </div>
                  </div>
                }
                checked={field.value}
                onChange={(e) => {
                  const isChecked = e.target.checked;
                  console.log('[Language Selector] All languages toggled:', isChecked);
                  field.onChange(isChecked);
                }}
              />
            </div>
          )}
        />

        {/* Individual Language Checkboxes */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Controller
            name="available_languages"
            control={control}
            defaultValue={[currentLanguage]}
            render={({ field }) => (
              <>
                {availableLanguages.map((lang) => {
                  const isChecked =
                    Array.isArray(field.value) && field.value.includes(lang.code);
                  const isDisabled = allLanguagesEnabled;

                  return (
                    <div
                      key={lang.code}
                      className={`border rounded-lg p-3 transition-all ${
                        isDisabled
                          ? 'bg-gray-50 border-gray-200 opacity-60'
                          : isChecked
                          ? 'border-accent bg-accent/5'
                          : 'border-gray-200 hover:border-accent'
                      }`}
                    >
                      <Checkbox
                        id={`lang_${lang.code}`}
                        label={
                          <div>
                            <div className="font-medium">{lang.nativeName}</div>
                            <div className="text-xs text-gray-500">
                              {lang.englishName}
                            </div>
                          </div>
                        }
                        checked={isChecked}
                        disabled={isDisabled}
                        onChange={(e) => {
                          if (isDisabled) return;

                          const currentValues = Array.isArray(field.value)
                            ? field.value
                            : [currentLanguage];

                          let newValues;
                          if (e.target.checked) {
                            // Add language
                            newValues = [...currentValues, lang.code];
                          } else {
                            // Remove language
                            newValues = currentValues.filter(
                              (code: string) => code !== lang.code
                            );
                          }

                          // Ensure at least one language is selected
                          if (newValues.length === 0) {
                            newValues = [currentLanguage];
                          }

                          console.log('[Language Selector] Languages changed:', newValues);
                          field.onChange(newValues);
                        }}
                      />
                    </div>
                  );
                })}
              </>
            )}
          />
        </div>
      </div>
    </Card>
  );
}
