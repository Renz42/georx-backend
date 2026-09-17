-- ============================================================
-- GEORX — Supabase Phase 8: Mobile Backend & RBAC Security Policies
-- Run in: Supabase Dashboard -> SQL Editor
-- Enables Row Level Security (RLS) for PostgREST API Mobile Access
-- ============================================================

-- ------------------------------------------------------------
-- 1. Enable RLS on All Application Tables
-- ------------------------------------------------------------
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE pharmacies ENABLE ROW LEVEL SECURITY;
ALTER TABLE medicines ENABLE ROW LEVEL SECURITY;
ALTER TABLE pharmacy_medicine ENABLE ROW LEVEL SECURITY;
ALTER TABLE inventory_batches ENABLE ROW LEVEL SECURITY;
ALTER TABLE orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE order_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE reviews ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_favorites ENABLE ROW LEVEL SECURITY;
ALTER TABLE search_logs ENABLE ROW LEVEL SECURITY;

-- ------------------------------------------------------------
-- 2. Helper Functions for RBAC Check
-- ------------------------------------------------------------

-- Check if authenticated user is Super Admin
CREATE OR REPLACE FUNCTION is_super_admin() 
RETURNS BOOLEAN AS $$
BEGIN
  RETURN EXISTS (
    SELECT 1 FROM users 
    WHERE id::text = auth.uid()::text AND role = 'super_admin'
  );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Check if authenticated user is Pharmacy Admin for a specific pharmacy
CREATE OR REPLACE FUNCTION is_pharmacy_admin(target_pharmacy_id BIGINT) 
RETURNS BOOLEAN AS $$
BEGIN
  RETURN EXISTS (
    SELECT 1 FROM pharmacies 
    WHERE id = target_pharmacy_id AND user_id::text = auth.uid()::text
  ) OR is_super_admin();
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ------------------------------------------------------------
-- 3. RLS POLICIES FOR PUBLIC & CUSTOMERS
-- ------------------------------------------------------------

-- PHARMACIES: Public & Customers can view active approved pharmacies
CREATE POLICY "Public Read Approved Pharmacies" 
ON pharmacies FOR SELECT 
USING (is_active = true AND is_approved = true OR is_super_admin() OR user_id::text = auth.uid()::text);

-- MEDICINES: Public & Customers can view medicine catalog
CREATE POLICY "Public Read Medicines" 
ON medicines FOR SELECT 
USING (true);

-- INVENTORY (pharmacy_medicine): Public & Customers can view available stock
CREATE POLICY "Public Read Available Inventory" 
ON pharmacy_medicine FOR SELECT 
USING (is_available = true AND quantity_on_hand > 0 OR is_super_admin() OR EXISTS (
  SELECT 1 FROM pharmacies WHERE id = pharmacy_id AND user_id::text = auth.uid()::text
));

-- ORDERS: Customers can insert and read their own orders
CREATE POLICY "Customer Own Orders Read" 
ON orders FOR SELECT 
USING (user_id::text = auth.uid()::text OR is_super_admin() OR EXISTS (
  SELECT 1 FROM pharmacies WHERE id = pharmacy_id AND user_id::text = auth.uid()::text
));

CREATE POLICY "Customer Own Orders Create" 
ON orders FOR INSERT 
WITH CHECK (user_id::text = auth.uid()::text);

-- ORDER ITEMS: View items related to accessible orders
CREATE POLICY "Order Items Read Access" 
ON order_items FOR SELECT 
USING (EXISTS (
  SELECT 1 FROM orders WHERE id = order_id AND (
    user_id::text = auth.uid()::text OR is_super_admin() OR EXISTS (
      SELECT 1 FROM pharmacies WHERE id = pharmacy_id AND user_id::text = auth.uid()::text
    )
  )
));

CREATE POLICY "Order Items Create Access" 
ON order_items FOR INSERT 
WITH CHECK (EXISTS (
  SELECT 1 FROM orders WHERE id = order_id AND user_id::text = auth.uid()::text
));

-- MESSAGES: Users can view and send messages involving themselves
CREATE POLICY "Messages Participant Read" 
ON messages FOR SELECT 
USING (sender_id::text = auth.uid()::text OR receiver_id::text = auth.uid()::text OR is_super_admin());

CREATE POLICY "Messages Participant Create" 
ON messages FOR INSERT 
WITH CHECK (sender_id::text = auth.uid()::text);

-- NOTIFICATIONS: Users can read their own notifications
CREATE POLICY "Notifications Owner Read" 
ON notifications FOR SELECT 
USING (notifiable_id::text = auth.uid()::text OR is_super_admin());

-- REVIEWS: Public read, authenticated insert own
CREATE POLICY "Reviews Public Read" 
ON reviews FOR SELECT 
USING (true);

CREATE POLICY "Reviews Customer Insert" 
ON reviews FOR INSERT 
WITH CHECK (user_id::text = auth.uid()::text);

-- USER FAVORITES: Customers manage their own favorites
CREATE POLICY "Favorites Owner Full Access" 
ON user_favorites FOR ALL 
USING (user_id::text = auth.uid()::text);

-- SEARCH LOGS: Insert search logs
CREATE POLICY "Search Logs Public Insert" 
ON search_logs FOR INSERT 
WITH CHECK (true);

-- ------------------------------------------------------------
-- 4. RLS POLICIES FOR PHARMACY ADMINS
-- ------------------------------------------------------------

-- Pharmacy Admins can update their own pharmacy profile
CREATE POLICY "Pharmacy Admin Update Own Profile" 
ON pharmacies FOR UPDATE 
USING (user_id::text = auth.uid()::text OR is_super_admin());

-- Pharmacy Admins manage their own inventory
CREATE POLICY "Pharmacy Admin Inventory Manage" 
ON pharmacy_medicine FOR ALL 
USING (EXISTS (
  SELECT 1 FROM pharmacies WHERE id = pharmacy_id AND user_id::text = auth.uid()::text
) OR is_super_admin());

-- Pharmacy Admins manage their inventory batches
CREATE POLICY "Pharmacy Admin Batches Manage" 
ON inventory_batches FOR ALL 
USING (EXISTS (
  SELECT 1 FROM pharmacy_medicine pm 
  JOIN pharmacies p ON pm.pharmacy_id = p.id 
  WHERE pm.id = pharmacy_medicine_id AND p.user_id::text = auth.uid()::text
) OR is_super_admin());

-- ------------------------------------------------------------
-- 5. RLS POLICIES FOR SUPER ADMINS
-- ------------------------------------------------------------

CREATE POLICY "Super Admin Full Users Access" ON users FOR ALL USING (is_super_admin());
CREATE POLICY "Super Admin Full Medicines Access" ON medicines FOR ALL USING (is_super_admin());
