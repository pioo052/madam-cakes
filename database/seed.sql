-- ============================================================================
--  MADAM CAKES  —  Initial data (seed)
-- ----------------------------------------------------------------------------
--  WHAT IS SEEDED
--    * 10 cake categories
--    * custom-cake designer options (owner sets prices later)
--    * layer counts (owner sets prices later)
--    * business settings (blank contact details for the owner to complete)
--    * editable website copy
--
--  WHAT IS DELIBERATELY NOT SEEDED
--    * products, cake prices, delivery fees, coupons, orders, reviews, gallery
--      => these are the owner's business decisions and real customer data.
-- ============================================================================

SET NAMES utf8mb4;

INSERT INTO app_meta (meta_key, meta_value) VALUES
  ('schema_version', '1.0.0'),
  ('installed_at',   NOW())
ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value);

-- ---------------------------------------------------------------- categories
INSERT INTO categories (name, slug, description, sort_order, is_active) VALUES
  ('Birthday Cakes',       'birthday-cakes',      'Joyful birthday cakes designed around the person being celebrated.', 10, 1),
  ('Wedding Cakes',        'wedding-cakes',       'Elegant tiered wedding cakes, finished to your chosen theme.',      20, 1),
  ('Anniversary Cakes',    'anniversary-cakes',   'Romantic designs to mark the years you have shared.',               30, 1),
  ('Graduation Cakes',     'graduation-cakes',    'Celebrate achievement with a cake worth the milestone.',            40, 1),
  ('Baby Shower Cakes',    'baby-shower-cakes',   'Soft, sweet designs for a new arrival on the way.',                 50, 1),
  ('Corporate Cakes',      'corporate-cakes',     'Branded cakes and dessert tables for companies and events.',        60, 1),
  ('Cupcakes',             'cupcakes',            'Beautifully finished cupcakes, sold by the box.',                   70, 1),
  ('Chocolate Cakes',      'chocolate-cakes',     'Rich chocolate cakes for the true chocolate lover.',                80, 1),
  ('Custom Cakes',         'custom-cakes',        'One-of-a-kind cakes built from your own idea or photograph.',       90, 1),
  ('Special Occasion Cakes','special-occasion-cakes','Cakes for every other reason to gather and celebrate.',          100, 1);

-- ------------------------------------------------- custom cake: occasion
INSERT INTO cake_options (group_key, name, price, sort_order, is_active) VALUES
  ('occasion', 'Birthday',      0.00, 10, 1),
  ('occasion', 'Wedding',       0.00, 20, 1),
  ('occasion', 'Graduation',    0.00, 30, 1),
  ('occasion', 'Anniversary',   0.00, 40, 1),
  ('occasion', 'Baby Shower',   0.00, 50, 1),
  ('occasion', 'Corporate Event',0.00, 60, 1),
  ('occasion', 'Other',         0.00, 70, 1);

-- ---------------------------------------------------- custom cake: shape
INSERT INTO cake_options (group_key, name, price, sort_order, is_active) VALUES
  ('shape', 'Round',  0.00, 10, 1),
  ('shape', 'Square', 0.00, 20, 1),
  ('shape', 'Heart',  0.00, 30, 1),
  ('shape', 'Sheet',  0.00, 40, 1),
  ('shape', 'Other',  0.00, 50, 1);

-- ------------------------------------------- custom cake: size (price = base)
INSERT INTO cake_options (group_key, name, price, sort_order, is_active) VALUES
  ('size', '6 inch (serves 6-8)',    0.00, 10, 1),
  ('size', '8 inch (serves 12-16)',  0.00, 20, 1),
  ('size', '10 inch (serves 20-26)', 0.00, 30, 1),
  ('size', '12 inch (serves 30-40)', 0.00, 40, 1),
  ('size', 'Custom / Tiered',        0.00, 50, 1);

-- ------------------------------------------------- custom cake: flavour
INSERT INTO cake_options (group_key, name, price, sort_order, is_active) VALUES
  ('flavour', 'Vanilla',    0.00, 10, 1),
  ('flavour', 'Chocolate',  0.00, 20, 1),
  ('flavour', 'Red Velvet', 0.00, 30, 1),
  ('flavour', 'Strawberry', 0.00, 40, 1),
  ('flavour', 'Marble',     0.00, 50, 1),
  ('flavour', 'Lemon',      0.00, 60, 1),
  ('flavour', 'Fruit Cake', 0.00, 70, 1);

