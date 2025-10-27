# راهنمای نصب و استفاده از اینماد (e-Namad)

## 📦 فایل‌های اضافه شده

### Frontend - Admin Panel

**1. فرم تنظیمات:**
- `/app/admin/src/components/settings/enamad/index.tsx`
  - فرم کامل تنظیمات اینماد
  - فیلدها: فعال/غیرفعال، کد HTML، لینک، محل نمایش
  - پیش‌نمایش زنده

**2. Validation Schema:**
- `/app/admin/src/components/settings/enamad/enamad-validation-schema.ts`
  - اعتبارسنجی فیلدها
  - کد اینماد الزامی در صورت فعال بودن
  - بررسی URL معتبر

**3. Translations:**
- `/app/admin/public/locales/fa/form.json` (به‌روزرسانی شده)
  - 17 ترجمه جدید برای فرم اینماد

### Frontend - Shop Panel

**1. کامپوننت نمایش:**
- `/app/shop/src/components/enamad/enamad-badge.tsx`
  - نمایش بج اینماد
  - پشتیبانی از کد HTML
  - Fallback به لینک و تصویر

**2. Layout Updates:**
- `/app/shop/src/layouts/_copyright.tsx` (به‌روزرسانی شده)
  - اینماد به Footer اضافه شد

**3. Translations:**
- `/app/shop/public/locales/fa/common.json` (به‌روزرسانی شده)
  - 2 ترجمه جدید

---

## 🔧 راهنمای تنظیمات

### مرحله 1: دریافت کد اینماد

