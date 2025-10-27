export const ZarinpalIcon = ({ ...props }) => {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width="80"
      height="24"
      viewBox="0 0 120 35"
      fill="none"
      {...props}
    >
      {/* ZarinPal Logo */}
      <g transform="translate(0, 0)">
        {/* Z Letter */}
        <path
          d="M5 8 L20 8 L5 22 L20 22"
          stroke="#20C05C"
          strokeWidth="2.5"
          fill="none"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        
        {/* Shield/Payment Icon */}
        <path
          d="M30 6 L38 6 L38 18 C38 22 34 25 34 25 C34 25 30 22 30 18 L30 6 Z"
          fill="#20C05C"
          stroke="#20C05C"
          strokeWidth="1"
        />
        <path
          d="M32 12 L34 14 L36 10"
          stroke="white"
          strokeWidth="1.5"
          fill="none"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        
        {/* Text "ZarinPal" */}
        <text
          x="42"
          y="19"
          fontFamily="Arial, sans-serif"
          fontSize="12"
          fontWeight="600"
          fill="#20C05C"
        >
          ZarinPal
        </text>
        
        {/* Persian text زرین‌پال */}
        <text
          x="85"
          y="19"
          fontFamily="Tahoma, Arial"
          fontSize="11"
          fill="#666"
          direction="rtl"
        >
          زرین‌پال
        </text>
      </g>
    </svg>
  );
};

export const ZarinpalIconDark = ({ ...props }) => {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width="80"
      height="24"
      viewBox="0 0 120 35"
      fill="none"
      {...props}
    >
      {/* ZarinPal Logo - Dark Version */}
      <g transform="translate(0, 0)">
        {/* Z Letter */}
        <path
          d="M5 8 L20 8 L5 22 L20 22"
          stroke="#20C05C"
          strokeWidth="2.5"
          fill="none"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        
        {/* Shield/Payment Icon */}
        <path
          d="M30 6 L38 6 L38 18 C38 22 34 25 34 25 C34 25 30 22 30 18 L30 6 Z"
          fill="#20C05C"
          stroke="#20C05C"
          strokeWidth="1"
        />
        <path
          d="M32 12 L34 14 L36 10"
          stroke="white"
          strokeWidth="1.5"
          fill="none"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        
        {/* Text "ZarinPal" */}
        <text
          x="42"
          y="19"
          fontFamily="Arial, sans-serif"
          fontSize="12"
          fontWeight="600"
          fill="#20C05C"
        >
          ZarinPal
        </text>
        
        {/* Persian text زرین‌پال */}
        <text
          x="85"
          y="19"
          fontFamily="Tahoma, Arial"
          fontSize="11"
          fill="#BBB"
          direction="rtl"
        >
          زرین‌پال
        </text>
      </g>
    </svg>
  );
};
