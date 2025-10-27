import Link from '@/components/ui/link';
import routes from '@/config/routes';
import { useSettings } from '@/data/settings';
import cn from 'classnames';
import EnamadBadge from '@/components/enamad/enamad-badge';

export default function Copyright({ className }: { className?: string }) {
  const currentYear = new Date().getFullYear();
  const { settings } = useSettings();
  return (
    <div className={cn('flex flex-col items-center justify-center gap-4', className)}>
      <EnamadBadge location="footer" className="mb-3" />
      <span className="tracking-[0.2px] text-center">
        ©{currentYear}{' '}
        <Link
          className="text-heading font-medium hover:text-brand-dark"
          href={settings?.siteLink ?? routes?.home}
          target="_blank"
        >
          {settings?.siteTitle}
        </Link>
        . {settings?.copyrightText}{' '}
        {settings?.externalText ? (
          <Link
            className="text-heading font-medium hover:text-brand-dark"
            href={settings?.externalLink ?? routes?.home}
            target="_blank"
          >
            {settings?.externalText}
          </Link>
        ) : (
          ''
        )}
      </span>
    </div>
  );
}
