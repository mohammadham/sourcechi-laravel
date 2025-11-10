# 📋 پلن پیاده‌سازی سیستم چند زبانی محصولات

## 🎯 هدف
افزودن قابلیت انتخاب زبان‌های نمایش برای هر محصول به جای ایجاد محصول جداگانه برای هر زبان

---

## 🔍 تحلیل وضعیت فعلی

### مشکلات موجود:
1. ✗ هر محصول فقط برای یک زبان (`language` field) قابل نمایش است
2. ✗ برای نمایش محصول در 4 زبان، باید 4 بار محصول را ایجاد کرد
3. ✗ مدیریت محصولات چند زبانه بسیار زمان‌بر و پیچیده است
4. ✗ به‌روزرسانی محصول در همه زبان‌ها نیازمند ویرایش چندین رکورد است

### ساختار فعلی:
```php
// Product Model
- language: string (en, fa, ar, de) - تنها یک زبان
- name, description: متن به آن زبان
```

### Query فعلی:
```php
$products = Product::where('language', $request->language)->get();
```

---

## 🎨 طراحی راه‌حل

### ویژگی‌های مورد نظر:

#### 1. انتخاب چند زبانه:
```
☑ زبان فارسی (fa)
☑ زبان انگلیسی (en)
☐ زبان آلمانی (de)
☐ زبان عربی (ar)

─────── یا ───────

☑ همه زبان‌ها (فعلی و آینده)
```

#### 2. رفتار "همه زبان‌ها":
- اگر فعال باشد: محصول در تمام زبان‌های موجود و آینده نمایش داده می‌شود
- checkbox های انتخاب زبان غیرفعال می‌شوند
- حتی اگر زبان جدیدی اضافه شود، محصول در آن نیز نمایش داده می‌شود

#### 3. زبان پیش‌فرض:
- در هنگام ایجاد محصول، زبان فعلی کاربر به طور پیش‌فرض انتخاب شده است
- کاربر می‌تواند زبان‌های بیشتری اضافه کند یا "همه زبان‌ها" را انتخاب کند

---

## 🗄️ تغییرات دیتابیس

### Migration جدید:
```php
// 2025_01_10_000001_add_multi_language_support_to_products.php

Schema::table('products', function (Blueprint $table) {
    // JSON array برای ذخیره لیست زبان‌های انتخاب شده
    $table->json('available_languages')->nullable()->after('language');
    
    // Boolean برای "همه زبان‌ها"
    $table->boolean('all_languages')->default(false)->after('available_languages');
});

// Migration برای محصولات موجود (Backward Compatibility)
DB::statement("
    UPDATE products 
    SET available_languages = JSON_ARRAY(language),
        all_languages = false
    WHERE available_languages IS NULL
");
```

### توضیحات:
- **available_languages**: `["fa", "en", "de"]` - لیست زبان‌های انتخاب شده
- **all_languages**: `true/false` - آیا محصول برای همه زبان‌ها است؟
- **language**: فیلد قدیمی حفظ می‌شود برای backward compatibility

---

## 🔧 تغییرات Backend

### 1. Model (Product.php):

```php
class Product extends Model
{
    protected $casts = [
        'image' => 'json',
        'gallery' => 'json',
        'video' => 'json',
        'available_languages' => 'array', // ✅ اضافه شد
    ];

    protected $fillable = [
        // ... existing fields
        'available_languages',
        'all_languages',
    ];

    /**
     * بررسی اینکه محصول در یک زبان خاص باید نمایش داده شود یا نه
     */
    public function isAvailableInLanguage($language)
    {
        // اگر all_languages فعال باشد، در همه زبان‌ها نمایش داده می‌شود
        if ($this->all_languages) {
            return true;
        }

        // اگر available_languages خالی باشد (محصولات قدیمی)
        if (empty($this->available_languages)) {
            return $this->language === $language;
        }

        // بررسی اینکه زبان در لیست available_languages باشد
        return in_array($language, $this->available_languages);
    }

    /**
     * Scope برای فیلتر کردن محصولات بر اساس زبان
     */
    public function scopeForLanguage($query, $language)
    {
        return $query->where(function ($q) use ($language) {
            $q->where('all_languages', true)
              ->orWhereJsonContains('available_languages', $language)
              ->orWhere(function ($q2) use ($language) {
                  // Fallback برای محصولات قدیمی
                  $q2->whereNull('available_languages')
                     ->where('language', $language);
              });
        });
    }
}
```

### 2. ProductRepository.php:

