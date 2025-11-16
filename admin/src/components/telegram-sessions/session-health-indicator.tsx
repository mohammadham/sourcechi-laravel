import cn from 'classnames';

interface HealthIndicatorProps {
  score: number;
  className?: string;
  showLabel?: boolean;
}

export default function SessionHealthIndicator({
  score,
  className,
  showLabel = true,
}: HealthIndicatorProps) {
  const getHealthColor = (score: number) => {
    if (score >= 90) return 'bg-green-500';
    if (score >= 70) return 'bg-yellow-500';
    if (score >= 50) return 'bg-orange-500';
    return 'bg-red-500';
  };

  const getHealthText = (score: number) => {
    if (score >= 90) return 'سالم';
    if (score >= 70) return 'خوب';
    if (score >= 50) return 'متوسط';
    return 'ضعیف';
  };

  return (
    <div className={cn('flex items-center gap-2', className)}>
      <div className="flex items-center gap-1">
        <div className={cn('h-3 w-3 rounded-full', getHealthColor(score))} />
        <span className="text-sm font-medium">{score}%</span>
      </div>
      {showLabel && (
        <span className="text-xs text-gray-500">({getHealthText(score)})</span>
      )}
    </div>
  );
}
