# راهنمای کامل سیستم تبلیغات (Advertisement System)

## 📦 نمای کلی

سیستم تبلیغات امکان مدیریت و نمایش تبلیغات در مکان‌های مختلف فروشگاه را فراهم می‌کند.

### ویژگی‌های اصلی:
- ✅ 3 نوع تبلیغ: تصویر، ویدیو، کد HTML/JavaScript
- ✅ 6 موقعیت نمایش: Header, Sidebar, Footer, Between Products, Product Detail, Popup
- ✅ راهنمای ابعاد برای هر موقعیت
- ✅ فعال/غیرفعال کردن دستی
- ✅ ترتیب‌دهی تبلیغات
- ✅ لینک مقصد با گزینه باز شدن در تب جدید
- ✅ چرخش خودکار تبلیغات (در Shop)
- ✅ Responsive design

---

## 🏗️ معماری سیستم

### Backend (Laravel API)

**Database:**
- جدول `advertisements`
- فیلدها: title, type, position, media_url, html_code, target_url, is_active, order, ...

**Files:**
- Model: `Advertisement.php`
- Repository: `AdvertisementRepository.php`
- Controller: `AdvertisementController.php`
- Request: `AdvertisementRequest.php`
- Migration: `create_advertisements_table.php`
- Routes: `advertisements.php`

**API Endpoints:**
```
Public (Frontend):
GET    /api/advertisements/active                    # همه تبلیغات فعال
GET    /api/advertisements/position/{position}       # تبلیغات یک موقعیت
GET    /api/advertisements/position-dimensions       # راهنمای ابعاد

Admin:
GET    /api/advertisements                           # لیست (paginated)
POST   /api/advertisements                           # ایجاد
GET    /api/advertisements/{id}                      # نمایش
PUT    /api/advertisements/{id}                      # ویرایش
DELETE /api/advertisements/{id}                      # حذف
POST   /api/advertisements/{id}/toggle-status        # فعال/غیرفعال
POST   /api/advertisements/update-order              # تغییر ترتیب
```

### Frontend - Admin Panel

**Files:**
- Pages:
  - `/pages/advertisements/index.tsx` - لیست
  - `/pages/advertisements/create.tsx` - ایجاد
  - `/pages/advertisements/[id]/edit.tsx` - ویرایش
- Components:
  - `/components/advertisements/advertisement-list.tsx` - جدول لیست
  - `/components/advertisements/advertisement-form.tsx` - فرم
  - `/components/advertisements/advertisement-validation-schema.ts` - Validation
- Data:
  - `/data/advertisements.ts` - React Query hooks
  - `/data/client/advertisement.ts` - API client

### Frontend - Shop Panel

**Files:**
- Component:
  - `/components/advertisements/ad-banner.tsx` - نمایش تبلیغ

---

## 📊 ابعاد توصیه شده

### 1. Header (بالای صفحه)
**توصیه اصلی:** 1200x150 پیکسل
**جایگزین‌ها:**
- 970x90 پیکسل
- 728x90 پیکسل

**توضیح:** بنر افقی در بالای صفحه - مناسب برای نمایش در تمام صفحات

### 2. Sidebar (نوار کناری)
**توصیه اصلی:** 300x250 پیکسل
**جایگزین‌ها:**
- 300x600 پیکسل (Skyscraper)
- 160x600 پیکسل

**توضیح:** بنر عمودی در کنار محتوا

### 3. Footer (پایین صفحه)
**توصیه اصلی:** 1200x100 پیکسل
**جایگزین‌ها:**
- 970x90 پیکسل
- 728x90 پیکسل

**توضیح:** بنر در انتهای صفحات

### 4. Between Products (بین محصولات)
**توصیه اصلی:** 728x90 پیکسل (Leaderboard)
**جایگزین‌ها:**
- 970x90 پیکسل
- 468x60 پیکسل

**توضیح:** بنر نمایش داده شده بین لیست محصولات

### 5. Product Detail (صفحه محصول)
**توصیه اصلی:** 300x250 پیکسل
**جایگزین‌ها:**
- 728x90 پیکسل
- 300x600 پیکسل

**توضیح:** تبلیغ در صفحه جزئیات محصول

### 6. Popup (پنجره بازشو)
**توصیه اصلی:** 600x400 پیکسل
**جایگزین‌ها:**
- 800x600 پیکسل
- 400x300 پیکسل

**توضیح:** پنجره modal که روی محتوا نمایش داده می‌شود

---

## 🚀 راهنمای استفاده

### مرحله 1: اجرای Migration

```bash
cd /app/pixer-api
php artisan migrate
```

این کار جدول `advertisements` را ایجاد می‌کند.

### مرحله 2: افزودن Route

Route ها به صورت خودکار در `RouteServiceProvider` لود می‌شوند.

### مرحله 3: ایجاد تبلیغ در Admin Panel

