import { useSettings } from '@/data/settings';

interface EnamadBadgeProps {
  className?: string;
  location?: 'footer' | 'sidebar' | 'both';
}

export default function EnamadBadge({ 
  className = '', 
  location = 'footer' 
}: EnamadBadgeProps) {
  const { settings } = useSettings();
  const enamadConfig = settings?.options?.enamad;

  // Check if enamad is enabled
  if (!enamadConfig?.enabled) {
    return null;
  }

  // Check if should display in this location
  const shouldDisplay =
    enamadConfig.displayLocation === 'both' ||
    enamadConfig.displayLocation === location;

  if (!shouldDisplay) {
    return null;
  }

  // If code is provided, render it
  if (enamadConfig.code) {
    return (
      <div
        className={`enamad-badge ${className}`}
        dangerouslySetInnerHTML={{ __html: enamadConfig.code }}
      />
    );
  }

  // Fallback: render as link with image (if only link is provided)
  if (enamadConfig.link) {
    return (
      <a
        href={enamadConfig.link}
        target="_blank"
        rel="noopener noreferrer nofollow"
        className={`enamad-badge inline-block ${className}`}
        title="نماد اعتماد الکترونیکی"
      >
        <img
          src="https://trustseal.enamad.ir/logo.aspx"
          alt="نماد اعتماد الکترونیکی"
          className="max-w-full h-auto"
          style={{ cursor: 'pointer' }}
        />
      </a>
    );
  }

  return null;
}
