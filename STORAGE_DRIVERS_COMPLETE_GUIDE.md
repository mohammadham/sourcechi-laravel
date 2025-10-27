# راهنمای کامل سیستم Storage Drivers - فارسی

## 📦 معرفی

سیستم Storage Drivers یک راه‌حل پیشرفته برای مدیریت ذخیره‌سازی فایل‌ها در Pixer است که به شما امکان می‌دهد از درایورهای مختلف برای ذخیره‌سازی انواع مختلف فایل‌ها استفاده کنید.

### ویژگی‌ها:
- ✅ پشتیبانی از 4 درایور: **Local, Telegram, Google Drive, FTP**
- ✅ تخصیص درایور مختلف به انواع فایل (تصویر، ویدیو، فایل دیجیتال، سند)
- ✅ تست اتصال برای هر درایور
- ✅ Fallback خودکار به Local در صورت خطا
- ✅ مدیریت کامل از پنل ادمین

---

## 🏗️ معماری سیستم

### ساختار فایل‌ها:

```
Backend (Laravel):
├── Storage/
│   ├── StorageDriverInterface.php       # Interface اصلی
│   ├── BaseStorageDriver.php            # کلاس پایه
│   ├── StorageManager.php               # مدیریت درایورها
│   └── Drivers/
│       ├── LocalStorageDriver.php       # Local
│       ├── TelegramStorageDriver.php    # Telegram (MadelineProto)
│       ├── GoogleDriveStorageDriver.php # Google Drive (OAuth2)
│       └── FTPStorageDriver.php         # FTP (Flysystem)
├── Http/Controllers/
│   ├── AttachmentController.php         # آپلود/دانلود فایل
│   └── StorageController.php            # مدیریت درایورها
└── Database/
    ├── Models/Attachment.php            # Model فایل
    └── Migrations/..._add_storage_fields_to_attachments.php

Frontend (Admin Panel):
├── components/settings/storage/
│   ├── index.tsx                        # فرم تنظیمات
│   └── storage-validation-schema.ts    # Validation
└── public/locales/fa/form.json          # ترجمه‌های فارسی
```

---

## 🔧 نصب و راه‌اندازی

### گام 1: نصب Dependencies

```bash
cd /app/pixer-api
composer require danog/madelineproto:^8.0
composer require google/apiclient:^2.15
composer require league/flysystem-ftp:^3.0
```

### گام 2: اجرای Migration

```bash
php artisan migrate
```

این migration فیلدهای زیر را به جدول `attachments` اضافه می‌کند:
- `storage_driver` (string): نام درایور (local, telegram, google_drive, ftp)
- `storage_metadata` (json): اطلاعات اضافی فایل
- `file_type` (string): نوع فایل (image, video, digital_file, document)

### گام 3: تنظیم Environment Variables

فایل `.env` را باز کنید و تنظیمات را اضافه کنید:

```env
# Storage Configuration
STORAGE_DEFAULT_DRIVER=local
STORAGE_IMAGE_DRIVER=local
STORAGE_VIDEO_DRIVER=local
STORAGE_DIGITAL_FILE_DRIVER=local
STORAGE_DOCUMENT_DRIVER=local

# Telegram Storage (اختیاری)
TELEGRAM_STORAGE_ENABLED=false
TELEGRAM_API_ID=
TELEGRAM_API_HASH=
TELEGRAM_PHONE=
TELEGRAM_CHANNEL_ID=

# Google Drive Storage (اختیاری)
GOOGLE_DRIVE_ENABLED=false
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REFRESH_TOKEN=
GOOGLE_DRIVE_FOLDER_ID=root

# FTP Storage (اختیاری)
FTP_STORAGE_ENABLED=false
FTP_HOST=
FTP_USERNAME=
FTP_PASSWORD=
FTP_PORT=21
FTP_ROOT=/
FTP_SSL=false
FTP_BASE_URL=
```