1. وارد Admin Panel شوید
2. به صفحه **تبلیغات** بروید
3. روی **+ افزودن تبلیغ جدید** کلیک کنید
4. فرم را پر کنید:
   - **عنوان**: نام تبلیغ (برای مدیریت داخلی)
   - **نوع تبلیغ**: تصویر، ویدیو یا HTML
   - **موقعیت نمایش**: انتخاب کنید کجا نمایش داده شود
   - **فایل**: آپلود تصویر یا ویدیو (در صورت انتخاب آن نوع)
   - **کد HTML**: کد HTML/JS (در صورت انتخاب نوع HTML)
   - **لینک مقصد**: آدرس هدایت هنگام کلیک (اختیاری)
   - **باز شدن در تب جدید**: فعال/غیرفعال
   - **وضعیت**: فعال/غیرفعال
   - **ترتیب**: عدد کمتر = اولویت بیشتر

5. **ذخیره** کنید

### مرحله 4: نمایش در Shop

تبلیغات فعال به صورت خودکار در موقعیت‌های مشخص شده نمایش داده می‌شوند.

برای اضافه کردن به صفحات Shop:

#### در هر Layout/Page:

```tsx
import AdBanner from '@/components/advertisements/ad-banner';

// در Header
<AdBanner position="header" />

// در Sidebar
<AdBanner position="sidebar" />

// در Footer
<AdBanner position="footer" />

// بین محصولات (در لیست)
<AdBanner position="between_products" />

// در صفحه محصول
<AdBanner position="product_detail" />

// Popup
<AdBanner position="popup" />
```

---

## 💻 مثال‌های کد

### 1. ایجاد تبلیغ تصویری (API)

```bash
curl -X POST http://localhost:8000/api/advertisements \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "title=تبلیغ تابستانه" \
  -F "type=image" \
  -F "position=header" \
  -F "media=@/path/to/banner.jpg" \
  -F "target_url=https://example.com/summer-sale" \
  -F "open_in_new_tab=true" \
  -F "is_active=true" \
  -F "order=1"
```

### 2. ایجاد تبلیغ HTML (API)

```bash
curl -X POST http://localhost:8000/api/advertisements \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Google Ads",
    "type": "html",
    "position": "sidebar",
    "html_code": "<script async src=\"https://pagead2.googlesyndication.com/...\"></script>",
    "is_active": true,
    "order": 1
  }'
```

### 3. دریافت تبلیغات یک موقعیت (Frontend)

```tsx
const fetchHeaderAds = async () => {
  const response = await fetch(
    'http://localhost:8000/api/advertisements/position/header'
  );
  const ads = await response.json();
  return ads;
};
```

### 4. Toggle Status (Admin)

```tsx
import { useToggleAdvertisementStatusMutation } from '@/data/advertisements';

const { mutate: toggleStatus } = useToggleAdvertisementStatusMutation();

// فعال/غیرفعال
toggleStatus(advertisementId);
```

---

## 🎨 سفارشی‌سازی UI

### استایل دهی تبلیغات

در Shop Panel می‌توانید CSS سفارشی اضافه کنید:

```css
/* در global styles */
.ad-banner {
  margin: 1rem auto;
  max-width: 100%;
}

.ad-banner-container {
  position: relative;
  overflow: hidden;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.ad-banner img,
.ad-banner video {
  display: block;
  width: 100%;
  height: auto;
}

/* Responsive */
@media (max-width: 768px) {
  .ad-banner {
    padding: 0 1rem;
  }
}
```

### محدود کردن تعداد نمایش

در `AdBanner.tsx`:

```tsx
// محدود به اولین 3 تبلیغ
const displayAds = ads.slice(0, 3);
```

---

## 🔧 تنظیمات پیشرفته

### 1. زمان چرخش تبلیغات

در `ad-banner.tsx` خط 45:

```tsx
// تغییر از 10 ثانیه به 5 ثانیه
const interval = setInterval(() => {
  setCurrentAdIndex((prev) => (prev + 1) % ads.length);
}, 5000); // 5 ثانیه
```

### 2. افزودن انیمیشن

```tsx
// در render
<div
  className={cn(
    'ad-banner-container transition-opacity duration-500',
    currentAd.target_url && 'cursor-pointer',
    className
  )}
  style={{ opacity: loading ? 0 : 1 }}
>
  {/* محتوا */}
</div>
```

### 3. اضافه کردن Lazy Loading

```tsx
<img
  src={currentAd.media_url}
  alt={currentAd.title}
  loading=\"lazy\"
  decoding=\"async\"
/>
```

---

## 🧪 تست سیستم

### 1. تست Backend (API)

```bash
# دریافت همه تبلیغات فعال
curl http://localhost:8000/api/advertisements/active

# دریافت تبلیغات Header
curl http://localhost:8000/api/advertisements/position/header

# دریافت راهنمای ابعاد
curl http://localhost:8000/api/advertisements/position-dimensions
```

### 2. تست Admin Panel

