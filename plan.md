# Zen Block Checkout – Plan

WooCommerce **Checkout Block**-এর জন্য field editor। সব কাজ **block editor-এর ভেতরেই** হবে। আলাদা admin page থাকবে না।

## লক্ষ্য
- Checkout step-এর ভেতরে "Custom Fields" block বসিয়ে (বা step select করে) user নিজের field যোগ করবে। Controller থাকবে ডান sidebar-এ।
- Default field-এর মধ্যে custom field order করা যাবে।
- Advanced: conditional logic, validation, role-based visibility।
- Order admin, email আর thank-you page-এ value দেখাবে।

## যাচাই করা তথ্য (WooCommerce 11.1.2 source থেকে)
- Native API: `woocommerce_register_additional_checkout_field()`। `woocommerce_init`-এ register করতে হয়।
- Location: `contact`, `address` (billing + shipping দুটোতেই), `order`।
- Native type শুধু `text`, `select`, `checkbox`।
- `required`, `hidden`, `validation` JSON schema rule নিতে পারে। **তাই conditional logic-এর জন্য নিজের JS লাগবে না**, UI-র rule কে JSON schema-তে compile করলেই হবে।
- Address fields `index` দিয়ে sort হয় (core: country 1, first_name 10, last_name 20, company 30, address_1 40, address_2 50, city 70, state 80, postcode 90, phone 100)। আমাদের field `anchor + 0.01*n` index পায়।
- WC-র address form non-dropdown field-কে `<input type={field.type}>` দিয়ে render করে। তাই email/number/date ইত্যাদি `text` হিসেবে register করে browser-এ `type` বদলালেই হয় (`src/input-types.js`)।
- Third-party inner block-এর `parent` হিসেবে step block-এর নাম দিতে হয়। Order information-এর জন্য `woocommerce/checkout-fields-block` (আলাদা step block নেই)।
- Frontend field id: `shipping-zbc-<key>`, `billing-zbc-<key>`।
- `AssetDataRegistry` একই key দ্বিতীয়বার add করলে ignore করে, তাই `defaultFields` PHP থেকে বদলানো যায় না, JS-এ বদলাতে হয়।
- Sidebar slot প্রতিটি top-level child আলাদা node হিসেবে append করে, তাই panel একটা wrapper `<div>`-এর ভেতরে রাখতে হয়।
- Editor canvas iframe-এ চলে, তাই editor CSS `block.json`-এর `editorStyle` দিয়ে দিতে হয়।

## Architecture (বর্তমান)
```
zen-block-checkout/
├─ zen-block-checkout.php
├─ block/block.json                 "Custom Fields" block (parent: contact/shipping/billing/fields-block)
├─ includes/
│  ├─ class-config-store.php        option zbc_config (source: checkout page > runtime > template)
│  ├─ class-config-sync.php         save_post + self-heal sync, field sanitize
│  ├─ class-field-registrar.php     WC register, validators, scope validation + CSS
│  └─ class-integration.php         WC checkout integration (scripts + script data)
├─ src/
│  ├─ editor/                       block, sidebar (fields-editor, field-editor), step-panel, preview
│  ├─ frontend/index.js             registerCheckoutBlock + input types + skins
│  ├─ input-types.js                number/email/date... এর real type
│  └─ skins.js                      textarea / radio / checkbox group
├─ build/
└─ readme.txt
```

## Data কোথায় থাকবে
- Config থাকে block attribute-এ (page/template-এর সাথে save হয়)।
- Sync: `wp_after_insert_post` (`page`, `wp_template`) → option `zbc_config`। Frontend render-এ hash না মিললে self-heal (`runtime`)।
- Checkout page-এর saved config সবসময় `runtime`-এর আগে।

## কী করা হয়েছে

### Phase 1 – Foundation ✅
- Plugin scaffold, `@wordpress/scripts` build (Node 20, `NODE_ENV=production` থাকলে `npm install --include=dev`)।
- Custom Fields block, step select করলে sidebar-এ panel, Add/delete/move field।
- Config sync + PHP registrar, WC-style preview।

### Field order ✅
- Address-এ "Position" (After First name ইত্যাদি), একই anchor-এ list order।
- Contact/Order-এ শুধু list order।

### Scope ✅ (ব্রাউজারে যাচাই বাকি)
- Address field-এ "Show in": Shipping and billing / Shipping only / Billing only। অন্য form-এর copy CSS (`:has`) দিয়ে লুকানো, required সার্ভারে hook দিয়ে।
- Step অনুযায়ী conditional note।

### Field type ✅ (ব্রাউজারে যাচাই বাকি)
- Native: Text, Dropdown, Checkbox।
- Input type override: Email, Phone, Number, URL, Date, Time, Date and time (সার্ভারে validation)।
- Skin: Textarea, Radio, Checkbox group (native input লুকিয়ে নিজের control, value native-এ লেখা)।

### Field width ✅ (frontend; editor-এ কাজ করে না)
- প্রতিটি field-এ "Width": Default / Full width (1 column) / Half width (2 columns)।
- Frontend-এ CSS (`:has([id$="zbc-<key>"])` wrapper-এ `flex`) দিয়ে। Editor-এ কাজ করে না, নিচে F দেখো।

