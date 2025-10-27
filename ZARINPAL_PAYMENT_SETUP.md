# راهنمای نصب و استفاده از درگاه پرداخت زرین‌پال (ZarinPal)

## 📦 فایل‌های اضافه شده

### Backend (Laravel API)

**1. Payment Class:**
- `/app/pixer-api/packages/marvel/src/Payment/Zarinpal.php`
  - کلاس اصلی درگاه زرین‌پال
  - پیاده‌سازی PaymentInterface
  - متدهای getIntent(), verify(), handleWebHooks()
  - پشتیبانی از Sandbox و Production

**2. Configuration:**
- `/app/pixer-api/packages/marvel/config/shop.php` (به‌روزرسانی شده)
  - افزودن تنظیمات ZarinPal
  - merchant_id, sandbox, callback_url, use_toman

**3. Environment Variables:**
- `/app/pixer-api/.env.example` (به‌روزرسانی شده)
  - افزودن متغیرهای محیطی ZarinPal

**4. Database Seeder:**
- `/app/pixer-api/packages/marvel/src/Database/Seeders/ZarinpalPaymentGatewaySeeder.php`
  - Seeder برای افزودن ZarinPal به تنظیمات

**5. SQL Script:**
- `/app/pixer-api/add_zarinpal_gateway.sql`
  - اسکریپت SQL برای افزودن دستی به دیتابیس

### Frontend - Admin Panel

**1. Payment Gateway List:**
- `/app/admin/src/components/settings/payment/payment-gateway.ts` (به‌روزرسانی شده)
  - افزودن ZarinPal به لیست درگاه‌ها

**2. Icon:**
- `/app/admin/src/components/icons/payment-gateways/zarinpal.tsx`
  - آیکون زرین‌پال برای پنل ادمین

**3. Translations:**
- `/app/admin/public/locales/fa/common.json` (به‌روزرسانی شده)
  - ترجمه‌های فارسی مرتبط با زرین‌پال

### Frontend - Shop Panel

**1. Icon:**
- `/app/shop/src/components/icons/payment-gateways/zarinpal.tsx`
  - آیکون زرین‌پال (روشن و تیره)

**2. Translations:**
- `/app/shop/public/locales/fa/common.json` (به‌روزرسانی شده)
  - ترجمه‌های فارسی برای فروشگاه

---

## 🔧 راهنمای نصب و پیکربندی

### مرحله 1: تنظیمات Backend

#### 1.1. افزودن ZarinPal به دیتابیس

**روش A: استفاده از Seeder (توصیه می‌شود)**
```bash
cd /app/pixer-api
php artisan db:seed --class=Marvel\\Database\\Seeders\\ZarinpalPaymentGatewaySeeder
```

**روش B: استفاده از SQL Script**
```bash
# وارد کردن فایل SQL
mysql -u [username] -p [database_name] < /app/pixer-api/add_zarinpal_gateway.sql

# یا اجرای مستقیم
mysql -u [username] -p [database_name] -e "$(cat /app/pixer-api/add_zarinpal_gateway.sql)"
```

#### 1.2. تنظیمات .env

فایل `.env` در `/app/pixer-api/` را ویرایش کنید:

```env
# ZarinPal Payment Gateway
ZARINPAL_MERCHANT_ID=XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX
ZARINPAL_SANDBOX=true
ZARINPAL_CALLBACK_URL=https://yourdomain.com/zarinpal/callback
ZARINPAL_USE_TOMAN=false
ZARINPAL_PAYMENT_DESCRIPTION=پرداخت سفارش
```

**نکات مهم:**
- برای **تست**: `ZARINPAL_SANDBOX=true` و از Merchant ID تست استفاده کنید
- برای **تولید**: `ZARINPAL_SANDBOX=false` و Merchant ID واقعی از زرین‌پال دریافت کنید
- `ZARINPAL_USE_TOMAN`: 
  - `false` = مبالغ به ریال
  - `true` = مبالغ به تومان (سیستم خودکار × 10 می‌کند)

