import matplotlib.pyplot as plt
import matplotlib.patches as mpatches
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch
import numpy as np

# Create figure
fig, ax = plt.subplots(1, 1, figsize=(16, 12))
ax.set_xlim(0, 16)
ax.set_ylim(0, 12)
ax.set_aspect('equal')
ax.axis('off')

# Title
ax.text(8, 11.5, 'Medicine Locator System Architecture', fontsize=20, fontweight='bold', 
        ha='center', va='center', color='#1e3a5f')
ax.text(8, 11, 'Bacolod City - 3rd Party SaaS Platform', fontsize=12, 
        ha='center', va='center', color='#666666')

# Colors
colors = {
    'user': '#3498db',
    'pharmacy': '#27ae60', 
    'admin': '#9b59b6',
    'frontend': '#e74c3c',
    'backend': '#f39c12',
    'database': '#1abc9c',
    'api': '#34495e'
}

def draw_box(ax, x, y, w, h, color, label, sublabel=None):
    box = FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.03,rounding_size=0.2",
                         facecolor=color, edgecolor='white', linewidth=2, alpha=0.9)
    ax.add_patch(box)
    ax.text(x + w/2, y + h/2 + (0.15 if sublabel else 0), label, fontsize=10, fontweight='bold',
            ha='center', va='center', color='white')
    if sublabel:
        ax.text(x + w/2, y + h/2 - 0.2, sublabel, fontsize=8,
                ha='center', va='center', color='white', alpha=0.9)

def draw_arrow(ax, start, end, color='#555555'):
    ax.annotate('', xy=end, xytext=start,
                arrowprops=dict(arrowstyle='->', color=color, lw=1.5))

# ============ USER ROLES (Top) ============
ax.text(8, 9.8, '👥 USER ROLES', fontsize=12, fontweight='bold', ha='center', color='#333')

# Guest User
draw_box(ax, 1, 8.5, 2.5, 1, colors['user'], '🔍 Guest User', 'Medicine Search')

# Pharmacy Admin  
draw_box(ax, 6.75, 8.5, 2.5, 1, colors['pharmacy'], '🏪 Pharmacy Admin', 'Manage Inventory')

# Super Admin
draw_box(ax, 12.5, 8.5, 2.5, 1, colors['admin'], '🛡️ Super Admin', 'Platform Owner')

# ============ FRONTEND (Middle-Top) ============
ax.text(8, 7.3, '🖥️ FRONTEND (Laravel Blade + Tailwind CSS)', fontsize=11, fontweight='bold', ha='center', color='#333')

draw_box(ax, 0.5, 6, 3, 0.9, colors['frontend'], 'Public Map View', 'Leaflet.js')
draw_box(ax, 4, 6, 3.5, 0.9, colors['frontend'], 'Portal Dashboard', '/portal/*')
draw_box(ax, 8.5, 6, 3.5, 0.9, colors['frontend'], 'Admin Dashboard', '/admin/*')
draw_box(ax, 12.5, 6, 3, 0.9, colors['frontend'], 'Auth Pages', 'Login/Register')

# ============ BACKEND (Middle) ============
ax.text(8, 5, '⚙️ BACKEND (Laravel 11 + PHP)', fontsize=11, fontweight='bold', ha='center', color='#333')

draw_box(ax, 0.5, 3.8, 2.5, 0.8, colors['backend'], 'PharmacySearch', 'Controller')
draw_box(ax, 3.5, 3.8, 2.5, 0.8, colors['backend'], 'Portal', 'Controller')
draw_box(ax, 6.5, 3.8, 2.5, 0.8, colors['backend'], 'Admin', 'Controller')
draw_box(ax, 9.5, 3.8, 2.5, 0.8, colors['backend'], 'Auth', 'Controller')
draw_box(ax, 12.5, 3.8, 2.5, 0.8, colors['api'], 'RoleMiddleware', 'Auth Guard')

# ============ MODELS ============
ax.text(8, 3, '📦 ELOQUENT MODELS', fontsize=11, fontweight='bold', ha='center', color='#333')

draw_box(ax, 2, 1.8, 2, 0.8, '#16a085', 'User', 'Model')
draw_box(ax, 5, 1.8, 2, 0.8, '#16a085', 'Pharmacy', 'Model')
draw_box(ax, 8, 1.8, 2, 0.8, '#16a085', 'Medicine', 'Model')
draw_box(ax, 11, 1.8, 2.5, 0.8, '#16a085', 'PharmacyMedicine', 'Pivot')

# ============ DATABASE (Bottom) ============
ax.text(8, 1, '🗄️ DATABASE (MySQL)', fontsize=11, fontweight='bold', ha='center', color='#333')