### Field type — Checkboxes
- আলাদা single "Checkbox" type বাদ। শুধু "Checkboxes" (option সহ)। পুরনো `checkbox` field "Checkbox (old)" হিসেবে চলে।

## পরের কাজ (পরবর্তী ধাপ)

### F. Editor-এ 1/2 column কাজ করে না
- Frontend-এ Width ঠিক আছে, কিন্তু editor canvas-এ Half width দেখায় না।
- সম্ভাব্য কারণ: editor-এ WC address form-এর wrapper/flex structure আলাদা, অথবা আমাদের CSS (`watchEditorCanvas`, ১ সেকেন্ড poll) iframe-এ ঠিকমতো পৌঁছায় না, অথবা editor-এর field wrapper class ভিন্ন।
- করণীয়: editor DOM inspect করে wrapper class দেখা, selector ঠিক করা, দরকারে poll-এর বদলে iframe `load`/MutationObserver।

### G. সব checkout block-এ field add করার option
- এখন Custom Fields block বসানো যায় শুধু Contact information, Shipping address, Billing address আর checkout fields block (Order information)-এ।
- লক্ষ্য: checkout-এর সব block/section-এ (যেমন Shipping method, Payment, Order note, Terms, Actions, Order summary, Totals ইত্যাদি) field add করার option দেওয়া।
- আগে যাচাই: WC কোন কোন area-তে third-party inner block allow করে (`innerBlockAreas` / `parent` list), আর WC Additional Checkout Fields API-তে location শুধু `contact`, `address`, `order`। তাই অন্য section-এ field "order" location-এ register হবে, কিন্তু দেখানোর জায়গা (position) কীভাবে হবে সেটা ঠিক করতে হবে (আমাদের নিজস্ব React render, অথবা DOM-এ সরিয়ে বসানো)।
- Sidebar panel (`step-panel.js`) আরও block-এ যোগ হবে, `STEP_LOCATIONS`-এ নতুন entry।

## বাকি কাজ

### A. এখনকার test থেকে যা যাচাই করতে হবে
- সব নতুন type checkout-এ কাজ করে কিনা, required খালি রেখে order দিলে error আসে কিনা।
- Date/Time label overlap CSS ঠিক হয়েছে কিনা।
- Textarea-র newline order-এ save হয় কিনা (Store API sanitize)।
- "Shipping only / Billing only" এ `:has()` selector WC-র wrapper class-এ কাজ করে কিনা (dropdown-এর wrapper অনুমান করা)।
- Width (Half/Full) frontend-এ WC-র wrapper-এ কাজ করে কিনা।
- "Use same address for billing" checkbox (shipping zone/method না থাকা WC setup-এর সমস্যা কিনা) এবং billing block editor-এ নড়াচড়া/click।
- Contact ও Order information-এ saved field WC editor নিজে দেখায় কিনা (দেখালে preview duplicate হবে, তখন address-এর মতো লুকাতে হবে)।
- Editor-এ saved textarea/radio/checkbox group WC-র native text/dropdown হিসেবে দেখায়, skin শুধু frontend-এ।

### B. Phase 2 – Default field editor
- Contact/Address block select করলে "Zen" panel: label, placeholder, required, hide।
- Protected field (email, country ইত্যাদি) hide/delete করা যাবে না।
- Reset to default।

### C. Phase 3 – Field option
- Default value, help text।
- Placeholder (এখন শুধু Dropdown-এ), min/max, max length, pattern, custom error message।
- Conditional logic (cart-এ product/category, cart total, shipping/payment method, country, অন্য field-এর value) → WC JSON schema।
- Role / logged-in status অনুযায়ী দেখানো।

### D. আরও field type (প্রয়োজন হলে)
- সহজ: Month, Week, Color (input type override), Hidden field, Multi-select (skin), Heading/Paragraph (শুধু লেখা)।
- কঠিন: File upload (alada endpoint, security, storage), Range/Rating।
- Country/State picker: WC-র নিজের address field-ই আছে, দরকার কম।

### E. Polish
- Drag-and-drop reorder (এখন শুধু up/down arrow)।
- Import / export (JSON), order list column, CSV export।
- Editor-এ saved field-এর label আপডেট করলে Update-এর আগে canvas-এ দেখা যায় না (WC field সরাসরি আঁকে), এটা সীমাবদ্ধতা।
- WPML/Polylang, i18n, uninstall cleanup, PHPCS, plugin-check, readme।

## Guard rail
- Field ID auto-generate, save-এর পর অপরিবর্তনীয়।
- Core required field delete/hide করা যাবে না (Phase 2)।
- WC version পুরনো হলে editor-এ warning (এখনো করা হয়নি)।
- একাধিক checkout block পেলে notice (এখনো করা হয়নি)।

## ঝুঁকি
- WC update-এ block name, field id format (`shipping-zbc-`), wrapper class বদলালে CSS/skin ভেঙে যেতে পারে।
- `defaultFields` JS-এ বদলানো WC-র internal structure-এর উপর নির্ভর করে।
- Skin polling (250ms) ও MutationObserver; WC form re-render করলে আবার enhance হয়, কিন্তু পুরোপুরি যাচাই হয়নি।