-- --------------------------------------------- custom cake: decoration
INSERT INTO cake_options (group_key, name, price, sort_order, is_active) VALUES
  ('decoration', 'Minimal / Modern',    0.00, 10, 1),
  ('decoration', 'Buttercream Florals', 0.00, 20, 1),
  ('decoration', 'Fondant Figures',     0.00, 30, 1),
  ('decoration', 'Drip & Sweets',       0.00, 40, 1),
  ('decoration', 'Gold Leaf Finish',    0.00, 50, 1),
  ('decoration', 'Photo Print',         0.00, 60, 1);

-- ------------------------------------------ custom cake: cream colours
INSERT INTO cake_options (group_key, name, price, sort_order, is_active) VALUES
  ('cream_colour', 'White',       0.00, 10, 1),
  ('cream_colour', 'Ivory',       0.00, 20, 1),
  ('cream_colour', 'Blush Pink',  0.00, 30, 1),
  ('cream_colour', 'Rose Pink',   0.00, 40, 1),
  ('cream_colour', 'Chocolate',   0.00, 50, 1),
  ('cream_colour', 'Pastel Blue', 0.00, 60, 1),
  ('cream_colour', 'Pastel Green',0.00, 70, 1),
  ('cream_colour', 'Gold',        0.00, 80, 1);

-- ------------------------------------------------------------- layers
INSERT INTO cake_layer_prices (layers, price, is_active) VALUES
  (1, 0.00, 1),
  (2, 0.00, 1),
  (3, 0.00, 1),
  (4, 0.00, 1);

-- --------------------------------------------------------- business settings
INSERT INTO business_settings (setting_key, setting_value, group_name) VALUES
  ('business_name',        'MADAM CAKES',                       'general'),
  ('tagline',              'Every Cake Tells a Sweet Story.',   'general'),
  ('logo_path',            '',                                  'general'),
  ('whatsapp_number',      '',                                  'contact'),
  ('phone',                '',                                  'contact'),
  ('email',                '',                                  'contact'),
  ('address',              '',                                  'contact'),
  ('opening_hours',        'Monday - Saturday: 8:00 AM - 8:00 PM', 'contact'),
  ('maps_url',             '',                                  'contact'),
  ('instagram_url',        '',                                  'social'),
  ('facebook_url',         '',                                  'social'),
  ('tiktok_url',           '',                                  'social'),
  ('x_url',                '',                                  'social'),
  ('currency_code',        'TZS',                               'commerce'),
  ('currency_decimals',    '0',                                 'commerce'),
  ('accepted_payments',    'cash,mpesa,airtel_money,mixx_by_yas','commerce'),
  ('pickup_enabled',       '1',                                 'commerce'),
  ('delivery_enabled',     '1',                                 'commerce'),
  ('surprise_delivery_enabled', '1',                            'commerce'),
  ('newsletter_enabled',   '0',                                 'commerce'),
  ('min_advance_days',     '2',                                 'commerce'),
  ('payment_instructions', 'Payment is confirmed by MADAM CAKES after verification. No order is marked paid automatically.', 'commerce');

-- ------------------------------------------------------------ website copy
INSERT INTO site_content (content_key, title, body) VALUES
  ('hero_eyebrow', 'Baked fresh in Dar es Salaam', NULL),
  ('hero_title',   'Every Cake Tells a Sweet Story.', 'Handcrafted cakes for birthdays, weddings and every moment worth remembering.'),
  ('hero_primary_cta', 'Shop Cakes', NULL),
  ('hero_secondary_cta', 'Design Your Cake', NULL),
  ('about_title', 'The MADAM CAKES Story', 'MADAM CAKES is a boutique cake studio. Every cake is baked to order, finished by hand and designed around the person receiving it.'),
  ('why_choose_title', 'Why Families Choose Us', NULL),
  ('custom_invite_title', 'Have an idea in mind?', 'Design your cake step by step, upload a reference photograph and send it to our team for a personal quotation.'),
  ('surprise_title', 'Send a surprise', 'Arrange a cake for someone you love, with a personal message and delivery to their door.'),
  ('contact_note', 'Order online or send us a message on WhatsApp - we reply during opening hours.', NULL),
  ('footer_about', 'MADAM CAKES is a premium cake boutique creating handcrafted cakes for celebrations across Tanzania.', NULL),
  ('promo_banner_active', '0', NULL),
  ('promo_banner_text', '', NULL);
