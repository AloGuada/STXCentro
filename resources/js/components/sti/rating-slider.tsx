import { Star } from 'lucide-react';
import { cn } from '@/lib/utils';

type RatingSliderProps = {
    value: number | null;
    onChange: (value: number) => void;
    disabled?: boolean;
    max?: number;
    size?: 'sm' | 'md' | 'lg';
};

export function RatingSlider({ value, onChange, disabled = false, max = 5, size = 'md' }: RatingSliderProps) {
    const sizeClasses = {
        sm: 'size-4',
        md: 'size-6',
        lg: 'size-8',
    };

    return (
        <div className="flex items-center gap-1">
            {Array.from({ length: max }, (_, i) => i + 1).map((rating) => (
                <button
                    key={rating}
                    type="button"
                    disabled={disabled}
                    onClick={() => onChange(rating)}
                    className={cn(
                        'transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded',
                        disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:scale-110'
                    )}
                >
                    <Star
                        className={cn(
                            sizeClasses[size],
                            'transition-colors',
                            value && rating <= value
                                ? 'fill-yellow-400 text-yellow-400'
                                : 'fill-transparent text-gray-300 dark:text-gray-600'
                        )}
                    />
                </button>
            ))}
            {value && (
                <span className="ml-2 text-sm text-gray-500">
                    {value}/{max}
                </span>
            )}
        </div>
    );
}
