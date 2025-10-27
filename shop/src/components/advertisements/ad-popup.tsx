import { useEffect, useState } from 'react';
import AdBanner from './ad-banner';
import { useLocalStorage } from 'react-use';

export default function AdPopup() {
  const [showPopup, setShowPopup] = useState(false);
  const [popupShownToday, setPopupShownToday] = useLocalStorage<string>(
    'ad-popup-shown',
    ''
  );

  useEffect(() => {
    // Check if popup was already shown today
    const today = new Date().toDateString();
    if (popupShownToday !== today) {
      // Show popup after 5 seconds
      const timer = setTimeout(() => {
        setShowPopup(true);
        setPopupShownToday(today);
      }, 5000);

      return () => clearTimeout(timer);
    }
  }, [popupShownToday, setPopupShownToday]);

  const handleClose = () => {
    setShowPopup(false);
  };

  if (!showPopup) return null;

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
        <div className="bg-white rounded-lg overflow-hidden shadow-2xl">
          <AdBanner position="popup" />
        </div>
      </div>
    </div>
  );
}