### گام 4: Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
```

---

## 📚 راهنمای درایورها

### 1. Local Storage (پیش‌فرض)

**ویژگی‌ها:**
- ✅ همیشه فعال
- ✅ ذخیره‌سازی در `/storage/app/public/`
- ✅ دسته‌بندی خودکار: `images/`, `videos/`, `digital-files/`, `documents/`
- ✅ بدون نیاز به تنظیمات

**مزایا:**
- سرعت بالا
- بدون محدودیت
- بدون نیاز به اینترنت

**معایب:**
- محدود به فضای سرور
- بدون backup خودکار

---

### 2. Telegram Storage

**ویژگی‌ها:**
- 🚀 حداکثر حجم آپلود: **2GB** (با User Account)
- 🔐 استفاده از MadelineProto (نه Bot API)
- ✅ ذخیره‌سازی در کانال تلگرام
- ✅ احراز هویت: Phone → OTP → 2FA (اختیاری)

#### راه‌اندازی گام‌به‌گام:

**گام 1: دریافت API Credentials**

1. به [my.telegram.org](https://my.telegram.org) بروید
2. وارد حساب کاربری خود شوید
3. به بخش "API Development Tools" بروید
4. یک Application جدید ایجاد کنید
5. `API ID` و `API Hash` را کپی کنید

**گام 2: ایجاد کانال**

1. در تلگرام یک کانال **عمومی** ایجاد کنید
2. خودتان را به عنوان Admin اضافه کنید
3. نام کاربری کانال را یادداشت کنید (مثال: `@my_storage_channel`)
4. یا ID کانال را از ربات `@username_to_id_bot` دریافت کنید

**گام 3: تنظیمات در Admin Panel**

1. وارد **Admin Panel → Settings → Storage** شوید
2. **Telegram Storage** را فعال کنید
3. اطلاعات را وارد کنید:
   - **API ID**: از my.telegram.org
   - **API Hash**: از my.telegram.org
   - **Phone**: شماره تلفن (+989123456789)
   - **Channel ID**: @channel_username یا -100XXXXXXXXXX

4. دکمه **"احراز هویت"** را بزنید
5. کد ارسال شده به تلگرام را وارد کنید
6. در صورت فعال بودن 2FA، رمز دوم را وارد کنید
7. دکمه **"تست کانال"** را بزنید

**محدودیت‌ها:**
- فایل‌های آپلود شده در کانال باقی می‌مانند (حذف واقعی امکان‌پذیر نیست)
- نیاز به اتصال اینترنت پایدار

**مزایا:**
- فضای رایگان نامحدود
- سرعت بالا در ایران
- امنیت بالا

**استفاده‌های پیشنهادی:**
- ✅ فایل‌های دیجیتال محصولات (زیپ، نرم‌افزار)
- ✅ ویدیوهای آموزشی
- ✅ بک‌آپ فایل‌ها

---

### 3. Google Drive Storage

**ویژگی‌ها:**
- ☁️ فضای رایگان: 15GB
- 🔐 احراز هویت با OAuth2
- ✅ ذخیره‌سازی در پوشه مشخص
- ✅ لینک اشتراک‌گذاری عمومی

#### راه‌اندازی:

**گام 1: ایجاد Project در Google Cloud**

1. به [Google Cloud Console](https://console.cloud.google.com) بروید
2. یک Project جدید ایجاد کنید
3. **APIs & Services → Enable APIs** را باز کنید
4. **Google Drive API** را فعال کنید

**گام 2: ایجاد OAuth2 Credentials**

1. **APIs & Services → Credentials** را باز کنید
2. **Create Credentials → OAuth 2.0 Client ID** را انتخاب کنید
3. **Application Type**: Web Application
4. **Authorized redirect URIs**: 
   ```
   https://yourdomain.com/admin/settings/storage/google-drive/callback
   ```
5. **Client ID** و **Client Secret** را کپی کنید

**گام 3: دریافت Refresh Token**

1. در Admin Panel تنظیمات Google Drive را باز کنید
2. Client ID و Client Secret را وارد کنید
3. دکمه **"احراز هویت با Google"** را بزنید
4. به Google وارد شوید و دسترسی را تأیید کنید
5. Refresh Token به طور خودکار ذخیره می‌شود

**استفاده‌های پیشنهادی:**
- ✅ تصاویر محصولات
- ✅ اسناد و PDF
- ✅ بک‌آپ روزانه

---

### 4. FTP Storage

**ویژگی‌ها:**
- 🌐 اتصال به هاست خارجی
- 🔐 پشتیبانی از SSL/TLS
- ✅ سازگار با تمام سرورهای FTP

#### راه‌اندازی:

**تنظیمات مورد نیاز:**
- **Host**: آدرس سرور (ftp.example.com)
- **Username**: نام کاربری FTP
- **Password**: رمز عبور
- **Port**: 21 (FTP) یا 22 (SFTP)
- **Root**: پوشه ریشه (/)
- **SSL**: فعال/غیرفعال
- **Base URL**: آدرس عمومی فایل‌ها (https://files.example.com)

**مثال تنظیمات:**

```env
FTP_HOST=ftp.example.com
FTP_USERNAME=myuser
FTP_PASSWORD=mypassword
FTP_PORT=21
FTP_ROOT=/public_html/storage
FTP_SSL=true
FTP_BASE_URL=https://files.example.com/storage
```

**استفاده‌های پیشنهادی:**
- ✅ فایل‌های استاتیک (CSS, JS)
- ✅ تصاویر با ترافیک بالا
- ✅ ذخیره‌سازی در CDN

---

## 🎯 تخصیص درایور به نوع فایل

شما می‌توانید برای هر نوع فایل درایور جداگانه‌ای انتخاب کنید:

### مثال پیکربندی:

```php
'type_mapping' => [
    'image' => 'local',           // تصاویر در Local
    'video' => 'telegram',        // ویدیوها در Telegram (2GB)
    'digital_file' => 'telegram', // فایل‌های دیجیتال در Telegram
    'document' => 'google_drive', // اسناد در Google Drive
]
```

### انواع فایل‌ها:

| نوع | MIME Types | مثال |
|-----|-----------|------|
| `image` | image/* | JPG, PNG, GIF, WebP |
| `video` | video/* | MP4, AVI, MKV |
| `digital_file` | zip, rar, exe, dmg | فایل‌های محصولات |
| `document` | pdf, doc, xls | PDF, Word, Excel |

---

## 🔌 API Endpoints

### مدیریت درایورها

```http
GET /api/storage
```
دریافت لیست درایورها و تنظیمات

```http
POST /api/storage/test
Content-Type: application/json

