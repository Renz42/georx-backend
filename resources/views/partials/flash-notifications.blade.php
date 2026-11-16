@php
    $flashItems = [];

    $flashDefinitions = [
        'success' => [
            'label' => 'Success',
            'auto_dismiss' => true,
            'duration_ms' => 4500,
            'role' => 'status',
            'live' => 'polite',
            'card_classes' => 'bg-white border-[#E2E8F0] text-[#0F172A]',
            'icon_wrap_classes' => 'bg-[#E9F7F2] text-[#2F7E6A] border-[#63C6A7]/40',
            'icon_class' => 'fas fa-check',
            'label_classes' => 'text-[#2F7E6A]',
            'bar_color' => '#2F7E6A',
        ],
        'info' => [
            'label' => 'Notice',
            'auto_dismiss' => true,
            'duration_ms' => 4500,
            'role' => 'status',
            'live' => 'polite',
            'card_classes' => 'bg-white border-[#E2E8F0] text-[#0F172A]',
            'icon_wrap_classes' => 'bg-sky-50 text-sky-600 border-sky-200/80',
            'icon_class' => 'fas fa-info',
            'label_classes' => 'text-sky-700',
            'bar_color' => '#0284C7',
        ],
        'warning' => [
            'label' => 'Attention',
            'auto_dismiss' => false,
            'duration_ms' => 0,
            'role' => 'alert',
            'live' => 'assertive',
            'card_classes' => 'bg-white border-amber-200 text-[#0F172A]',
            'icon_wrap_classes' => 'bg-amber-50 text-amber-600 border-amber-200',
            'icon_class' => 'fas fa-exclamation-triangle',
            'label_classes' => 'text-amber-700',
            'bar_color' => '#D97706',
        ],
        'error' => [
            'label' => 'Action Required',
            'auto_dismiss' => false,
            'duration_ms' => 0,
            'role' => 'alert',
            'live' => 'assertive',
            'card_classes' => 'bg-white border-rose-200 text-[#0F172A]',
            'icon_wrap_classes' => 'bg-rose-50 text-rose-600 border-rose-200',
            'icon_class' => 'fas fa-exclamation-circle',
            'label_classes' => 'text-rose-700',
            'bar_color' => '#E11D48',
        ],
    ];

    foreach ($flashDefinitions as $flashKey => $meta) {
        if (session()->has($flashKey)) {
            $rawMessage = session($flashKey);
            if (is_string($rawMessage) && trim($rawMessage) !== '') {
                $flashItems[] = array_merge($meta, [
                    'type' => $flashKey,
                    'message' => trim($rawMessage),
                ]);
            }
        }
    }

    $topOffsetClass = $topOffsetClass ?? 'top-[84px]';
@endphp

