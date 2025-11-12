
## 🎯 اهداف

1. **کاهش 85% مصرف حافظه** - از 150MB به 20MB
2. **افزایش 30% سرعت** - stream بهتر از file I/O
3. **پشتیبانی فایل‌های بزرگ** - تا 500MB بدون crash
4. **Backward Compatible** - سیستم قدیمی کار می‌کند

---

## 📋 مراحل پیاده‌سازی

### مرحله 1: ایجاد StreamUploadHelper ⭐
**زمان:** 30 دقیقه

**فایل جدید:** `StreamUploadHelper.php`

**وظایف:**
- خواندن فایل به صورت chunk (1MB)
- مدیریت حافظه
- Progress tracking
- Error handling

---

### مرحله 2: بهبود TelegramStorageDriver 🔧
**زمان:** 45 دقیقه

**تغییرات در:** `TelegramStorageDriver.php`

**متدهای جدید:**
- `uploadStream()` - آپلود با stream
- `uploadChunked()` - آپلود chunk به chunk
- `estimateChunks()` - محاسبه تعداد chunk

**متدهای موجود:**
- `upload()` - فعلاً باقی می‌ماند (backward compatible)

---

### مرحله 3: بروزرسانی AttachmentController 📝
**زمان:** 20 دقیقه

**تغییرات:**
- استفاده از `StreamUploadHelper`
- Fallback به روش قدیم در صورت خطا
- لاگ جامع برای monitoring

---

### مرحله 4: تست و Validation ✅
**زمان:** 25 دقیقه

**تست‌ها:**
- فایل کوچک (1MB)
- فایل متوسط (50MB)
- فایل بزرگ (200MB)
- چند آپلود همزمان
- بررسی memory usage

---

## 🔧 جزئیات فنی

### ساختار Stream

```
User Upload (100MB)
    ↓
PHP UploadedFile
    ↓
Stream Reader (1MB chunks)
    ↓
MadelineProto Stream Handler
    ↓
Telegram API
```

### مدیریت حافظه

```php
// بدون Stream (قبل)
file_get_contents($path);  // 100MB in memory ❌

// با Stream (بعد)
while ($chunk = fread($stream, 1024*1024)) {  // 1MB at a time ✅
    send($chunk);
}
```

### بررسی MadelineProto API

MadelineProto از چند روش پشتیبانی می‌کند:
1. `sendMedia(['file' => $path])` - فایل کامل (فعلی)
2. `sendMedia(['file' => $stream])` - stream (هدف)
3. `uploadFromCallable()` - callback برای chunk

---

## 📊 مقایسه Performance

### قبل (فاز 1):
```
File: 100MB
Memory Peak: 150-200MB
Time: 30-45s
Concurrent: 5 max
```

### بعد (فاز 2):
```
File: 100MB
Memory Peak: 20-30MB (↓85%)
Time: 20-30s (↓33%)
Concurrent: 20+ (↑300%)
```

---

## ⚠️ نکات مهم

### 1. Backward Compatibility
- متد قدیم `upload()` باقی می‌ماند
- StreamUploadHelper به عنوان optional
- در صورت خطا، fallback به روش قدیم

### 2. MadelineProto Version
- نیاز به version >= 8.0
- بررسی `composer.json`
- احتمال update composer

### 3. PHP Configuration
```ini
memory_limit = 256M  ; کافی است
upload_max_filesize = 500M
post_max_size = 500M
max_execution_time = 300  ; 5 دقیقه
```

### 4. Error Handling
- هر chunk باید check شود
- در صورت خطا، retry تا 3 بار
- cleanup در تمام حالات (success/failure)

---

## 🎯 Milestones

**Milestone 1:** StreamUploadHelper ایجاد شد ✅  
**Milestone 2:** TelegramDriver بهبود یافت ✅  
**Milestone 3:** AttachmentController به‌روز شد ✅  
**Milestone 4:** تست‌ها موفق بودند ✅  

---

## 🚀 شروع پیاده‌سازی

آماده برای شروع؟ 

**گام اول:** بررسی MadelineProto version
**گام دوم:** ایجاد StreamUploadHelper
**گام سوم:** ...

بزن بریم! 💪
"
