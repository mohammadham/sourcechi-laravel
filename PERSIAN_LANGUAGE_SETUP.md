# افزودن زبان فارسی به Pixer

این سند مراحل انجام شده برای افزودن زبان فارسی (Farsi/Persian) به سیستم Pixer را توضیح می‌دهد.

## تغییرات انجام شده

### 1. فایل‌های ترجمه Frontend

#### Admin Panel (`/app/admin/public/locales/fa/`)
✅ فایل‌های زیر ایجاد شدند:
- `banner.json` - ترجمه بنر صفحه اصلی
- `common.json` - ترجمه عبارات رایج (25KB - ~600 ترجمه)
- `form.json` - ترجمه فرم‌ها و فیلدها (51KB - ~1200 ترجمه)
- `table.json` - ترجمه جداول و ستون‌ها (4.6KB - ~100 ترجمه)
- `widgets.json` - ترجمه ویجت‌ها و کارت‌ها

#### Shop Panel (`/app/shop/public/locales/fa/`)
✅ فایل‌های زیر ایجاد شدند:
- `about-us.json` - ترجمه صفحه درباره ما
- `common.json` - ترجمه عبارات رایج (37KB - ~900 ترجمه)
- `form.json` - فایل خالی (برای استفاده‌های آینده)

### 2. Backend Database

✅ **Seeder فایل**: `/app/pixer-api/packages/marvel/src/Database/Seeders/PersianLanguageSeeder.php`
- افزودن رکورد زبان فارسی به جدول `languages`
- شامل کد زبان: `fa`
- نام زبان: `فارسی`
- پرچم: پرچم ایران (https://flagcdn.com/w320/ir.png)

✅ **SQL Script**: `/app/pixer-api/add_persian_language.sql`
- برای افزودن مستقیم به دیتابیس در صورت نیاز

### 3. تنظیمات محیط (.env)

✅ **Admin Panel** (`/app/admin/.env`):
```env
NEXT_PUBLIC_ENABLE_MULTI_LANG=true
NEXT_PUBLIC_AVAILABLE_LANGUAGES=en,de,ar,es,he,zh,fa
```

✅ **Shop Panel** (`/app/shop/.env`):
```env
NEXT_PUBLIC_ENABLE_MULTI_LANG=true
NEXT_PUBLIC_AVAILABLE_LANGUAGES=en,de,ar,fa
```

### 4. پشتیبانی RTL

✅ سیستم از قبل پشتیبانی RTL برای زبان عربی دارد که برای فارسی نیز کار می‌کند.
- Tailwind CSS با `tailwindcss-rtl` پیکربندی شده است
- Direction به صورت خودکار بر اساس زبان تنظیم می‌شود

## نحوه اجرا

### مرحله 1: افزودن زبان به دیتابیس

**روش 1: استفاده از Seeder (توصیه می‌شود)**
```bash
cd /app/pixer-api
php artisan db:seed --class=Marvel\\Database\\Seeders\\PersianLanguageSeeder
```

**روش 2: اجرای SQL مستقیم**
```bash
# در MySQL/MariaDB
mysql -u [username] -p [database_name] < add_persian_language.sql
```

### مرحله 2: بازنشانی Cache (اختیاری)

```bash
cd /app/pixer-api
php artisan cache:clear
php artisan config:clear
```

### مرحله 3: راه‌اندازی مجدد سرویس‌ها

```bash
# بازنشانی Admin Panel
cd /app/admin
npm run build
npm start

# بازنشانی Shop Panel
cd /app/shop
npm run build
npm start

# بازنشانی Backend
cd /app/pixer-api
php artisan config:cache
```

## تست

1. به پنل Admin بروید: `http://localhost:3002`
2. از منوی تغییر زبان، گزینه "فارسی" را انتخاب کنید
3. تمام متن‌ها باید به فارسی نمایش داده شوند
4. راست‌چینی (RTL) باید به درستی فعال شود

## نکات مهم

### ترجمه‌ها
- فایل‌های `common.json` و `form.json` با AI ترجمه شده‌اند
- کلیدهای مهم به صورت دستی بررسی و ترجمه شده‌اند
- در صورت نیاز به ویرایش، فایل‌های JSON را ویرایش کنید

### RTL
- زبان فارسی به صورت خودکار راست به چپ می‌شود
- از پشتیبانی RTL موجود عربی استفاده می‌کند
- نیازی به تنظیمات اضافی RTL نیست

### بروزرسانی
- برای افزودن ترجمه جدید، فایل JSON مربوطه را ویرایش کنید
- پس از تغییر فایل‌های ترجمه، نیازی به rebuild نیست (hot reload)
- فقط refresh کردن مرورگر کافی است

## ساختار فایل‌ها

```
/app/
├── admin/
│   ├── .env (NEXT_PUBLIC_AVAILABLE_LANGUAGES شامل fa)
│   └── public/
│       └── locales/
│           └── fa/
│               ├── banner.json
│               ├── common.json
│               ├── form.json
│               ├── table.json
│               └── widgets.json
├── shop/
│   ├── .env (NEXT_PUBLIC_AVAILABLE_LANGUAGES شامل fa)
│   └── public/
│       └── locales/
│           └── fa/
│               ├── about-us.json
│               ├── common.json
│               └── form.json
└── pixer-api/
    ├── add_persian_language.sql
    └── packages/
        └── marvel/
            └── src/
                └── Database/
                    └── Seeders/
                        └── PersianLanguageSeeder.php
```

## مشکلات رایج و حل آن‌ها

### زبان فارسی در لیست نمایش داده نمی‌شود
- اطمینان حاصل کنید که رکورد در جدول `languages` وجود دارد
- Cache را پاک کنید
- سرویس‌ها را restart کنید

### متن‌ها ترجمه نمی‌شوند
- فایل‌های JSON را بررسی کنید
- اطمینان حاصل کنید که کلیدها با فایل انگلیسی مطابقت دارند
- مرورگر را refresh کنید

### RTL کار نمی‌کند
- اطمینان حاصل کنید که `tailwindcss-rtl` نصب است
- Tailwind config را بررسی کنید
- Cache مرورگر را پاک کنید

## نسخه

- تاریخ ایجاد: October 27, 2024
- نسخه Pixer: 6.9.0
- وضعیت: ✅ کامل و آماده استفاده
