import { useSettings } from '@/data/settings';
import { useRouter } from 'next/router';
import { useMemo } from 'react';

export function formatPrice({
  amount,
  currencyCode,
  locale,
}: {
  amount: number;
  currencyCode: string;
  locale: string;
}) {
  // تشخیص تعداد رقم اعشار بر اساس مقدار
  // اگر عدد صحیح است، اعشار نمایش نده
  const fractionDigits = amount % 1 === 0 ? 0 : 2;

  const formatCurrency = new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: currencyCode,
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  });

  let formattedPrice = formatCurrency.format(amount);

  // جایگزینی IRR با \"ریال\" در زبان فارسی
  if (currencyCode === 'IRR' && locale === 'fa') {
    // IRR ممکن است به صورت ﷼ (نماد ریال) یا IRR نمایش داده شود
    formattedPrice = formattedPrice.replace(/IRR|﷼/g, 'ریال');
  }

  return formattedPrice;
}

export function formatVariantPrice({
  amount,
  baseAmount,
  currencyCode,
  locale,
}: {
  baseAmount: number;
  amount: number;
  currencyCode: string;
  locale: string;
}) {
  const hasDiscount = baseAmount > amount;
  const formatDiscount = new Intl.NumberFormat(locale, { style: 'percent' });
  const discount = hasDiscount
    ? formatDiscount.format((baseAmount - amount) / baseAmount)
    : null;

  const price = formatPrice({ amount, currencyCode, locale });
  const basePrice = hasDiscount
    ? formatPrice({ amount: baseAmount, currencyCode, locale })
    : null;

  return { price, basePrice, discount };
}

export default function usePrice(
  data?: {
    amount: number;
    baseAmount?: number;
    currencyCode?: string;
  } | null
) {
  const { settings } = useSettings();
  const { locale: currentLocale } = useRouter();
  const {
    amount,
    baseAmount,
    currencyCode = settings?.currency ?? 'USD',
  } = data ?? {};
  const value = useMemo(() => {
    if (typeof amount !== 'number' || !currencyCode) return '';
    const locale = currentLocale ?? 'en';
    return baseAmount
      ? formatVariantPrice({ amount, baseAmount, currencyCode, locale })
      : formatPrice({ amount, currencyCode, locale });
  }, [amount, baseAmount, currencyCode, currentLocale]);
  return typeof value === 'string'
    ? { price: value, basePrice: null, discount: null }
    : value;
}