@if(count($flashItems) > 0)
    <div id="georxFlashNotificationContainer"
         class="fixed {{ $topOffsetClass }} right-4 left-4 sm:left-auto sm:right-6 z-[60] w-auto sm:w-[380px] max-w-[calc(100vw-2rem)] space-y-3 pointer-events-none"
         aria-label="Notifications">
        @foreach($flashItems as $index => $item)
            <div data-flash-toast
                 data-flash-type="{{ $item['type'] }}"
                 data-auto-dismiss="{{ $item['auto_dismiss'] ? '1' : '0' }}"
                 data-duration-ms="{{ $item['duration_ms'] }}"
                 role="{{ $item['role'] }}"
                 aria-live="{{ $item['live'] }}"
                 tabindex="0"
                 class="pointer-events-auto relative overflow-hidden rounded-2xl border {{ $item['card_classes'] }} shadow-[0_12px_30px_-8px_rgba(15,23,42,0.14)] transition-all duration-200 ease-out focus:outline-none focus:ring-2 focus:ring-[#2F7E6A]/30">
                <div class="p-4 flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl border {{ $item['icon_wrap_classes'] }} flex items-center justify-center shrink-0">
                        <i class="{{ $item['icon_class'] }} text-xs" aria-hidden="true"></i>
                    </div>

                    <div class="min-w-0 flex-1 pt-0.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider {{ $item['label_classes'] }} leading-none">
                            {{ $item['label'] }}
                        </p>
                        <p class="mt-1.5 text-sm font-medium text-[#0F172A] leading-snug break-words">
                            {{ $item['message'] }}
                        </p>
                    </div>

                    <button type="button"
                            data-flash-dismiss
                            aria-label="Dismiss notification"
                            class="w-7 h-7 rounded-lg text-[#64748B] hover:text-[#0F172A] hover:bg-slate-100 flex items-center justify-center transition-colors shrink-0 -mr-1 -mt-1 focus:outline-none focus:ring-2 focus:ring-[#2F7E6A]/30">
                        <i class="fas fa-times text-xs" aria-hidden="true"></i>
                    </button>
                </div>

                @if($item['auto_dismiss'])
                    <div class="h-1 w-full bg-slate-100 overflow-hidden" aria-hidden="true">
                        <div data-flash-progress
                             class="h-full w-full origin-left"
                             style="background-color: {{ $item['bar_color'] }};"></div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <script>
        (function () {
            if (window.__georxFlashInit) {
                return;
            }
            window.__georxFlashInit = true;

            function initGeorxFlashToasts() {
                const container = document.getElementById('georxFlashNotificationContainer');
                if (!container) {
                    return;
                }

                const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                const toasts = Array.from(container.querySelectorAll('[data-flash-toast]'));

                function dismissToast(toast) {
                    if (!toast || toast.dataset.dismissed === '1') {
                        return;
                    }
                    toast.dataset.dismissed = '1';

                    if (toast.__georxTimer) {
                        clearTimeout(toast.__georxTimer);
                        toast.__georxTimer = null;
                    }

                    if (reducedMotion) {
                        toast.remove();
                        if (!container.querySelector('[data-flash-toast]')) {
                            container.remove();
                        }
                        return;
                    }

                    toast.classList.add('opacity-0', '-translate-y-1.5');
                    setTimeout(function () {
                        toast.remove();
                        if (!container.querySelector('[data-flash-toast]')) {
                            container.remove();
                        }
                    }, 220);
                }

                toasts.forEach(function (toast) {
                    const closeBtn = toast.querySelector('[data-flash-dismiss]');
                    if (closeBtn) {
                        closeBtn.addEventListener('click', function () {
                            dismissToast(toast);
                        });
                    }

                    toast.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') {
                            dismissToast(toast);
                        }
                    });

                    if (toast.dataset.autoDismiss !== '1') {
                        return;
                    }

                    const totalMs = Math.max(500, parseInt(toast.dataset.durationMs || '4500', 10));
                    let remainingMs = totalMs;
                    let startedAt = null;
                    let isPaused = false;
                    const progressBar = toast.querySelector('[data-flash-progress]');

                    function startCountdown() {
                        if (toast.dataset.dismissed === '1' || isPaused) {
                            return;
                        }
                        startedAt = Date.now();
                        if (progressBar) {
                            if (reducedMotion) {
                                progressBar.style.width = ((remainingMs / totalMs) * 100) + '%';
                            } else {
                                progressBar.style.transition = 'none';
                                progressBar.style.width = ((remainingMs / totalMs) * 100) + '%';
                                void progressBar.offsetWidth;
                                progressBar.style.transition = 'width ' + remainingMs + 'ms linear';
                                progressBar.style.width = '0%';
                            }
                        }
                        toast.__georxTimer = setTimeout(function () {
                            dismissToast(toast);
                        }, remainingMs);
                    }

                    function pauseCountdown() {
                        if (toast.dataset.dismissed === '1' || isPaused || startedAt === null) {
                            return;
                        }
                        isPaused = true;
                        if (toast.__georxTimer) {
                            clearTimeout(toast.__georxTimer);
                            toast.__georxTimer = null;
                        }
                        const elapsed = Date.now() - startedAt;
                        remainingMs = Math.max(0, remainingMs - elapsed);
                        if (progressBar) {
                            const pct = Math.max(0, (remainingMs / totalMs) * 100);
                            progressBar.style.transition = 'none';
                            progressBar.style.width = pct + '%';
                        }
                    }

                    function resumeCountdown(event) {
                        if (event && event.relatedTarget && toast.contains(event.relatedTarget)) {
                            return;
                        }
                        if (toast.dataset.dismissed === '1' || !isPaused) {
                            return;
                        }
                        isPaused = false;
                        if (remainingMs <= 0) {
                            dismissToast(toast);
                            return;
                        }
                        startCountdown();
                    }

                    toast.addEventListener('mouseenter', pauseCountdown);
                    toast.addEventListener('mouseleave', resumeCountdown);
                    toast.addEventListener('focusin', pauseCountdown);
                    toast.addEventListener('focusout', resumeCountdown);

                    startCountdown();
                });
            }

            if (document.getElementById('georxFlashNotificationContainer')) {
                setTimeout(initGeorxFlashToasts, 0);
            } else if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initGeorxFlashToasts);
            } else {
                initGeorxFlashToasts();
            }
        })();
    </script>
@endif
