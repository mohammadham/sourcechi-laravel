import { useEffect, useState } from 'react';
import AdBanner from './ad-banner';
import { useLocalStorage } from 'react-use';
import Cookies from 'js-cookie';
import { NEWSLETTER_POPUP_MODAL_KEY } from '@/lib/constants';

export default function AdPopup() {
  const [showPopup, setShowPopup] = useState(false);
  const [hasAds, setHasAds] = useState(false);
  const [popupShownToday, setPopupShownToday] = useLocalStorage<string>(
    'ad-popup-shown',
    ''
  );

  // Check if there are ads for popup position
  useEffect(() => {
    const checkAdsExist = async () => {
      try {
        const response = await fetch(
          `${process.env.NEXT_PUBLIC_REST_API_ENDPOINT}/advertisements/position/popup`
        );
        const data = await response.json();
        const adsExist = Array.isArray(data) && data.length > 0;
        setHasAds(adsExist);

        console.log('[Ad Popup] Ads exist for popup:', adsExist);
      } catch (error) {
        console.error('[Ad Popup] Failed to check ads:', error);
        setHasAds(false);
      }
    };

    checkAdsExist();
  }, []);

  useEffect(() => {
    // Only show popup if:
    // 1. Ads exist for popup position
    // 2. Popup was not shown today
    // 3. Promo popup has been seen (to avoid conflict)
    if (!hasAds) {
      console.log('[Ad Popup] No ads available, skipping');
      return;
    }

    const today = new Date().toDateString();
    const seenPromoPopup = Cookies.get(NEWSLETTER_POPUP_MODAL_KEY);

    if (popupShownToday === today) {
      console.log('[Ad Popup] Already shown today, skipping');
      return;
    }

    // Delay calculation:
    // - If promo popup was seen, show ad popup after 3 seconds
    // - If no promo popup, show ad popup after 8 seconds
    const delay = seenPromoPopup ? 3000 : 8000;

    console.log('[Ad Popup] Scheduling popup with delay:', delay, 'ms');

    const timer = setTimeout(() => {
      setShowPopup(true);
      setPopupShownToday(today);
      console.log('[Ad Popup] Showing popup now');
    }, delay);

    return () => clearTimeout(timer);
  }, [hasAds, popupShownToday, setPopupShownToday]);

  const handleClose = () => {
    console.log('[Ad Popup] User closed popup');
    setShowPopup(false);
  };

  // Don't render anything if:
  // - No ads available
  // - Popup should not be shown
  if (!hasAds || !showPopup) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 backdrop-blur-sm">
      <div className="relative max-w-4xl mx-auto p-4">
        {/* Close button */}
        <button
          onClick={handleClose}
          className="absolute -top-2 -right-2 z-10 flex items-center justify-center w-10 h-10 bg-white rounded-full shadow-lg hover:bg-gray-100 transition-colors"
          aria-label="بستن تبلیغ"
        >
          <svg
            className="w-6 h-6 text-gray-700"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>

        {/* Ad content */}
        <div className="bg-white rounded-lg overflow-hidden shadow-2xl max-h-[90vh] overflow-y-auto">
          <AdBanner position="popup" onLoaded={() => console.log('[Ad Popup] Ad content loaded')} />
        </div>
      </div>
    </div>
  );
}