```php
public function fetchProducts($request)
{
    $language = $request->language ?? DEFAULT_LANGUAGE;
    
    // ✅ استفاده از scope جدید
    $products_query = $this->forLanguage($language);
    
    // ... rest of the logic
    
    return $products_query;
}
```

### 3. ProductController.php:

```php
public function store(ProductCreateRequest $request)
{
    // ✅ پردازش available_languages
    $validatedData = $request->validated();
    
    // اگر all_languages فعال باشد، available_languages را null کن
    if ($validatedData['all_languages'] ?? false) {
        $validatedData['available_languages'] = null;
    }
    
    $product = $this->repository->create($validatedData);
    
    return $product;
}

public function update(ProductUpdateRequest $request, $id)
{
    // همان منطق
    $validatedData = $request->validated();
    
    if ($validatedData['all_languages'] ?? false) {
        $validatedData['available_languages'] = null;
    }
    
    $product = $this->repository->update($validatedData, $id);
    
    return $product;
}
```

### 4. Validation (ProductCreateRequest.php):

```php
public function rules()
{
    return [
        // ... existing rules
        'available_languages' => 'nullable|array',
        'available_languages.*' => 'string|in:en,de,ar,fa', // زبان‌های معتبر
        'all_languages' => 'boolean',
    ];
}
```

---

## 🎨 تغییرات Frontend (Admin Panel)

### 1. Component جدید: `LanguageSelector.tsx`

```tsx
// /app/admin/src/components/product/language-selector.tsx

import { useTranslation } from 'next-i18next';
import { Controller } from 'react-hook-form';
import Label from '@/components/ui/label';
import Card from '@/components/common/card';

interface Language {
  code: string;
  name: string;
  nativeName: string;
}

const AVAILABLE_LANGUAGES: Language[] = [
  { code: 'en', name: 'English', nativeName: 'English' },
  { code: 'fa', name: 'Persian', nativeName: 'فارسی' },
  { code: 'ar', name: 'Arabic', nativeName: 'العربية' },
  { code: 'de', name: 'German', nativeName: 'Deutsch' },
];

interface LanguageSelectorProps {
  control: any;
  currentLanguage: string;
}

export default function LanguageSelector({ 
  control, 
  currentLanguage 
}: LanguageSelectorProps) {
  const { t } = useTranslation();

  return (
    <Card className="w-full mb-5">
      <div className="mb-5">
        <Label>{t('form:input-label-available-languages')}</Label>
        <p className="text-sm text-gray-500 mb-3">
          {t('form:input-description-available-languages')}
        </p>

        {/* دکمه "همه زبان‌ها" */}
        <Controller
          name="all_languages"
          control={control}
          defaultValue={false}
          render={({ field }) => (
            <label className="flex items-center gap-3 mb-4 p-4 border-2 border-dashed rounded-lg cursor-pointer hover:border-accent transition-colors">
              <input
                type="checkbox"
                checked={field.value}
                onChange={(e) => field.onChange(e.target.checked)}
                className="w-5 h-5 text-accent border-gray-300 rounded focus:ring-accent"
              />
              <div>
                <span className="font-semibold text-base">
                  🌍 {t('form:input-label-all-languages')}
                </span>
                <p className="text-xs text-gray-500 mt-1">
                  {t('form:input-description-all-languages')}
                </p>
              </div>
            </label>
          )}
        />

        {/* لیست زبان‌ها */}
        <Controller
          name="available_languages"
          control={control}
          defaultValue={[currentLanguage]}
          render={({ field }) => {
            const allLanguages = control._formValues.all_languages;
            
            return (
              <div className="grid grid-cols-2 gap-3">
                {AVAILABLE_LANGUAGES.map((lang) => {
                  const isChecked = field.value?.includes(lang.code) ?? false;
                  const isDisabled = allLanguages;

                  return (
                    <label
                      key={lang.code}
                      className={`
                        flex items-center gap-3 p-3 border-2 rounded-lg cursor-pointer
                        transition-all
                        ${isDisabled ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'hover:border-accent'}
                        ${isChecked && !isDisabled ? 'border-accent bg-accent/5' : 'border-gray-200'}
                      `}
                    >
                      <input
                        type="checkbox"
                        checked={isChecked}
                        disabled={isDisabled}
                        onChange={(e) => {
                          const newValue = e.target.checked
                            ? [...(field.value || []), lang.code]
                            : field.value.filter((l: string) => l !== lang.code);
                          field.onChange(newValue);
                        }}
                        className="w-4 h-4 text-accent border-gray-300 rounded focus:ring-accent disabled:opacity-50"
                      />
                      <div>
                        <div className="font-medium">{lang.nativeName}</div>
                        <div className="text-xs text-gray-500">{lang.name}</div>
                      </div>
                    </label>
                  );
                })}
              </div>
            );
          }}
        />
      </div>
    </Card>
  );
}
```

