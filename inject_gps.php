<?php
$files = [
    __DIR__ . '/resources/views/public/medicine_details.blade.php',
    __DIR__ . '/resources/views/public/pharmacy_catalog.blade.php'
];

$jsBlock = <<<EOD
    <!-- GPS Restriction Logic -->
    @php
        \$latSetting = \\App\\Models\\GlobalSetting::where('key', 'service_center_lat')->first();
        \$lngSetting = \\App\\Models\\GlobalSetting::where('key', 'service_center_lng')->first();
        \$radiusSetting = \\App\\Models\\GlobalSetting::where('key', 'service_radius_km')->first();

        \$serviceCenterLat = \$latSetting ? \$latSetting->value : 10.640739;
        \$serviceCenterLng = \$lngSetting ? \$lngSetting->value : 122.968262;
        \$serviceRadiusKm = \$radiusSetting ? \$radiusSetting->value : 5;
    @endphp
    
    <div id="gpsBanner" class="hidden fixed top-16 left-0 right-0 bg-rose-600 text-white text-center py-3 px-4 shadow-md z-[60] font-medium text-sm">
        <i class="fas fa-map-marker-alt mr-2"></i>
        Online ordering is currently available only within Barangay Alijis and its <strong>{{ \$serviceRadiusKm }}km</strong> service radius.
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const serviceCenterLat = {{ \$serviceCenterLat }};
            const serviceCenterLng = {{ \$serviceCenterLng }};
            const serviceRadiusKm = {{ \$serviceRadiusKm }};
            
            // Haversine formula to calculate distance in km
            function calculateDistance(lat1, lon1, lat2, lon2) {
                const R = 6371; // km
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                          Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                          Math.sin(dLon/2) * Math.sin(dLon/2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                return R * c;
            }

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const distance = calculateDistance(
                        position.coords.latitude, 
                        position.coords.longitude, 
                        serviceCenterLat, 
                        serviceCenterLng
                    );
                    
                    if (distance > serviceRadiusKm) {
                        // User is outside service radius
                        document.getElementById('gpsBanner').classList.remove('hidden');
                        
                        // Disable order/cart buttons
                        document.querySelectorAll('button[onclick^="addToCart"], button[type="submit"]').forEach(btn => {
                            if (btn.innerText.includes('Add to Cart') || btn.innerText.includes('Reserve Now')) {
                                btn.disabled = true;
                                btn.classList.add('opacity-50', 'cursor-not-allowed');
                                btn.removeAttribute('onclick');
                                btn.onclick = function(e) {
                                    e.preventDefault();
                                    alert('Ordering is currently disabled in your area. We only serve within ' + serviceRadiusKm + 'km of Barangay Alijis.');
                                };
                            }
                        });
                    }
                }, error => {
                    console.log("GPS Error:", error);
                }, { enableHighAccuracy: true });
            }
        });
    </script>
</body>
EOD;

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'GPS Restriction Logic') === false) {
            $content = str_replace('</body>', $jsBlock, $content);
            file_put_contents($file, $content);
            echo "Updated $file\n";
        }
    }
}
?>
