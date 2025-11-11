import { useEffect, useState } from 'react';
import cn from 'classnames';

interface AdBannerProps {
  position: 'header' | 'sidebar' | 'footer' | 'between_products' | 'product_detail' | 'popup';
  className?: string;
  onLoaded?: () => void;
}

interface Advertisement {
  id: number;
  title: string;
  type: 'image' | 'video' | 'html';
  media_url?: string;
  html_code?: string;
  target_url?: string;
  open_in_new_tab: boolean;
  width?: number;
  height?: number;
}

export default function AdBanner({ position, className, onLoaded }: AdBannerProps) {
  const [ads, setAds] = useState<Advertisement[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentAdIndex, setCurrentAdIndex] = useState(0);

  useEffect(() => {
    const fetchAds = async () => {
      try {
        const response = await fetch(
          `${process.env.NEXT_PUBLIC_REST_API_ENDPOINT}/advertisements/position/${position}`
        );
        const data = await response.json();
        const adsArray = Array.isArray(data) ? data : [];
        setAds(adsArray);
        
        console.log(`[AdBanner:${position}] Loaded ${adsArray.length} ads`);
        
        if (onLoaded && adsArray.length > 0) {
          onLoaded();
        }
      } catch (error) {
        console.error(`[AdBanner:${position}] Failed to fetch advertisements:`, error);
        setAds([]);
      } finally {
        setLoading(false);
      }
    };

    fetchAds();
  }, [position, onLoaded]);

  // Rotate ads every 10 seconds if multiple ads exist
  useEffect(() => {
    if (ads.length <= 1) return;

    const interval = setInterval(() => {
      setCurrentAdIndex((prev) => (prev + 1) % ads.length);
    }, 10000);

    return () => clearInterval(interval);
  }, [ads.length]);

  if (loading || ads.length === 0) {
    return null;
  }

  const currentAd = ads[currentAdIndex];

  const handleClick = () => {
    if (currentAd.target_url) {
      if (currentAd.open_in_new_tab) {
        window.open(currentAd.target_url, '_blank', 'noopener,noreferrer');
      } else {
        window.location.href = currentAd.target_url;
      }
    }
  };

  const renderAd = () => {
    switch (currentAd.type) {
      case 'image':
        return (
          <div
            className={cn(
              'ad-banner-container',
              currentAd.target_url && 'cursor-pointer',
              className
            )}
            onClick={handleClick}
            role={currentAd.target_url ? 'button' : undefined}
            tabIndex={currentAd.target_url ? 0 : undefined}
          >
            <img
              src={currentAd.media_url}
              alt={currentAd.title}
              className="w-full h-auto object-contain"
              loading="lazy"
            />
          </div>
        );

      case 'video':
        return (
          <div className={cn('ad-banner-container', className)}>
            <video
              src={currentAd.media_url}
              controls={false}
              autoPlay
              muted
              loop
              playsInline
              className="w-full h-auto object-contain"
            >
              Your browser does not support the video tag.
            </video>
            {currentAd.target_url && (
              <div
                className="absolute inset-0 cursor-pointer"
                onClick={handleClick}
                role="button"
                tabIndex={0}
              />
            )}
          </div>
        );

      case 'html':
        return (
          <div
            className={cn('ad-banner-container', className)}
            dangerouslySetInnerHTML={{ __html: currentAd.html_code || '' }}
          />
        );

      default:
        return null;
    }
  };

  // Position-specific styling
  // Note: For 'popup' position, we don't add positioning classes
  // because the parent (AdPopup) handles the overlay and positioning
  const positionClasses = {
    header: 'my-4 max-w-screen-xl mx-auto',
    sidebar: 'my-4',
    footer: 'my-4 max-w-screen-xl mx-auto',
    between_products: 'my-6 max-w-screen-xl mx-auto',
    product_detail: 'my-4',
    popup: '', // Parent handles positioning for popup
  };

  return (
    <div className={cn('ad-banner', positionClasses[position])}>
      {renderAd()}
      
      {/* Indicators for multiple ads */}
      {ads.length > 1 && position !== 'popup' && (
        <div className="flex justify-center gap-2 mt-2">
          {ads.map((_, index) => (
            <button
              key={index}
              onClick={() => setCurrentAdIndex(index)}
              className={cn(
                'w-2 h-2 rounded-full transition-colors',
                index === currentAdIndex
                  ? 'bg-accent'
                  : 'bg-gray-300 hover:bg-gray-400'
              )}
              aria-label={`نمایش تبلیغ ${index + 1}`}
            />
          ))}
        </div>
      )}
    </div>
  );
}