### 2. ادغام در `product-form.tsx`:

```tsx
import LanguageSelector from './language-selector';

// در داخل فرم، بعد از بخش Type Selection:
<LanguageSelector 
  control={control}
  currentLanguage={locale}
/>
```

### 3. Translation Keys (fa/common.json):

```json
{
  "form:input-label-available-languages": "زبان‌های نمایش محصول",
  "form:input-description-available-languages": "انتخاب کنید که این محصول در کدام زبان‌ها نمایش داده شود",
  "form:input-label-all-languages": "همه زبان‌ها",
  "form:input-description-all-languages": "نمایش در تمام زبان‌های فعلی و آینده (توصیه می‌شود)"
}
```

---

## 🔄 Backward Compatibility

### استراتژی سازگاری با محصولات موجود:

1. **Migration Data:**
   ```sql
   UPDATE products 
   SET available_languages = JSON_ARRAY(language),
       all_languages = false
   WHERE available_languages IS NULL;
   ```

2. **Logic در Model:**
   ```php
   public function isAvailableInLanguage($language)
   {
       // اگر all_languages فعال باشد
       if ($this->all_languages) {
           return true;
       }

       // اگر available_languages خالی (محصولات قدیمی)
       if (empty($this->available_languages)) {
           return $this->language === $language; // fallback به روش قدیم
       }

       // روش جدید
       return in_array($language, $this->available_languages);
   }
   ```

3. **تست محصولات موجود:**
   - محصولات موجود باید همچنان با همان زبان قبلی نمایش داده شوند
   - هیچ محصولی نباید گم شود یا در زبان اشتباه نمایش داده شود

---

## 📊 مراحل پیاده‌سازی

### مرحله 1: Backend (Database & Model)
- [ ] ایجاد migration برای افزودن فیلدهای جدید
- [ ] اجرای migration و data migration برای محصولات موجود
- [ ] اضافه کردن `available_languages` و `all_languages` به Model
- [ ] پیاده‌سازی method های `isAvailableInLanguage` و `scopeForLanguage`
- [ ] تست backward compatibility با محصولات موجود

### مرحله 2: Backend (API)
- [ ] به‌روزرسانی validation rules
- [ ] اصلاح `ProductController::store` و `update`
- [ ] اصلاح `ProductRepository::fetchProducts`
- [ ] تست API با Postman/curl

### مرحله 3: Frontend (Admin)
- [ ] ایجاد component `LanguageSelector`
- [ ] ادغام در `product-form.tsx`
- [ ] اضافه کردن translation keys
- [ ] تست UI و تعاملات

### مرحله 4: تست جامع
- [ ] تست ایجاد محصول جدید با چند زبان
- [ ] تست ایجاد محصول با "همه زبان‌ها"
- [ ] تست ویرایش محصول موجود
- [ ] تست نمایش محصولات در Shop با زبان‌های مختلف
- [ ] تست backward compatibility

### مرحله 5: مستندات
- [ ] به‌روزرسانی documentation
- [ ] اضافه کردن راهنمای استفاده
- [ ] تهیه changelog

---

## ⚠️ نکات مهم

### 1. Performance:
- استفاده از JSON index برای `available_languages` در MySQL 8+
- Cache کردن لیست محصولات بر اساس زبان

### 2. SEO:
- حفظ slug یکتا برای هر زبان
- canonical URLs برای محصولات چند زبانه

### 3. UX:
- نمایش واضح زبان‌های انتخاب شده در لیست محصولات
- آیکون یا badge برای محصولات "همه زبان‌ها"

### 4. Data Integrity:
- Validation سخت‌گیرانه برای کدهای زبان
- مدیریت محصولاتی که در حال حاضر فقط در یک زبان هستند

---

## 📈 مزایای راه‌حل

✅ مدیریت آسان‌تر محصولات چند زبانه
✅ کاهش تکرار داده
✅ انعطاف‌پذیری برای افزودن زبان‌های جدید
✅ تجربه کاربری بهتر برای مدیران
✅ سازگار با محصولات موجود
✅ قابلیت توسعه برای آینده

---

## 🎯 نتیجه

این راه‌حل یک سیستم جامع و انعطاف‌پذیر برای مدیریت محصولات چند زبانه فراهم می‌کند که:
- با ساختار فعلی سازگار است
- آسان برای استفاده است
- قابل توسعه برای آینده است
- هیچ محصول موجودی را از بین نمی‌برد
