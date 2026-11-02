@props(['status'])
<span @class(['rounded px-2 py-0.5 text-xs font-medium',
    'bg-blue-50 text-blue-800' => $status === \Modules\Bcm\Domain\Enums\ExerciseStatus::Planned,
    'bg-amber-50 text-amber-800' => $status === \Modules\Bcm\Domain\Enums\ExerciseStatus::InProgress,
    'bg-green-50 text-green-800' => $status === \Modules\Bcm\Domain\Enums\ExerciseStatus::Completed,
    'bg-gray-100 text-gray-600' => $status === \Modules\Bcm\Domain\Enums\ExerciseStatus::Cancelled])>{{ $status->label() }}</span>
