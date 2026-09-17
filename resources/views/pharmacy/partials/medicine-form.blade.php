<!-- Master Data Form: This information is shared globally across all pharmacies -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
    <div class="md:col-span-2">
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Trade Name (Brand Name)</label>
        <input type="text" name="brand_name" placeholder="e.g., Catapres" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-extrabold text-slate-900 text-sm">
    </div>

    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Generic Name *</label>
        <input type="text" name="generic_name" required placeholder="e.g., Clonidine" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-sm font-medium">
    </div>

    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Strength</label>
        <input type="text" name="strength" placeholder="e.g., 150 MCG" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-sm font-medium">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Drug Classification (Therapeutic Class)</label>
        <input type="text" name="drug_category" placeholder="e.g., Anti-hypertensive" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-sm font-medium">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Primary Use / Consumer Use Description</label>
        <textarea name="primary_use" rows="3" placeholder="e.g., Catapres works quickly to lower critically high blood pressure in an emergency hypertensive crisis." class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-sm font-medium"></textarea>
    </div>

    <div class="md:col-span-2">
        <div class="bg-blue-50 border border-blue-100 px-4 py-3 rounded-lg flex items-center justify-between">
            <span class="text-sm font-semibold text-blue-800">Is a Prescription Required? (Rx)</span>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="prescription_required" value="1" class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
        </div>
    </div>
</div>