#### 1.3. دریافت Merchant ID

**برای Sandbox (تست):**
- Merchant ID: `XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX` (قابل استفاده برای تست)
- یا از [sandbox.zarinpal.com](https://sandbox.zarinpal.com) ثبت‌نام کنید

**برای Production:**
1. به [zarinpal.com](https://www.zarinpal.com) بروید
2. ثبت‌نام کنید و حساب پذیرنده ایجاد کنید
3. مدارک را ارسال کنید و تأیید دریافت کنید
4. از پنل پذیرنده، Merchant ID را کپی کنید

#### 1.4. Clear Cache

```bash
cd /app/pixer-api
php artisan config:clear
php artisan cache:clear
```

### مرحله 2: فعال‌سازی در پنل ادمین

1. وارد پنل ادمین شوید
2. به **Settings → Payment** بروید
3. **ZarinPal (زرین‌پال)** را از لیست انتخاب کنید
4. ذخیره کنید

---

## 🎯 نحوه استفاده

### جریان پرداخت (Payment Flow)

```
1. کاربر در Shop → Checkout → انتخاب ZarinPal
                                    ↓
2. Frontend → POST /api/payment-intent → Backend
                                    ↓
3. Backend → ZarinPal Request API
           ← دریافت Authority
                                    ↓
4. Backend → ذخیره Authority در payment_intents
           → بازگشت redirect_url
                                    ↓
5. Frontend → Redirect user → ZarinPal Gateway
                                    ↓
6. کاربر پرداخت می‌کند
                                    ↓
7. ZarinPal → Redirect → /zarinpal/callback?Authority=xxx&Status=OK
                                    ↓
8. Backend → ZarinPal Verify API
           ← تأیید پرداخت (RefID)
                                    ↓
9. Backend → Update Order Status → SUCCESS
                                    ↓
10. Frontend → نمایش پیام موفقیت → Order Details
```

---

## 🧪 تست کردن

### تست با Sandbox

1. مطمئن شوید `ZARINPAL_SANDBOX=true`
2. از Merchant ID تست استفاده کنید
3. در درگاه تست، هر شماره کارتی را وارد کنید
4. از کد CVV2 و تاریخ انقضا دلخواه استفاده کنید
5. رمز دوم: هر عددی (مثلاً 123456)

**کارت‌های تست:**
- شماره کارت: `6219-8619-8000-0000` (یا هر شماره ۱۶ رقمی)
- CVV2: هر ۳ یا ۴ رقم
- تاریخ انقضا: هر تاریخ آینده
- رمز دوم: هر عدد ۶ رقمی

### تست مبالغ مختلف

```php
// تست با ریال
ZARINPAL_USE_TOMAN=false
Amount: 100000 (صد هزار ریال)

// تست با تومان
ZARINPAL_USE_TOMAN=true
Amount: 10000 (ده هزار تومان = صد هزار ریال)
```

---

## 🔍 عیب‌یابی (Troubleshooting)

### خطاهای رایج

**1. خطای -10: مرچنت کد نامعتبر**
```
✅ راه حل: Merchant ID را در .env بررسی کنید
```

**2. خطای -11: مرچنت کد غیرفعال**
```
✅ راه حل: 
- در Sandbox: مطمئن شوید از Merchant ID صحیح استفاده می‌کنید
- در Production: با پشتیبانی زرین‌پال تماس بگیرید
```

**3. خطای -16: سطح تأیید پایین**
```
✅ راه حل: حساب پذیرنده باید حداقل سطح نقره‌ای داشته باشد
```

**4. خطای -50: مبلغ مطابقت ندارد**
```
✅ راه حل: مبلغ verify باید دقیقاً برابر با مبلغ request باشد
```

**5. اتصال به درگاه برقرار نمی‌شود**
```
✅ بررسی کنید:
- آیا سرور به اینترنت دسترسی دارد؟
- آیا Firewall port 443 را باز کرده است؟
- آیا SSL certificate صحیح است؟
```

### بررسی لاگ‌ها

```bash
# لاگ‌های Laravel
tail -f /app/pixer-api/storage/logs/laravel.log | grep -i zarinpal

# جستجوی خطاها
grep -r "ZarinPal" /app/pixer-api/storage/logs/
```

---

## 🌐 API Endpoints

### ZarinPal URLs

**Sandbox:**
- Request: `https://sandbox.zarinpal.com/pg/v4/payment/request.json`
- Gateway: `https://sandbox.zarinpal.com/pg/StartPay/{Authority}`
- Verify: `https://sandbox.zarinpal.com/pg/v4/payment/verify.json`

**Production:**
- Request: `https://payment.zarinpal.com/pg/v4/payment/request.json`
- Gateway: `https://payment.zarinpal.com/pg/StartPay/{Authority}`
- Verify: `https://payment.zarinpal.com/pg/v4/payment/verify.json`

---

## 📊 کدهای وضعیت (Status Codes)

| کد  | معنی |
|-----|------|
| 100 | تراکنش موفق |
| 101 | تراکنش قبلاً تأیید شده |
| -9  | خطای اعتبارسنجی |
| -10 | مرچنت کد یا IP نامعتبر |
| -11 | مرچنت کد غیرفعال |
| -12 | تلاش بیش از حد |
| -15 | ترمینال تعلیق شده |
| -16 | سطح تأیید پایین |
| -50 | عدم مطابقت مبلغ |
| -51 | پرداخت ناموفق |
| -54 | Authority نامعتبر |

---

## 💰 محاسبه مبالغ

```php
// ریال به تومان
$toman = $rial / 10;

// تومان به ریال
$rial = $toman * 10;

// در کد:
if (config('shop.zarinpal.use_toman')) {
    $amount_in_rial = $amount_in_toman * 10;
}
```

**نکته مهم:** زرین‌پال فقط ریال قبول می‌کند. حداقل مبلغ: ۱,۰۰۰ ریال.

---

## 🔒 امنیت

**توصیه‌های امنیتی:**

1. **Merchant ID را محرمانه نگه دارید**
2. **Callback URL را در زرین‌پال ثبت کنید** (برای جلوگیری از تقلب)
3. **همیشه verify را اجرا کنید** (Status=OK کافی نیست!)
4. **مبلغ را در verify بررسی کنید**
5. **Authority را ذخیره کنید** (برای ردیابی)
6. **از HTTPS استفاده کنید**
7. **لاگ تراکنش‌ها را نگه دارید**

---

## 📚 منابع اضافی

- [مستندات رسمی زرین‌پال](https://docs.zarinpal.com)
- [راهنمای REST API زرین‌پال](https://docs.zarinpal.com/paymentGateway/)
- [پنل پذیرندگان زرین‌پال](https://panel.zarinpal.com)
- [Sandbox زرین‌پال](https://sandbox.zarinpal.com)

---

## ✅ Checklist پس از نصب

- [ ] ZarinPal به دیتابیس اضافه شده است
- [ ] تنظیمات .env کامل شده است
- [ ] Merchant ID صحیح وارد شده است
- [ ] Callback URL صحیح است و publicly accessible است
- [ ] Cache Laravel پاک شده است
- [ ] در پنل ادمین ZarinPal قابل انتخاب است
- [ ] تست پرداخت در Sandbox موفق بوده است
- [ ] لاگ‌ها بررسی شده و خطایی وجود ندارد

---

## 🆘 پشتیبانی

در صورت مشکل:
1. ابتدا لاگ‌های Laravel را بررسی کنید
2. کدهای خطای زرین‌پال را با جدول بالا مقایسه کنید
3. مطمئن شوید تنظیمات .env صحیح است
4. با تیم پشتیبانی زرین‌پال تماس بگیرید: support@zarinpal.com

---

**تاریخ ایجاد:** 2025-01-XX
**نسخه:** 1.0.0
**سازگاری:** Pixer 6.9.0
