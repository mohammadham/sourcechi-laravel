import {
  adminAndOwnerOnly,
  adminOnly,
  adminOwnerAndStaffOnly,
  ownerAndStaffOnly,
} from '@/utils/auth-utils';
import { Routes } from '@/config/routes';

// TODO : this area need to be checked in Pixer

export const siteSettings = {
  name: 'Pixer',
  description: '',
  logo: {
    url: '/logo.svg',
    alt: 'Pixer',
    href: '/',
    width: 138,
    height: 34,
  },
  collapseLogo: {
    url: '/collapse-logo.svg',
    alt: 'P',
    href: '/',
    width: 32,
    height: 32,
  },
  defaultLanguage: 'en',
  author: {
    name: 'RedQ',
    websiteUrl: 'https://redq.io',
    address: '',
  },
  headerLinks: [],
  authorizedLinks: [
    {
      href: Routes.profileUpdate,
      labelTransKey: 'authorized-nav-item-profile',
      icon: 'UserIcon',
      permission: adminOwnerAndStaffOnly,
    },
    {
      href: Routes.shop.create,
      labelTransKey: 'common:text-create-shop',
      icon: 'ShopIcon',
      permission: adminAndOwnerOnly,
    },

    {
      href: Routes.settings,
      labelTransKey: 'authorized-nav-item-settings',
      icon: 'SettingsIcon',
      permission: adminOnly,
    },
    {
      href: Routes.logout,
      labelTransKey: 'authorized-nav-item-logout',
      icon: 'LogOutIcon',
      permission: adminOwnerAndStaffOnly,
    },
  ],
  currencyCode: 'USD',
  sidebarLinks: {
    admin: {
      root: {
        href: Routes.dashboard,
        label: 'Main',
        icon: 'DashboardIcon',
        childMenu: [
          {
            href: Routes.dashboard,
            label: 'sidebar-nav-item-dashboard',
            icon: 'DashboardIcon',
          },
        ],
      },

      shop: {
        href: '',
        label: 'text-shop-management',
        icon: 'ShopIcon',
        childMenu: [
          {
            href: '',
            label: 'sidebar-nav-item-shops',
            icon: 'ShopIcon',
            childMenu: [
              {
                href: Routes.shop.list,
                label: 'text-all-shops',
                icon: 'MyShopIcon',
              },
              {
                href: Routes.shop.create,
                label: 'text-add-all-shops',
                icon: 'ShopIcon',
              },
              {
                href: Routes.newShops,
                label: 'text-inactive-shops',
                icon: 'MyShopIcon',
              },
            ],
          },
          {
            href: Routes.adminMyShops,
            label: 'sidebar-nav-item-my-shops',
            icon: 'MyShopIcon',
          },
          {
            href: Routes.ownershipTransferRequest.list,
            label: 'Shop Transfer Request',
            icon: 'MyShopIcon',
            permission: adminAndOwnerOnly,
          },
        ],
      },

      product: {
        href: '',
        label: 'text-product-management',
        icon: 'ProductsIcon',
        childMenu: [
          {
            href: '',
            label: 'sidebar-nav-item-products',
            icon: 'ProductsIcon',
            childMenu: [
              {
                href: Routes.product.list,
                label: 'text-all-products',
                icon: 'ProductsIcon',
              },

              {
                href: Routes.draftProducts,
                label: 'text-my-draft-products',
                icon: 'ProductsIcon',
              },
              {
                href: Routes.outOfStockOrLowProducts,
                label: 'text-all-out-of-stock',
                icon: 'ProductsIcon',
              },
            ],
          },
          {
            href: Routes.type.list,
            label: 'text-groups',
            icon: 'HomeIcon',
          },
          {
            href: Routes.productInventory,
            label: 'text-inventory',
            icon: 'InventoryIcon',
          },
          {
            href: Routes.category.list,
            label: 'sidebar-nav-item-categories',
            icon: 'CategoriesIcon',
          },
          {
            href: Routes.tag.list,
            label: 'sidebar-nav-item-tags',
            icon: 'TagIcon',
          },
        ],
      },

      financial: {
        href: '',
        label: 'text-e-commerce-management',
        icon: 'WithdrawIcon',
        childMenu: [
          {
            href: Routes.tax.list,
            label: 'sidebar-nav-item-taxes',
            icon: 'TaxesIcon',
          },
          {
            href: Routes.withdraw.list,
            label: 'sidebar-nav-item-withdraws',
            icon: 'WithdrawIcon',
          },
        ],
      },

      order: {
        href: Routes.order.list,
        label: 'text-order-management',
        icon: 'OrdersIcon',
        childMenu: [
          {
            href: Routes.order.list,
            label: 'sidebar-nav-item-orders',
            icon: 'OrdersIcon',
          },

          {
            href: Routes.transaction,
            label: 'text-transactions',
            icon: 'TransactionsIcon',
          },
        ],
      },

      layout: {
        href: '',
        label: 'text-page-control',
        icon: 'SettingsIcon',
        childMenu: [
          {
            href: '',
            label: 'text-faqs',
            icon: 'FaqIcon',
            childMenu: [
              {
                href: Routes.faqs.list,
                label: 'text-all-faqs',
                icon: 'FaqIcon',
              },
              {
                href: Routes.faqs.create,
                label: 'text-new-faq',
                icon: 'TypesIcon',
              },
            ],
          },

          {
            href: '',
            label: 'text-terms-conditions',
            icon: 'TermsIcon',
            childMenu: [
              {
                href: Routes.termsAndCondition.list,
                label: 'text-all-terms',
                icon: 'TermsIcon',
              },
              {
                href: Routes.termsAndCondition.create,
                label: 'text-new-terms',
                icon: 'TermsIcon',
              },
            ],
          },
          {
            href: Routes.becomeSeller,
            label: 'Become a seller Page',
            icon: 'TermsIcon',
          },
        ],
      },

      user: {
        href: '',
        label: 'text-user-control',
        icon: 'SettingsIcon',
        childMenu: [
          {
            href: Routes.user.list,
            label: 'text-all-users',
            icon: 'UsersIcon',
          },
          {
            href: Routes.adminList,
            label: 'text-admin-list',
            icon: 'AdminListIcon',
          },
          {
            href: '',
            label: 'text-vendors',
            icon: 'VendorsIcon',
            childMenu: [
              {
                href: Routes.vendorList,
                label: 'text-all-vendors',
                icon: 'UsersIcon',
              },
              {
                href: Routes.pendingVendorList,
                label: 'text-pending-vendors',
                icon: 'UsersIcon',
              },
            ],
          },
          {
            href: '',
            label: 'sidebar-nav-item-staffs',
            icon: 'StaffIcon',
            childMenu: [
              {
                href: Routes.myStaffs,
                label: 'sidebar-nav-item-my-staffs',
                icon: 'UsersIcon',
              },
              {
                href: Routes.vendorStaffs,
                label: 'sidebar-nav-item-vendor-staffs',
                icon: 'UsersIcon',
              },
            ],
          },
          {
            href: Routes.customerList,
            label: 'text-customers',
            icon: 'CustomersIcon',
          },
        ],
      },

      feedback: {
        href: '',
        label: 'text-feedback-control',
        icon: 'SettingsIcon',
        childMenu: [
          {
            href: Routes.reviews.list,
            label: 'sidebar-nav-item-reviews',
            icon: 'ReviewIcon',
          },
          {
            href: Routes.question.list,
            label: 'sidebar-nav-item-questions',
            icon: 'QuestionIcon',
          },
        ],
      },

      settings: {
        href: '',
        label: 'text-site-management',
        icon: 'SettingsIcon',
        childMenu: [
          {
            href: Routes.settings,
            label: 'sidebar-nav-item-settings',
            icon: 'SettingsIcon',
            childMenu: [
              {
                href: Routes.settings,
                label: 'text-general-settings',
                icon: 'SettingsIcon',
              },
              {
                href: Routes.paymentSettings,
                label: 'text-payment-settings',
                icon: 'RefundsIcon',
              },
              {
                href: Routes.seoSettings,
                label: 'text-seo-settings',
                icon: 'StoreNoticeIcon',
              },
              {
                href: Routes.eventSettings,
                label: 'text-events-settings',
                icon: 'RefundsIcon',
              },
              {
                href: Routes.shopSettings,
                label: 'text-shop-settings',
                icon: 'RefundsIcon',
              },
              {
                href: Routes?.maintenance,
                label: 'text-maintenance-settings',
                icon: 'InformationIcon',
              },
              {
                href: Routes?.companyInformation,
                label: 'text-company-settings',
                icon: 'InformationIcon',
              },
              {
                href: Routes?.promotionPopup,
                label: 'text-popup-settings',
                icon: 'InformationIcon',
              },
            ],
          },
        ],
      },
    },

    shop: {
      root: {
        href: '',
        label: 'text-main',
        icon: 'DashboardIcon',
        childMenu: [
          {
            href: (shop: string) => `${Routes.dashboard}${shop}`,
            label: 'sidebar-nav-item-dashboard',
            icon: 'DashboardIcon',
            permissions: adminOwnerAndStaffOnly,
          },
        ],
      },

      product: {
        href: '',
        label: 'text-product-management',
        icon: 'ProductsIcon',
        permissions: adminOwnerAndStaffOnly,
        childMenu: [
          {
            href: (shop: string) => `/${shop}${Routes.product.list}`,
            label: 'sidebar-nav-item-products',
            icon: 'ProductsIcon',
            childMenu: [
              {
                href: (shop: string) => `/${shop}${Routes.product.list}`,
                label: 'text-all-products',
                icon: 'ProductsIcon',
                permissions: adminOwnerAndStaffOnly,
              },
              {
                href: (shop: string) => `/${shop}${Routes.product.create}`,
                label: 'text-new-products',
                icon: 'ProductsIcon',
                permissions: adminOwnerAndStaffOnly,
              },
              {
                href: (shop: string) => `/${shop}${Routes.draftProducts}`,
                label: 'text-my-draft',
                icon: 'ProductsIcon',
                permissions: adminOwnerAndStaffOnly,
              },
              {
                href: (shop: string) =>
                  `/${shop}${Routes.outOfStockOrLowProducts}`,
                label: 'text-all-out-of-stock',
                icon: 'ProductsIcon',
                permissions: adminOwnerAndStaffOnly,
              },
            ],
          },
          {
            href: (shop: string) => `/${shop}${Routes.productInventory}`,
            label: 'text-inventory',
            icon: 'InventoryIcon',
            permissions: adminOwnerAndStaffOnly,
          },
        ],
      },

      financial: {
        href: '',
        label: 'text-financial-management',
        icon: 'WithdrawIcon',
        childMenu: [
          {
            href: (shop: string) => `/${shop}${Routes.withdraw.list}`,
            label: 'sidebar-nav-item-withdraws',
            icon: 'AttributeIcon',
            permissions: adminAndOwnerOnly,
          },
        ],
      },

      order: {
        href: '',
        label: 'text-order-management',
        icon: 'OrdersIcon',
        childMenu: [
          {
            href: (shop: string) => `/${shop}${Routes.order.list}`,
            label: 'sidebar-nav-item-orders',
            icon: 'OrdersIcon',
            permissions: adminOwnerAndStaffOnly,
          },
          {
            href: (shop: string) => `/${shop}${Routes.transaction}`,
            label: 'text-transactions',
            icon: 'CalendarScheduleIcon',
            permissions: adminAndOwnerOnly,
          },
        ],
      },

      feedback: {
        href: '',
        label: 'text-feedback-control',
        icon: 'SettingsIcon',
        childMenu: [
          {
            href: (shop: string) => `/${shop}${Routes.reviews.list}`,
            label: 'sidebar-nav-item-reviews',
            icon: 'ReviewIcon',
            permissions: adminAndOwnerOnly,
          },
          {
            href: (shop: string) => `/${shop}${Routes.question.list}`,
            label: 'sidebar-nav-item-questions',
            icon: 'QuestionIcon',
            permissions: adminAndOwnerOnly,
          },
        ],
      },

      user: {
        href: '',
        label: 'text-user-control',
        icon: 'SettingsIcon',
        childMenu: [
          {
            href: (shop: string) => `/${shop}${Routes.staff.list}`,
            label: 'sidebar-nav-item-staffs',
            icon: 'UsersIcon',
            permissions: adminAndOwnerOnly,
          },
        ],
      },

      layout: {
        href: '',
        label: 'text-page-management',
        icon: 'SettingsIcon',
        childMenu: [
          {
            href: (shop: string) => `/${shop}${Routes.faqs.list}`,
            label: 'text-faqs',
            icon: 'TypesIcon',
            permissions: adminOwnerAndStaffOnly,
          },
          {
            href: (shop: string) => `/${shop}${Routes.termsAndCondition.list}`,
            label: 'Terms And Conditions',
            icon: 'TypesIcon',
            permissions: adminAndOwnerOnly,
          },
        ],
      },
    },

    staff: {
      root: {
        href: '',
        label: 'text-main',
        icon: 'DashboardIcon',
        childMenu: [
          {
            href: (shop: string) => `${Routes.dashboard}${shop}`,
            label: 'sidebar-nav-item-dashboard',
            icon: 'DashboardIcon',
            permissions: adminOwnerAndStaffOnly,
          },
        ],
      },

      product: {
        href: '',
        label: 'text-product-management',
        icon: 'ProductsIcon',
        permissions: adminOwnerAndStaffOnly,
        childMenu: [
          {
            href: (shop: string) => `/${shop}${Routes.product.list}`,
            label: 'sidebar-nav-item-products',
            icon: 'ProductsIcon',
            childMenu: [
              {
                href: (shop: string) => `/${shop}${Routes.product.list}`,
                label: 'text-all-products',
                icon: 'ProductsIcon',
                permissions: adminOwnerAndStaffOnly,
              },
              {
                href: (shop: string) => `/${shop}${Routes.product.create}`,
                label: 'text-new-products',
                icon: 'ProductsIcon',
                permissions: adminOwnerAndStaffOnly,
              },
              {
                href: (shop: string) => `/${shop}${Routes.draftProducts}`,
                label: 'text-my-draft',
                icon: 'ProductsIcon',
                permissions: adminOwnerAndStaffOnly,
              },
              {
                href: (shop: string) =>
                  `/${shop}${Routes.outOfStockOrLowProducts}`,
                label: 'text-low-out-of-stock',
                icon: 'ProductsIcon',
                permissions: adminOwnerAndStaffOnly,
              },
            ],
          },
          {
            href: (shop: string) => `/${shop}${Routes.productInventory}`,
            label: 'text-inventory',
            icon: 'InventoryIcon',
            permissions: adminOwnerAndStaffOnly,
          },
        ],
      },

      order: {
        href: '',
        label: 'text-order-management',
        icon: 'OrdersIcon',
        childMenu: [
          {
            href: (shop: string) => `/${shop}${Routes.order.list}`,
            label: 'sidebar-nav-item-orders',
            icon: 'OrdersIcon',
            permissions: adminOwnerAndStaffOnly,
          },
        ],
      },
    },

    ownerDashboard: [
      {
        href: Routes.dashboard,
        label: 'sidebar-nav-item-dashboard',
        icon: 'DashboardIcon',
        permissions: ownerAndStaffOnly,
      },
      {
        href: Routes?.ownerDashboardMyShop,
        label: 'common:sidebar-nav-item-my-shops',
        icon: 'MyShopOwnerIcon',
        permissions: ownerAndStaffOnly,
      },
      {
        href: Routes?.ownerDashboardShopTransferRequest,
        label: 'Shop Transfer Request',
        icon: 'MyShopIcon',
        permissions: adminAndOwnerOnly,
      },
    ],
  },
  product: {
    placeholder: '/product-placeholder.svg',
  },
  avatar: {
    placeholder: '/avatar-placeholder.svg',
  },
};

export const socialIcon = [
  {
    value: 'FacebookIcon',
    label: 'Facebook',
  },
  {
    value: 'InstagramIcon',
    label: 'Instagram',
  },
  {
    value: 'TwitterIcon',
    label: 'Twitter',
  },
  {
    value: 'YouTubeIcon',
    label: 'Youtube',
  },
];