{
  "driver": "telegram"
}
```
تست اتصال درایور

```http
POST /api/storage/config
Content-Type: application/json

{
  "storage": { ... }
}
```
به‌روزرسانی تنظیمات

### Telegram Authentication

```http
POST /api/storage/telegram/auth/start
Content-Type: application/json

{
  "phone": "+989123456789",
  "api_id": "12345",
  "api_hash": "abcd1234..."
}
```

```http
POST /api/storage/telegram/auth/verify
Content-Type: application/json

{
  "phone": "+989123456789",
  "code": "12345"
}
```

```http
POST /api/storage/telegram/auth/2fa
Content-Type: application/json

{
  "phone": "+989123456789",
  "password": "my2fapassword"
}
```

```http
POST /api/storage/telegram/test-channel
Content-Type: application/json

{
  "api_id": "12345",
  "api_hash": "abcd...",
  "phone": "+989123456789",
  "channel_id": "@my_channel"
}
```

### Google Drive OAuth

```http
POST /api/storage/google-drive/auth-url
Content-Type: application/json

{
  "client_id": "...",
  "client_secret": "...",
  "redirect_uri": "https://..."
}
```

```http
POST /api/storage/google-drive/exchange-code
Content-Type: application/json

{
  "code": "4/...",
  "client_id": "...",
  "client_secret": "...",
  "redirect_uri": "https://..."
}
```

---

## 💻 استفاده از Storage Manager در Code

### مثال 1: آپلود فایل

```php
use Marvel\Storage\StorageManager;

$manager = new StorageManager();

$result = $manager->upload(
    filePath: '/tmp/myfile.jpg',
    fileName: 'product-image.jpg',
    type: 'image'  // auto-selects driver based on type_mapping
);

if ($result['success']) {
    echo "File ID: " . $result['file_id'];
    echo "URL: " . $result['url'];
}
```

### مثال 2: دانلود فایل

```php
$result = $manager->download(
    fileId: 'images/product-123.jpg',
    localPath: '/tmp/download.jpg',
    driverName: 'local'
);
```

### مثال 3: دریافت URL فایل

```php
$url = $manager->getFileUrl(
    fileId: 'file_id_here',
    driverName: 'telegram',
    expiresIn: 3600  // 1 hour
);
```

### مثال 4: حذف فایل

```php
$result = $manager->delete(
    fileId: 'file_id',
    driverName: 'google_drive'
);
```

---

## 🧪 تست درایورها

### Local Storage Test

```bash
php artisan tinker

