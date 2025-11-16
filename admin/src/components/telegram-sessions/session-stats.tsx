import Card from '@/components/common/card';
import { useTelegramSessionStatsQuery } from '@/data/telegram-session';
import { useTranslation } from 'next-i18next';
import Loader from '@/components/ui/loader/loader';
import ErrorMessage from '@/components/ui/error-message';

interface StatCardProps {
  title: string;
  value: number | string;
  icon?: React.ReactNode;
  color?: string;
}

function StatCard({ title, value, icon, color = 'text-accent' }: StatCardProps) {
  return (
    <div className="flex items-center gap-4 rounded-lg border border-gray-200 bg-white p-4">
      {icon && <div className={cn('text-2xl', color)}>{icon}</div>}
      <div>
        <p className="text-sm text-gray-500">{title}</p>
        <p className="text-2xl font-bold">{value}</p>
      </div>
    </div>
  );
}

import cn from 'classnames';

interface SessionStatsProps {
  onRefresh?: () => void;
}

export default function SessionStats({ onRefresh }: SessionStatsProps) {
  const { t } = useTranslation('common');
  const { stats, loading, error, refetch } = useTelegramSessionStatsQuery();

  const handleRefresh = () => {
    refetch();
    onRefresh?.();
  };

  if (loading) return <Loader text={t('text-loading')} />;
  if (error) return <ErrorMessage message={error.message} />;
  if (!stats) return null;

  return (
    <Card className="mb-8">
      <div className="flex items-center justify-between mb-6">
        <h3 className="text-lg font-semibold">
          {t('telegram-sessions-overview')}
        </h3>
        <button
          onClick={handleRefresh}
          className="flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-semibold text-white transition duration-200 hover:bg-accent-hover"
        >
          <svg
            className="h-4 w-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
            />
          </svg>
          {t('text-refresh')}
        </button>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <StatCard
          title={t('telegram-total-sessions')}
          value={stats.total_sessions}
          icon="📊"
          color="text-blue-500"
        />
        <StatCard
          title={t('telegram-active-sessions')}
          value={stats.active_sessions}
          icon="✅"
          color="text-green-500"
        />
        <StatCard
          title={t('telegram-healthy-sessions')}
          value={stats.healthy_sessions}
          icon="❤️"
          color="text-red-500"
        />
        <StatCard
          title={t('telegram-active-downloads')}
          value={stats.total_active_downloads}
          icon="⬇️"
          color="text-purple-500"
        />
        <StatCard
          title={t('telegram-downloads-today')}
          value={stats.total_downloads_today?.toLocaleString() || 0}
          icon="📈"
          color="text-orange-500"
        />
      </div>
    </Card>
  );
}
