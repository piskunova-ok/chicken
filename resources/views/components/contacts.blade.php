{{--
    Контактные данные. Пока значения не подтверждены, они остаются
    плейсхолдерами и выводятся текстом — без нерабочих tel:/mailto:.
    Логику берёт из App\Support\ContactLinks (общую с подвалом).
--}}
@php
    use App\Support\ContactLinks;

    $phone = ContactLinks::value('phone');
    $email = ContactLinks::value('email');
    $phoneUrl = ContactLinks::phoneUrl();
    $emailUrl = ContactLinks::emailUrl();
@endphp

<ul {{ $attributes->merge(['class' => 'flex flex-col gap-3 text-body']) }}>
    <li>
        @if ($phoneUrl !== null)
            <a href="{{ $phoneUrl }}" class="text-primary no-underline transition-colors duration-200 hover:text-primary-dark hover:underline">
                {{ $phone }}
            </a>
        @else
            <span data-placeholder class="text-ink-muted">{{ $phone }}</span>
        @endif
    </li>
    <li>
        @if ($emailUrl !== null)
            <a href="{{ $emailUrl }}" class="text-primary no-underline transition-colors duration-200 hover:text-primary-dark hover:underline">
                {{ $email }}
            </a>
        @else
            <span data-placeholder class="text-ink-muted">{{ $email }}</span>
        @endif
    </li>
</ul>
