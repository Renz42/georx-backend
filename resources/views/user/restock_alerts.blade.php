<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Restock Alerts - GEORX</title>
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

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fas fa-bell text-indigo-500"></i> My Restock Alerts
                </h1>
                <p class="text-sm text-slate-500 mt-1">Manage notifications for medicines you are waiting to be restocked.</p>
            </div>
            <a href="{{ url('/') }}" class="hidden sm:inline-flex items-center gap-2 text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 px-4 py-2.5 rounded-xl transition shadow-sm">
                <i class="fas fa-search text-blue-500"></i> Find Medicines
            </a>
        </div>

        <!-- Notification Banner Container -->
        <div id="toast-banner" class="hidden p-4 rounded-xl text-sm font-bold shadow-sm transition-all duration-300"></div>

        @if($alerts->isEmpty())
            <div class="py-20 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
                <div class="w-24 h-24 bg-indigo-50 text-indigo-500 rounded-full flex items-center justify-center mx-auto mb-6 text-4xl">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3 class="text-2xl font-black text-slate-700 mb-2">No active restock alerts</h3>
                <p class="text-slate-500 mb-6 max-w-md mx-auto">When a medicine is out of stock at your preferred pharmacy, click <strong>"Notify Me When Restocked"</strong> to get notified here automatically.</p>
                <a href="{{ url('/') }}" class="inline-flex items-center justify-center px-6 py-3 text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                    <i class="fas fa-clinic-medical mr-2"></i> Browse Pharmacies
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4" id="alerts-container">
                @foreach($alerts as $alert)
                    <div id="alert-card-{{ $alert->id }}" class="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl {{ $alert->is_in_stock ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200' }} flex items-center justify-center shrink-0 font-bold text-xl">
                                <i class="fas {{ $alert->is_in_stock ? 'fa-check-circle' : 'fa-box' }}"></i>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <h3 class="font-extrabold text-slate-900 text-base">
                                        {{ $alert->medicine->brand_name ?? $alert->medicine->generic_name }}
                                    </h3>
                                    @if($alert->medicine->brand_name && $alert->medicine->generic_name)
                                        <span class="text-xs font-semibold text-slate-400">({{ $alert->medicine->generic_name }})</span>
                                    @endif
                                </div>
                                <p class="text-xs font-bold text-slate-600 flex items-center gap-1.5 mb-2">
                                    <i class="fas fa-store text-indigo-500"></i> {{ $alert->pharmacy->name }}
                                    <span class="text-slate-300">•</span>
                                    <span class="text-slate-400 font-normal">Created {{ $alert->created_at->diffForHumans() }}</span>
                                </p>
                                
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $alert->is_in_stock ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200' }}">
                                        <i class="fas {{ $alert->is_in_stock ? 'fa-store-alt' : 'fa-exclamation-circle' }} mr-1 text-[10px]"></i>
                                        {{ $alert->is_in_stock ? "Back in Stock ({$alert->live_stock} available)" : 'Currently Out of Stock' }}
                                    </span>
                                    @if($alert->live_price)
                                        <span class="text-xs font-black text-slate-700">₱{{ number_format($alert->live_price, 2) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 sm:self-center pt-2 sm:pt-0 border-t sm:border-0 border-slate-100">
                            @if($alert->is_in_stock)
                                <a href="{{ url('/pharmacy/' . $alert->pharmacy_id . '/medicine/' . $alert->medicine_id) }}" class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition">
                                    <i class="fas fa-shopping-cart mr-1"></i> Order Now
                                </a>
                            @endif
                            
                            <button onclick="removeAlert({{ $alert->pharmacy_id }}, {{ $alert->medicine_id }}, {{ $alert->id }})" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 border border-slate-200 hover:border-rose-200 transition">
                                <i class="fas fa-trash-alt mr-1"></i> Remove Alert
                            </button>
                        </div>

                    </div>
                @endforeach
            </div>
        @endif

    </main>

    <script>
        function showToast(msg, isSuccess = true) {
            const toast = document.getElementById('toast-banner');
            toast.className = `p-4 rounded-xl text-sm font-bold shadow-sm border ${isSuccess ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'}`;
            toast.innerHTML = `<i class="fas ${isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i> ${msg}`;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 4000);
        }

        function removeAlert(pharmacyId, medicineId, cardId) {
            if (!confirm('Are you sure you want to remove this restock alert?')) return;

            fetch('/stock-alerts', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    pharmacy_id: pharmacyId,
                    medicine_id: medicineId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Stock alert removed.');
                    const card = document.getElementById(`alert-card-${cardId}`);
                    if (card) {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.95)';
                        setTimeout(() => card.remove(), 300);
                    }
                } else {
                    showToast(data.message || 'Failed to remove alert.', false);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('An error occurred. Please try again.', false);
            });
        }
    </script>

</body>
</html>
