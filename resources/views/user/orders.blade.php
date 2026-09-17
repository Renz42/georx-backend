<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - GEORX: A Medicine Hub Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        geo: { 900: '#0F172A', 800: '#1E293B', 700: '#1F2E2C', 600: '#2F7E6A', 500: '#63C6A7', 400: '#BFE8D6', 300: '#A0D8C4', 100: '#E9F7F2', 50: '#F8FAFC' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 pb-20">

    <x-navbar />

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 space-y-4">

        @if($orders->isEmpty())
            <div class="py-20 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
                <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-clipboard-list text-4xl text-slate-300"></i>
                </div>
                <h3 class="text-2xl font-black text-slate-700 mb-2">No orders yet</h3>
                <p class="text-slate-500 mb-6">Start by finding a pharmacy and adding medicines to your cart.</p>
                <a href="{{ url('/') }}" class="inline-flex items-center justify-center px-6 py-3 text-base font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 transition shadow-lg shadow-blue-200 hover:-translate-y-0.5">
                    Browse Pharmacies
                </a>
            </div>
        @else
            @foreach($orders as $order)
                @php
                    $statusConfig = [
                        'pending_confirmation' => ['Pending Review', 'bg-purple-50 text-purple-700 border-purple-200', 'fas fa-clipboard-check'],
                        'pending' => ['Confirmed', 'bg-amber-50 text-amber-700 border-amber-200', 'fas fa-clock'],
                        'accepted' => ['Rider Assigned', 'bg-blue-50 text-blue-700 border-blue-200', 'fas fa-motorcycle'],
                        'at_pharmacy' => ['At Pharmacy', 'bg-indigo-50 text-indigo-700 border-indigo-200', 'fas fa-store'],
                        'picked_up' => ['On the Way', 'bg-violet-50 text-violet-700 border-violet-200', 'fas fa-shipping-fast'],
                        'delivered' => ['Delivered', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fas fa-check-double'],
                        'cancelled' => ['Cancelled', 'bg-slate-50 text-slate-700 border-slate-200', 'fas fa-ban'],
                        'rejected' => ['Rejected', 'bg-rose-50 text-rose-700 border-rose-200', 'fas fa-times-circle'],
                    ];
                    $cfg = $statusConfig[$order->status] ?? ['Unknown', 'bg-slate-50 text-slate-700 border-slate-200', 'fas fa-question'];
                @endphp
                <a href="{{ route('orders.show', $order->id) }}" class="block bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all overflow-hidden">
                    <div class="p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-center shrink-0">
                                <i class="fas fa-store-alt text-slate-400 text-lg"></i>
                            </div>
                            <div>
                                <h3 class="font-black text-slate-800">{{ $order->pharmacy->name }}</h3>
                                <p class="text-xs text-slate-500 font-medium mt-0.5">{{ $order->items->count() }} item(s) · {{ $order->created_at->format('M d, Y h:i A') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border {{ $cfg[1] }} flex items-center gap-1.5">
                                <i class="{{ $cfg[2] }}"></i> {{ $cfg[0] }}
                            </span>
                            <span class="font-black text-lg text-slate-800">₱{{ number_format($order->total_amount, 2) }}</span>
                            <i class="fas fa-chevron-right text-slate-300"></i>
                        </div>
                    </div>
                </a>
            @endforeach
        @endif

    </main>
</body>
</html>