$manager = new \Marvel\Storage\StorageManager();
$result = $manager->testDriver('local');
print_r($result);
```

### Telegram Test

```bash
# ابتدا احراز هویت کنید
curl -X POST http://localhost/api/storage/telegram/auth/start \
  -H "Content-Type: application/json" \
  -d '{"phone": "+989123456789", "api_id": "12345", "api_hash": "..."}'

# کد را verify کنید
curl -X POST http://localhost/api/storage/telegram/auth/verify \
  -H "Content-Type: application/json" \
  -d '{"phone": "+989123456789", "code": "12345"}'

# تست کانال
curl -X POST http://localhost/api/storage/telegram/test-channel \
  -H "Content-Type: application/json" \
  -d '{"api_id": "12345", "api_hash": "...", "phone": "+989123456789", "channel_id": "@mychannel"}'
```

---

## 🔍 عیب‌یابی

### مشکل 1: Telegram - "Not authenticated"

**علت:** Session منقضی شده یا حذف شده

**راه‌حل:**
```bash
# حذف session و ورود مجدد
rm -rf /app/pixer-api/storage/app/telegram/
# سپس از پنل ادمین دوباره احراز هویت کنید
```

### مشکل 2: Google Drive - "Invalid refresh token"

**علت:** Token منقضی یا لغو شده

**راه‌حل:**
1. OAuth flow را دوباره انجام دهید
2. Refresh Token جدید دریافت کنید
3. در تنظیمات ذخیره کنید

### مشکل 3: FTP - "Connection timeout"

**علت:** Firewall یا Port بسته

**راه‌حل:**
```bash
# تست اتصال
telnet ftp.example.com 21

# بررسی SSL
openssl s_client -connect ftp.example.com:21 -starttls ftp
```

### مشکل 4: "Driver not configured"

**علت:** تنظیمات ناقص

**راه‌حل:**
1. بررسی کنید تمام فیلدهای الزامی پر شده باشند
2. Cache را پاک کنید: `php artisan config:clear`
3. تست اتصال را انجام دهید

---

## 📊 بهترین شیوه‌ها

### امنیت

1. **Credentials را در .env نگهداری کنید**
2. **Telegram Session files را backup بگیرید**
3. **Google Drive Refresh Token را امن نگه دارید**
4. **FTP از SSL استفاده کند**

### عملکرد

1. **فایل‌های کوچک (<1MB)**: Local
2. **فایل‌های متوسط (1-100MB)**: Google Drive
3. **فایل‌های بزرگ (100MB-2GB)**: Telegram
4. **فایل‌های استاتیک با ترافیک بالا**: FTP + CDN

### Backup

1. **Telegram**: فایل‌ها در کانال باقی می‌مانند (backup خودکار)
2. **Google Drive**: از Google Takeout استفاده کنید
3. **FTP**: backup منظم با rsync
4. **Local**: backup روزانه storage/app/public/

---

## 📝 Checklist نصب

- [ ] Dependencies نصب شده (composer require)
- [ ] Migration اجرا شده (php artisan migrate)
- [ ] Environment variables تنظیم شده (.env)
- [ ] Cache پاک شده (config:clear, cache:clear)
- [ ] Telegram authentication انجام شده (در صورت استفاده)
- [ ] Google Drive OAuth انجام شده (در صورت استفاده)
- [ ] FTP تست شده (در صورت استفاده)
- [ ] تخصیص درایورها تنظیم شده (type_mapping)
- [ ] تست آپلود/دانلود انجام شده
- [ ] Admin Panel تست شده

---

## 🆘 پشتیبانی

اگر مشکلی دارید:

1. لاگ‌های Laravel را بررسی کنید: `storage/logs/laravel.log`
2. Console browser را در Admin Panel بررسی کنید
3. تست اتصال را از API مستقیماً انجام دهید
4. مستندات رسمی کتابخانه‌ها را مطالعه کنید:
   - [MadelineProto Docs](https://docs.madelineproto.xyz/)
   - [Google Drive API](https://developers.google.com/drive)
   - [Flysystem Docs](https://flysystem.thephpleague.com/)

---

**نسخه:** 1.0.0  
**تاریخ:** 2025-01-XX  
**سازگاری:** Pixer 6.9.0