1. به سایت [enamad.ir](https://enamad.ir) بروید
2. ثبت‌نام کنید و حساب کاربری ایجاد کنید
3. اطلاعات فروشگاه و مدارک را ارسال کنید
4. پس از تأیید، به پنل کاربری بروید
5. از بخش "دریافت کد" کد HTML را کپی کنید

**نمونه کد اینماد:**
```html
<a referrerpolicy="origin" target="_blank" href="https://trustseal.enamad.ir/?id=XXXXX&Code=XXXXX">
  <img referrerpolicy="origin" src="https://trustseal.enamad.ir/logo.aspx?id=XXXXX&Code=XXXXX" 
       alt="" style="cursor:pointer" code="XXXXX">
</a>
```

### مرحله 2: تنظیمات در پنل ادمین

1. وارد **Admin Panel** شوید
2. به **Settings** بروید
3. تب **اینماد (e-Namad)** را انتخاب کنید
4. تنظیمات را به شرح زیر انجام دهید:

**فیلدها:**

| فیلد | توضیحات | الزامی |
|------|---------|--------|
| فعال‌سازی | فعال/غیرفعال کردن نمایش اینماد | خیر |
| کد HTML | کد کامل HTML دریافتی از enamad.ir | بله (در صورت فعال بودن) |
| لینک تأیید | لینک صفحه تأیید اینماد | خیر |
| محل نمایش | Footer / Sidebar / هر دو | خیر (پیش‌فرض: Footer) |

5. **ذخیره** کنید

### مرحله 3: بررسی نمایش در فروشگاه

1. به **Shop** بروید
2. به انتهای صفحه (Footer) بروید
3. باید بج اینماد را ببینید
4. روی بج کلیک کنید تا به صفحه تأیید enamad.ir هدایت شوید

---

## 🎨 محل‌های نمایش

### 1. Footer (پیش‌فرض)
```tsx
// در Copyright component
<EnamadBadge location="footer" />
```

اینماد در انتهای تمام صفحات نمایش داده می‌شود.

### 2. Sidebar (اختیاری)
```tsx
// در هر Sidebar component
<EnamadBadge location="sidebar" />
```

برای فعال‌سازی، در پنل ادمین محل نمایش را روی "نوار کناری" یا "هر دو" تنظیم کنید.

### 3. سفارشی‌سازی CSS

می‌توانید ظاهر اینماد را با CSS سفارشی‌سازی کنید:

```css
/* در global styles */
.enamad-badge {
  /* استایل‌های دلخواه */
  max-width: 120px;
  display: inline-block;
}

.enamad-badge img {
  width: 100%;
  height: auto;
}
```

---

## 📊 ساختار داده در Backend

تنظیمات اینماد در `settings.options.enamad` ذخیره می‌شود:

```json
{
  "options": {
    "enamad": {
      "enabled": true,
      "code": "<a referrerpolicy=\"origin\" target=\"_blank\" href=\"https://trustseal.enamad.ir/?id=XXXXX&Code=XXXXX\">...</a>",
      "link": "https://trustseal.enamad.ir/?id=XXXXX&Code=XXXXX",
      "displayLocation": "footer"
    }
  }
}
```

**فیلدها:**
- `enabled` (boolean): فعال/غیرفعال
- `code` (string): کد HTML کامل
- `link` (string, optional): لینک تأیید
- `displayLocation` (string): `footer` | `sidebar` | `both`

---

## 🔍 عیب‌یابی

### مشکل 1: اینماد نمایش داده نمی‌شود

**راه حل:**
1. مطمئن شوید در پنل ادمین فعال است (`enabled: true`)
2. بررسی کنید کد HTML به درستی وارد شده است
3. Cache مرورگر را پاک کنید
4. Console مرورگر را برای خطاها بررسی کنید

### مشکل 2: کد HTML کار نمی‌کند

**راه حل:**
1. مطمئن شوید کد کامل (شامل `<a>` و `<img>`) کپی شده است
2. از enamad.ir کد جدید دریافت کنید
3. مطمئن شوید اکانت شما در enamad.ir تأیید شده است

### مشکل 3: لینک به صفحه 404 می‌رود

**راه حل:**
1. لینک را در پنل enamad.ir بررسی کنید
2. مطمئن شوید ID و Code صحیح هستند
3. با پشتیبانی enamad.ir تماس بگیرید

---

## 🎯 بهترین شیوه‌ها

### 1. امنیت
- ✅ همیشه از کد رسمی enamad.ir استفاده کنید
- ✅ به‌طور دوره‌ای اعتبار اینماد را بررسی کنید
- ✅ از تغییر دستی کد خودداری کنید

### 2. عملکرد
- ✅ اینماد را به‌صورت lazy load بارگذاری کنید
- ✅ از cache مرورگر استفاده کنید
- ✅ اندازه تصویر را بهینه کنید

### 3. UX
- ✅ اینماد را در مکان قابل مشاهده قرار دهید
- ✅ از tooltip برای توضیح استفاده کنید
- ✅ در موبایل اندازه مناسب داشته باشد

---

## 📱 نمایش واکنش‌گرا (Responsive)

کامپوننت اینماد به‌طور خودکار responsive است:

```tsx
// اندازه‌های مختلف
<EnamadBadge 
  className="
    w-20 sm:w-24 md:w-28 lg:w-32
    mx-auto
  " 
/>
```

---

## 🔗 منابع

- [سایت رسمی اینماد](https://enamad.ir)
- [راهنمای ثبت‌نام](https://enamad.ir/DomainRegistration/VerifyDomain)
- [سوالات متداول](https://enamad.ir/faq)
- [پشتیبانی](https://enamad.ir/Support)

---

## ✅ Checklist پس از نصب

- [ ] اینماد در enamad.ir دریافت شده
- [ ] کد HTML در پنل ادمین وارد شده
- [ ] تنظیمات ذخیره شده
- [ ] اینماد در Footer نمایش داده می‌شود
- [ ] لینک به صفحه تأیید enamad.ir هدایت می‌کند
- [ ] در موبایل و دسکتاپ به درستی نمایش داده می‌شود
- [ ] اعتبار اینماد معتبر است

---

**تاریخ ایجاد:** 2025-01-XX  
**نسخه:** 1.0.0  
**سازگاری:** Pixer 6.9.0
