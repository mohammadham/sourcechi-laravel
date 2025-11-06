export const getShopUrl = (path: string): string => {
  const base = process.env.NEXT_PUBLIC_SHOP_URL ?? '';
  const cleanBase = base.replace(/\/$/, '');     // remove trailing slash
  const cleanPath = path.replace(/^\//, '');     // remove leading slash
    // If path is empty, return base without trailing slash
  if (!cleanPath || cleanPath === '') {
    return cleanBase || '/';
  }
  return `${cleanBase}/${cleanPath}`;
};