draw_box(ax, 1, 0.1, 2.3, 0.6, colors['database'], 'users', '')
draw_box(ax, 4, 0.1, 2.3, 0.6, colors['database'], 'pharmacies', '')
draw_box(ax, 7, 0.1, 2.3, 0.6, colors['database'], 'medicines', '')
draw_box(ax, 10, 0.1, 3, 0.6, colors['database'], 'pharmacy_medicine', '')
draw_box(ax, 13.5, 0.1, 2, 0.6, colors['database'], 'sessions', '')

# ============ ARROWS ============
# Users to Frontend
draw_arrow(ax, (2.25, 8.5), (2, 6.9), colors['user'])
draw_arrow(ax, (8, 8.5), (5.75, 6.9), colors['pharmacy'])
draw_arrow(ax, (13.75, 8.5), (10.25, 6.9), colors['admin'])

# Frontend to Backend
draw_arrow(ax, (2, 6), (1.75, 4.6), colors['frontend'])
draw_arrow(ax, (5.75, 6), (4.75, 4.6), colors['frontend'])
draw_arrow(ax, (10.25, 6), (7.75, 4.6), colors['frontend'])
draw_arrow(ax, (14, 6), (10.75, 4.6), colors['frontend'])

# Backend to Models
draw_arrow(ax, (4, 3.8), (3, 2.6), colors['backend'])
draw_arrow(ax, (6, 3.8), (6, 2.6), colors['backend'])
draw_arrow(ax, (9, 3.8), (9, 2.6), colors['backend'])

# Models to Database
draw_arrow(ax, (3, 1.8), (2.15, 0.7), '#16a085')
draw_arrow(ax, (6, 1.8), (5.15, 0.7), '#16a085')
draw_arrow(ax, (9, 1.8), (8.15, 0.7), '#16a085')
draw_arrow(ax, (12.25, 1.8), (11.5, 0.7), '#16a085')

# ============ LEGEND ============
legend_y = 10.3
ax.add_patch(FancyBboxPatch((0.3, legend_y-0.1), 0.3, 0.3, facecolor=colors['user'], edgecolor='none'))
ax.text(0.8, legend_y+0.05, 'Guest User', fontsize=8, va='center')

ax.add_patch(FancyBboxPatch((2.5, legend_y-0.1), 0.3, 0.3, facecolor=colors['pharmacy'], edgecolor='none'))
ax.text(3, legend_y+0.05, 'Pharmacy Admin', fontsize=8, va='center')

ax.add_patch(FancyBboxPatch((5, legend_y-0.1), 0.3, 0.3, facecolor=colors['admin'], edgecolor='none'))
ax.text(5.5, legend_y+0.05, 'Super Admin', fontsize=8, va='center')

ax.add_patch(FancyBboxPatch((7.3, legend_y-0.1), 0.3, 0.3, facecolor=colors['frontend'], edgecolor='none'))
ax.text(7.8, legend_y+0.05, 'Frontend', fontsize=8, va='center')

ax.add_patch(FancyBboxPatch((9.3, legend_y-0.1), 0.3, 0.3, facecolor=colors['backend'], edgecolor='none'))
ax.text(9.8, legend_y+0.05, 'Backend', fontsize=8, va='center')

ax.add_patch(FancyBboxPatch((11.3, legend_y-0.1), 0.3, 0.3, facecolor=colors['database'], edgecolor='none'))
ax.text(11.8, legend_y+0.05, 'Database', fontsize=8, va='center')

ax.add_patch(FancyBboxPatch((13.3, legend_y-0.1), 0.3, 0.3, facecolor=colors['api'], edgecolor='none'))
ax.text(13.8, legend_y+0.05, 'Middleware', fontsize=8, va='center')

# Features box
features_text = """KEY FEATURES:
• GPS-Enabled Pharmacy Locator (Haversine Formula)
• Real-Time Medicine Inventory Management
• Role-Based Access Control (RBAC)
• Pharmacy Registration & Profile Management
• Medicine Search with Proximity Filtering"""

ax.text(15.5, 4.5, features_text, fontsize=8, ha='right', va='top',
        bbox=dict(boxstyle='round', facecolor='#f8f9fa', edgecolor='#dee2e6'),
        family='monospace')

plt.tight_layout()
plt.savefig('c:/xampp/htdocs/medicine_locator_initial/public/system_architecture.png', 
            dpi=150, bbox_inches='tight', facecolor='white', edgecolor='none')
plt.savefig('c:/xampp/htdocs/medicine_locator_initial/storage/app/system_architecture.png', 
            dpi=150, bbox_inches='tight', facecolor='white', edgecolor='none')
print("System architecture diagram saved!")
print("Location: public/system_architecture.png")
