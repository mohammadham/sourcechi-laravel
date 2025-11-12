import { useMemo } from 'react';
import { siteSettings } from '@/settings/site.settings';
import { useSettings } from '@/contexts/settings.context';
export function formatPrice({
  amount,
  currencyCode,
  locale,
  fractions = 2,
}: {
  amount: number;
  currencyCode: string;
  locale: string;
  fractions: number;
}) {
  // تشخیص اینکه آیا عدد صحیح است یا نه
  const shouldHideDecimals = amount % 1 === 0;
  
  // اگر عدد صحیح است، اعشار را نمایش نده
  const actualFractions = shouldHideDecimals 
    ? 0 
    : (fractions > 20 || fractions < 0 || !fractions ? 2 : fractions);

  const formatCurrency = new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: currencyCode,
    minimumFractionDigits: actualFractions,
    maximumFractionDigits: actualFractions,
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
  fractions = 2,
}: {
  baseAmount: number;
  amount: number;
  currencyCode: string;
  locale: string;
  fractions: number;
}) {
  const hasDiscount = baseAmount < amount;
  const formatDiscount = new Intl.NumberFormat(locale, { style: 'percent' });
  const discount = hasDiscount
    ? formatDiscount.format((amount - baseAmount) / amount)
    : null;

  const price = formatPrice({ amount, currencyCode, locale, fractions });
  const basePrice = hasDiscount
    ? formatPrice({ amount: baseAmount, currencyCode, locale, fractions })
    : null;

  return { price, basePrice, discount };
}
type PriceProps = {
  amount: number;
  baseAmount?: number;
  currencyCode?: string;
};
export default function usePrice(data?: PriceProps | null) {
  const { currency, currencyOptions } = useSettings();
  const { formation, fractions } = currencyOptions;
  const { amount, baseAmount, currencyCode = currency } = data ?? {};
  const locale = formation ?? siteSettings.defaultLanguage;
  const value = useMemo(() => {
    if (typeof amount !== 'number' || !currencyCode) return '';

    return baseAmount
      ? formatVariantPrice({
          amount,
          baseAmount,
          currencyCode,
          locale,
          fractions,
        })
      : formatPrice({ amount, currencyCode, locale, fractions });
  }, [amount, baseAmount, currencyCode]);

  return typeof value === 'string'
    ? { price: value, basePrice: null, discount: null }
    : value;
}