1. ایجاد تبلیغ تصویری با فایل 1MB
2. ایجاد تبلیغ ویدیویی با فایل 20MB
3. ایجاد تبلیغ HTML با Google Ads
4. تست فعال/غیرفعال
5. تست ویرایش
6. تست حذف

### 3. تست Shop Panel

1. بررسی نمایش در Header
2. بررسی نمایش در Sidebar
3. بررسی نمایش در Footer
4. بررسی چرخش تبلیغات (اگر چند تبلیغ باشد)
5. تست کلیک و redirect
6. تست responsive در موبایل

---

## 🐛 عیب‌یابی

### مشکل 1: تبلیغات نمایش داده نمی‌شوند

**راه حل:**
1. مطمئن شوید تبلیغ `is_active = true` است
2. بررسی کنید موقعیت صحیح انتخاب شده
3. Console مرورگر را برای خطاهای JS بررسی کنید
4. مطمئن شوید `NEXT_PUBLIC_REST_API_ENDPOINT` در `.env` تنظیم شده

### مشکل 2: خطای آپلود فایل

**راه حل:**
1. بررسی حجم فایل (تصویر: 5MB، ویدیو: 50MB)
2. فرمت فایل را بررسی کنید
3. تنظیمات `php.ini` را چک کنید:
   ```ini
   upload_max_filesize = 50M
   post_max_size = 50M
   ```

### مشکل 3: تبلیغ HTML کار نمی‌کند

**راه حل:**
1. مطمئن شوید کد HTML معتبر است
2. برای کدهای JavaScript، از تگ `<script>` استفاده کنید
3. CSP (Content Security Policy) را بررسی کنید

### مشکل 4: عدم نمایش Responsive

**راه حل:**
1. ابعاد تصویر را بررسی کنید
2. CSS `max-width: 100%` را اضافه کنید
3. از تصاویر با ابعاد مناسب استفاده کنید

---

## 🔒 امنیت

### 1. HTML/JavaScript Injection

⚠️ **هشدار:** نوع HTML می‌تواند خطرناک باشد.

**راه حل:**
- فقط ادمین‌های معتبر اجازه ایجاد تبلیغ HTML داشته باشند
- کدهای HTML را قبل از save بررسی کنید
- از CSP استفاده کنید

### 2. آپلود فایل

- فقط فرمت‌های مجاز: JPG, PNG, GIF, WebP, MP4, WebM, OGG
- محدودیت حجم: Image 5MB, Video 50MB
- اسکن فایل‌ها برای ویروس (توصیه می‌شود)

### 3. XSS Prevention

کامپوننت `AdBanner` از `dangerouslySetInnerHTML` استفاده می‌کند فقط برای نوع HTML که فقط ادمین می‌تواند ایجاد کند.

---

## 📈 بهینه‌سازی عملکرد

### 1. Lazy Loading

تصاویر به صورت lazy load می‌شوند:
```tsx
<img loading="lazy" />
```

### 2. Caching

در Backend می‌توانید cache اضافه کنید:

```php
// در Controller
use Illuminate\Support\Facades\Cache;

public function getActiveByPosition($position)
{
    return Cache::remember("ads_{$position}", 3600, function () use ($position) {
        return Advertisement::active()
            ->byPosition($position)
            ->ordered()
            ->get();
    });
}
```

### 3. Image Optimization

- استفاده از WebP
- کاهش کیفیت تصاویر
- استفاده از CDN

---

## 🎯 Best Practices

### 1. ابعاد تصویر
✅ همیشه از ابعاد توصیه شده استفاده کنید
✅ تصاویر را قبل از آپلود optimize کنید
✅ از aspect ratio صحیح استفاده کنید

### 2. محتوا
✅ متن واضح و خوانا
✅ CTA (Call To Action) مشخص
✅ لینک معتبر
✅ محتوای مرتبط با موقعیت

### 3. تست
✅ تست در مرورگرهای مختلف
✅ تست responsive در موبایل/تبلت
✅ تست کلیک و redirect
✅ تست A/B (اختیاری)

---

## 📚 منابع

- [IAB Standard Ad Unit Portfolio](https://www.iab.com/newadportfolio/)
- [Google AdSense Ad Sizes](https://support.google.com/adsense/answer/6002621)
- [Web Advertising Best Practices](https://www.iab.com/guidelines/)

---

## ✅ Checklist پس از نصب

- [ ] Migration اجرا شده
- [ ] Route ها ثبت شده
- [ ] Admin Panel کار می‌کند
- [ ] ایجاد تبلیغ موفق است
- [ ] آپلود فایل کار می‌کند
- [ ] تبلیغات در Shop نمایش داده می‌شوند
- [ ] چرخش تبلیغات کار می‌کند
- [ ] Responsive است
- [ ] کلیک و redirect کار می‌کند
- [ ] فعال/غیرفعال کردن کار می‌کند

---

**تاریخ ایجاد:** 2025-01-XX  
**نسخه:** 1.0.0  
**سازگاری:** Pixer 6.9.0  
**نویسنده:** Emergent E1 AI Agent
