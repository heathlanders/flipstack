# Organization Admin User Guide

This guide covers the complete setup and management of your organization on the platform. It is intended for organization owners and administrators.

---

## Table of Contents

1. [Getting Started](#1-getting-started)
2. [Organization Settings](#2-organization-settings)
3. [Team Management](#3-team-management)
4. [Inventory Management](#4-inventory-management)
5. [Product Intake Queue](#5-product-intake-queue)
6. [Supplier Management](#6-supplier-management)
7. [Marketplace Integrations](#7-marketplace-integrations)
8. [Fulfillment](#8-fulfillment)
9. [Label Printing](#9-label-printing)
10. [Dashboards](#10-dashboards)
11. [Notifications](#11-notifications)
12. [Admin Features](#12-admin-features)
13. [Storefront](#13-storefront)
14. [Mobile App](#14-mobile-app)

---

## 1. Getting Started

### Registration

1. Navigate to the registration page and create an account with your name, email, and password.
2. Verify your email address via the confirmation link sent to your inbox.
3. Log in to begin the onboarding process.

### Onboarding Wizard

After your first login you are guided through a 7-step onboarding wizard. Progress is tracked automatically so you can resume where you left off.

| Step | Purpose |
|------|---------|
| 1. Organization Profile | Enter your business name, slug (used for subdomain), and contact details. |
| 2. SKU Prefix | Choose a unique SKU prefix (e.g., `ACME`). All generated SKUs will start with this prefix. |
| 3. Business Preferences | Set your default currency, timezone, and measurement units. |
| 4. Inventory Locations | Create your first warehouse or storage location. |
| 5. Team Setup | Invite initial team members and assign roles. |
| 6. Integrations | Optionally connect eBay, Shopify, or other services. |
| 7. Review & Finish | Confirm your settings and enter the main dashboard. |

You can revisit any of these settings later from the Organization Settings page.

### SKU Prefix

Your SKU prefix is set during onboarding and is used by the `OrganizationSkuService` to generate unique product SKUs. The prefix can be changed later under **Settings > General**, but existing SKUs will not be retroactively updated.

---

## 2. Organization Settings

Navigate to **Settings** from the sidebar. Settings are divided into several tabs.

### General

- Organization name and slug (subdomain)
- Logo upload
- Contact email and phone
- Business address
- SKU prefix and numbering format

### Preferences

- Default currency
- Timezone
- Date/number formatting
- Measurement units (imperial/metric)
- Default product listing behavior

### Security

- Two-factor authentication enforcement
- Session management (view and revoke active sessions)
- Password policy settings
- API token management (Sanctum tokens)

### Notifications

- Organization-wide notification defaults
- Email digest frequency
- Quiet hours configuration

### Integrations

- Manage connected platforms (eBay, Shopify, Barcode Spider, Gemini AI, EasyPost)
- Add or remove API credentials
- View connection status and last sync times
- See the [Marketplace Integrations](#7-marketplace-integrations) section for detailed setup instructions

---

## 3. Team Management

### Teams

Organizations contain one or more teams. Each team can be scoped to a function (e.g., "Buying Team", "Fulfillment Team").

**Creating a team:**

1. Go to **Settings > Teams**.
2. Click **Create Team**.
3. Enter a team name and optional description.
4. Save.

### Inviting Users

1. Open the team you want to add members to.
2. Click **Invite Member**.
3. Enter the user's email address.
4. Select a role (see below).
5. Send the invitation. The user receives an email with a link to join.

### Roles and Permissions

| Role | Description |
|------|-------------|
| **Owner** | Full access to all features, settings, billing, and team management. Can delete the organization. |
| **Admin** | Comprehensive management capabilities including user, team, and integration management. |
| **Manager** | Broad operational access with user creation, team management, and intake processing. |
| **Buyer** | Access to product sourcing, intake queue, supplier management, and the buyer dashboard. |
| **Sourcing Specialist** | Variant of Buyer role with same sourcing capabilities. |
| **Lister** | Access to product listing, intake queue verification, eBay/Shopify management, and the lister dashboard. |
| **Listing Specialist** | Variant of Lister role with same listing capabilities. |
| **Fulfillment Manager** | Access to orders, picking, packing, shipping, returns, and the fulfillment dashboard. |
| **Shipper** | Alternative fulfillment role focused on shipping operations. |
| **Support Agent** | View-only access to inventory and products, with messaging and return request visibility. |
| **Employee** | Basic view access to inventory, products, and analytics. Default role for new users. |

Permissions are enforced at the route level via Laravel Gates and at the controller level via `CheckUserPermissions` and `CheckInventoryPermissions` middleware. Owners can customize granular permissions per role under **Admin > Roles & Permissions**.

### Managing Members

- Change a member's role from the team detail page.
- Remove a member by clicking **Remove** next to their name.
- View member activity in the **User Activity Log**.

---

## 4. Inventory Management

### Products

Navigate to **Inventory > Products** to manage your product catalog.

**Creating a product:**

1. Click **Create Product**.
2. Fill in required fields: name, SKU (auto-generated or manual), category, and condition.
3. Add optional details: description, brand, weight, dimensions, cost, price.
4. Upload product images (drag-and-drop supported, multiple images allowed).
5. Add product identifiers (UPC, EAN, ISBN, MPN) for barcode lookups.
6. Save.

**Editing and deleting:**

- Click any product row to open its detail page.
- Use the **Edit** button to modify fields.
- Use the **Delete** button to remove a product (this also removes associated inventory levels).

**Bulk operations:**

- **Import**: Upload a CSV file from **Inventory > Import**. Map columns to product fields and preview before confirming.
- **Export**: Download your full product catalog as CSV from **Inventory > Export**. Filter by category, brand, or status before exporting.

### Categories

Navigate to **Inventory > Categories** to organize products.

- Categories support a hierarchical (parent/child) structure.
- Assign a category when creating or editing a product.
- Categories can be mapped to eBay and Shopify categories for marketplace sync.

### Inventory Locations

Navigate to **Inventory > Locations** to manage warehouses and storage areas.

- Create locations with a name, code, and address.
- Locations are used for stock tracking, picking, and label printing.
- Each product's stock is tracked per-location via `InventoryLevel` records.

### Stock Operations

From a product's detail page or from **Inventory > Stock**:

| Operation | Description |
|-----------|-------------|
| **Adjust** | Manually set or adjust the quantity at a location (e.g., after a physical count). |
| **Transfer** | Move stock between two locations. Both source and destination are updated. |
| **Reserve** | Hold stock for a pending order. Reserved stock is unavailable for new orders. |

All stock changes create an `InventoryMovement` record for full audit history.

### Analytics

Navigate to **Inventory > Analytics** for insights:

- **Inventory Valuation**: Total value of stock on hand broken down by location and category.
- **ABC Analysis**: Products ranked by sales velocity or revenue contribution (A = top 20%, B = next 30%, C = remaining 50%).
- **Turnover Rate**: How quickly stock is sold and replenished, calculated per product and category.
- **Low Stock Alerts**: Products below their configured reorder thresholds.

---

## 5. Product Intake Queue

The intake queue is the workflow for adding new products to your catalog. It supports barcode scanning, AI-powered research, and a 4-step verification process.

### Adding Items to the Queue

**From the web app:**

1. Navigate to **Intake Queue**.
2. Click **Add Item**.
3. Enter a barcode/UPC or type a product name manually.
4. Optionally add notes, photos, quantity, and source location.
5. Submit. The item enters the queue with a status of **Pending**.

**From the mobile app:**

1. Open the app and tap **Add to Intake Queue**.
2. Scan the product barcode using the camera.
3. Take photos (front, back, details).
4. Add notes and quantity.
5. Save. The item is queued and synced to the server.

### AI Research & Enrichment

When an item enters the queue, the system automatically:

1. Looks up the barcode via **Barcode Spider** to find product name, brand, category, and images.
2. Sends the data to **Gemini AI** for enriched descriptions, specification extraction, and category detection.
3. Assigns a **confidence score** indicating how complete and accurate the auto-filled data is.

High-confidence items can be bulk-approved. Low-confidence items require manual review.

### 4-Step Verification Workflow

Each intake item passes through four steps before becoming a live product:

| Step | What Happens |
|------|-------------|
| **1. Product Info** | Review and confirm the product name, brand, category, condition, identifiers, and images. Edit any auto-filled data that is incorrect. |
| **2. Pricing** | Set the cost (what you paid), list price (what you sell for), and minimum acceptable price. View suggested pricing from AI research. |
| **3. Listing Details** | Configure marketplace-specific fields: eBay title, item specifics, Shopify tags, description, and SEO metadata. |
| **4. Review & Publish** | Final review of all data. Choose to publish to inventory only, or also push to connected marketplaces (eBay, Shopify). Confirm to create the product. |

### Queue Management

- Filter the queue by status (Pending, In Review, Approved, Rejected).
- Assign items to team members for review.
- Bulk approve high-confidence items.
- Reject items with a reason note.

---

## 6. Supplier Management

### Adding Suppliers

1. Navigate to **Suppliers > Add Supplier**.
2. Enter company name, contact person, email, phone, and address.
3. Assign an initial tier (see below).
4. Save.

### Supplier Tiers

Tiers categorize suppliers by their relationship level and determine commission rates.

| Tier | Typical Use |
|------|-------------|
| **Bronze** | New or occasional suppliers with standard commission rates. |
| **Silver** | Established suppliers with moderate volume and reduced commission. |
| **Gold** | High-volume, reliable suppliers with preferential commission rates. |
| **Platinum** | Strategic partners with custom commission structures. |

Tiers are managed under **Suppliers > Tiers** and can be customized with different names and commission rates.

### Commission Structures

Each tier (or individual supplier) can have a commission structure defining:

- **Flat rate**: A fixed dollar amount per item sold.
- **Percentage**: A percentage of the sale price.
- **Tiered percentage**: Rates that change based on volume thresholds.

Commissions are calculated automatically by the `CommissionCalculatorService` when settlements are generated.

### Performance Tracking

Navigate to **Suppliers > Performance** to view:

- Total products supplied
- Sales generated from supplier's products
- Average time from intake to sale
- Return rate
- Quality score

Performance data drives tier recommendations (e.g., upgrade a Silver supplier to Gold).

### Settlements

Settlements are periodic payment summaries for suppliers.

1. Go to **Suppliers > Settlements**.
2. Click **Generate Settlement** for a supplier and date range.
3. The system calculates total sales, commissions owed, and net payout.
4. Review line items and approve the settlement.
5. Mark as paid once payment is sent.

The `SettlementProcessorService` handles the calculation logic.

### Supplier Detail Hub

The supplier detail page (**Suppliers > select supplier**) consolidates supplier review into a single hub:

**Quick stats**: Total Sales, Performance Score, Catalog Products (pivot count), **Marketplace Listings** (eBay + Shopify + Amazon active), Open PO Value, Pending Payout. A breakdown line shows the per-marketplace split.

**Overview tab** surfaces two analytical cards built specifically for this page:

- **Procurement KPIs** — per-supplier PO count, total spend, average lead time, on-time rate, receiving accuracy, total payouts, and over/under-received item counts. Computed inline (not from the org-wide `ProcurementAnalyticsService` cache).
- **Lister / Owner Cross-Reference** — surfaces a documented gap in the eBay SKU parser: lister initials (`products.attributes.lister_initials`) do not create a supplier link, only owner codes do. Shows the org's configured `known_listers`, the codes seen on this supplier's products, and the count of org-wide products tagged with a lister code but missing a supplier link.

**Tabs**:

- **Products** — paginated, searchable, sortable. Shows pivot data (supplier SKU, cost, preferred flag) alongside product fields.
- **Purchase Orders / Inventory Batches / Orders / Submissions** — paginated lists for each supplier-linked entity. Submissions row click opens the existing review page.
- **Settlements / Performance / Documents / Notes** — existing widgets, now wired from top-level controller props.

Notes persist to `meta_data.admin_notes` (array of `{id, content, user_name, created_at}`).

---

## 7. Marketplace Integrations

### eBay

#### Setup

1. Go to **Settings > Integrations** and find the eBay panel.
2. Click **Connect eBay**.
3. You are redirected to eBay's OAuth page. Log in and grant permissions.
4. After authorization, you are returned to the platform with your account connected.
5. Choose your environment: **Sandbox** (for testing) or **Production** (live).
6. Configure sync settings: auto-sync toggle, sync interval, default listing format.

#### Managing Listings

- Navigate to **Marketplaces > eBay > Listings** to view all eBay listings.
- Create listings manually or via the intake queue publish step.
- Edit listing details (title, price, quantity, item specifics) from the listing detail page.
- Sync inventory levels between the platform and eBay.

#### eBay Products & Import

- **Marketplaces > eBay > Products** shows products synced from eBay.
- **Marketplaces > eBay > Import** lets you import existing eBay listings into the platform. The SKU parser maps eBay item data to platform product fields.

#### Sync Logs

- **Marketplaces > eBay > Logs** shows a history of all sync operations, errors, and API rate limit status.

#### Rate Limiting

The platform tracks eBay API usage (daily and monthly limits) via `EbayRateLimiter`. If limits are approached, sync operations are automatically throttled.

### Shopify

#### Setup

1. Go to **Settings > Integrations** and find the Shopify panel.
2. Enter your Shopify store domain (e.g., `mystore.myshopify.com`).
3. Click **Connect Shopify**.
4. You are redirected to Shopify's OAuth page. Install the app and grant permissions.
5. After authorization, you are returned to the platform with Shopify connected.
6. Configure sync settings from **Shopify > Settings**.

#### Product Sync

- Navigate to **Shopify > Products** to view synced products.
- Product sync is bidirectional: changes on either platform update the other.
- Inventory levels are kept in sync via `ShopifyInventoryService`.

#### Import

- **Shopify > Import** lets you bulk import your existing Shopify catalog into the platform.

#### Sync Logs

- **Shopify > Sync Logs** shows the history of sync operations and any errors.

#### Webhooks

Shopify sends real-time updates (product changes, order creation, inventory updates) via webhooks. These are automatically processed by the `ShopifyWebhookController` with HMAC signature verification.

### Barcode Spider

1. Go to **Settings > Integrations**.
2. Enter your Barcode Spider API key.
3. Click **Verify** to test the connection.
4. Once verified, barcode lookups in the intake queue use Barcode Spider automatically.
5. Free tier includes 100 lookups per day.

### Gemini AI

1. Go to **Settings > Integrations**.
2. Enter your Google Gemini API key.
3. Click **Verify** to test the connection.
4. Once verified, the intake queue uses Gemini for product description generation, specification extraction, and category detection.
5. Free tier includes 1,500 requests per day.

---

## 8. Fulfillment

The fulfillment module manages the complete order lifecycle: order management, picking, packing, shipping, and returns.

### Orders

Navigate to **Fulfillment > Orders** to view all orders.

- Filter by status (Pending, Processing, Shipped, Delivered, Cancelled), priority, or assigned user.
- **Assign** orders to team members for picking.
- **Hold** an order to pause processing (e.g., address verification needed).
- **Release** a held order to resume processing.
- **Cancel** an order with a reason.
- **Bulk assign** and **bulk priority** operations are available for managing large volumes.
- Each order has an **audit trail** showing every status change and who made it.

### Picking

Navigate to **Fulfillment > Pick** to see the pick queue.

1. Start picking an order by clicking **Start Pick**.
2. The pick list shows each item, its quantity, and its warehouse location.
3. Scan barcodes or manually mark items as picked.
4. If an item is unavailable, mark it as **Short** to flag the shortage.
5. Click **Complete Pick** when all items are picked.
6. Print pick lists for paper-based workflows.

### Packing

Navigate to **Fulfillment > Pack** to see orders ready for packing.

1. Click **Start Pack** on an order.
2. Enter package dimensions and weight (or select a saved package preset).
3. Click **Get Rates** to fetch shipping rates from your configured provider (EasyPost, ShipStation, or ShippingEasy).
4. Select a carrier and service level.
5. Click **Purchase Label** to buy the shipping label.
6. Print the packing slip from the order detail.
7. Click **Complete Pack** to advance the order.

### Shipping

Navigate to **Fulfillment > Ship** to see packed orders ready to ship.

- **Confirm Shipment**: Mark orders as shipped. Tracking numbers are automatically applied.
- **Batch Print Labels**: Select multiple shipments and print all labels at once.
- **Void Label**: Cancel a purchased label if needed (subject to carrier void windows).
- **Download Label**: Download a label PDF for any shipment.

### Returns

Navigate to **Fulfillment > Returns** to manage return requests.

1. **Create Return**: Initiate a return for an order, specifying the reason and items.
2. **Approve/Deny**: Review the return request and approve or deny it.
3. **Receive**: Mark the returned item as received at your warehouse.
4. **Inspect**: Record the item's condition upon arrival.
5. **Refund**: Process the refund to the customer.
6. **Restock**: Optionally return the item to inventory.

### Shipping Provider Settings

Navigate to **Fulfillment > Settings** to configure shipping.

**EasyPost** (primary provider):
- Enter your EasyPost API key.
- Supports multi-carrier rate shopping, address verification, label generation, and tracking.
- Tracking updates are received via webhook.

**ShipStation** (alternative):
- Enter your ShipStation API key and secret.
- Webhook support for order status updates.

**ShippingEasy** (alternative):
- Enter your ShippingEasy API key and secret.
- Webhook support for order status updates.

**Additional settings:**
- Default carrier and service level
- Package presets (saved box dimensions and weights)
- Return policy (return window in days, restocking fee, policy text)
- Auto-refund settings
- Carrier sync (pull carrier accounts from your provider)

---

## 9. Label Printing

### Printing Dashboard

Navigate to **Printing** from the sidebar to access the label printing system.

### Printer Setup

**Network Discovery:**

1. Go to **Printing > Printers > Discovery**.
2. Click **Discover Printers** to scan the network for compatible printers.
3. Select a discovered printer and click **Add**.
4. Run a **Test Print** to verify connectivity and alignment.

**Manual Setup:**

1. Go to **Printing > Printers > Add Printer**.
2. Enter the printer name, IP address, port, driver type, paper size, and DPI.
3. Save and run a test print.

### Label Templates

Navigate to **Printing > Templates** to manage label layouts.

**Default templates** can be created with one click and include:
- **Product Label**: SKU, product name, barcode, price.
- **Location Label**: Warehouse bin code, QR code.
- **Shipping Label**: Address, carrier barcode.
- **Price Tag**: Price, product name, barcode.

**Custom templates:**
- Create a new template with the template editor.
- Configure field positioning, font size and style, barcode format (QR Code, Code 128, etc.), logo/image placement, and margins.

### Print Jobs

Navigate to **Printing > Jobs** to view job history.

- **Single Print**: Print one label from a product's detail page.
- **Batch Print**: Select multiple products and print all labels at once.
- **Job Queue**: View pending, in-progress, and completed print jobs.
- **Retry Failed**: Reprint any jobs that failed.

### Warehouse Location Labels

Navigate to **Printing > Locations** to manage warehouse location labels.

- Create individual locations or use **bulk create** with range patterns (e.g., A01-A50).
- Print QR code labels for each location.
- These labels are scanned during picking and stock operations.

---

## 10. Dashboards

The main dashboard at `/dashboard` automatically displays the view matching your role. You can also access any dashboard you have permission for directly.

### Owner Dashboard

The owner dashboard provides a complete business overview.

**Key metrics at a glance:**
- Total inventory value
- Products added this week (with trend indicator)
- Pending intake items
- Low stock alerts count
- Orders pending fulfillment
- Revenue for the current period

**Sections:**
- **Critical Alerts**: Urgent items requiring attention (integration failures, stock-outs).
- **Platform Health**: Status of connected integrations (eBay, Shopify) and queue workers.
- **Recent Activity**: Feed of actions across the organization.
- **Team Performance**: Per-user productivity metrics.
- **Financial Summary**: Revenue, costs, and margins.
- **Business Intelligence**: ROI analysis, top products, growth trends.

**Live updates**: Data auto-refreshes every 60 seconds. Toggle auto-refresh on or off, or click the refresh button manually. Critical alerts trigger browser notifications.

### Buyer Dashboard

Focused on sourcing and procurement.

- Products sourced this week and average cost
- Intake queue status
- Supplier performance rankings
- ROI metrics on sourced products
- Price alerts and competitor analysis

### Lister Dashboard

Focused on listing and publishing.

- Items listed this week
- Pending intake items awaiting listing
- Listing quality score
- Average time from intake to listed
- High-confidence items ready for bulk approval
- eBay/Shopify sync status

### Fulfillment Dashboard

Focused on order processing.

- Orders pending, picked, packed, and shipped today
- Return requests count
- Pick queue, pack queue, and ship queue summaries
- Carrier performance metrics
- Urgent orders requiring immediate attention

### Support Dashboard

Focused on customer communication and issue resolution.

- Unread marketplace messages count
- Messages received today across all channels
- Average response time (last 7 days)
- Open return requests
- Recent incoming messages with read/unread status
- Active return requests with order details and status

### Basic Dashboard

Default dashboard for employees and users without a role-specific view.

- Links to available features based on user permissions (Inventory, Orders, Analytics, Settings)
- Adapts dynamically to show only features the user has access to

### Role-to-Dashboard Mapping

| Role | Dashboard |
|------|-----------|
| Organization Owner | Owner |
| Admin | Owner |
| Manager | Owner |
| Buyer / Sourcing Specialist | Buyer |
| Lister / Listing Specialist | Lister |
| Fulfillment Manager / Shipper | Fulfillment |
| Support Agent | Support |
| Employee | Basic |

---

## 11. Notifications

### Notification Center

Navigate to **Notifications** from the sidebar or click the bell icon in the header.

- View all notifications with unread count badge.
- Filter by type (Inventory, Orders, Returns, Supplier, Integrations, System).
- Mark individual notifications as read or use **Mark All Read**.
- Delete notifications you no longer need.
- Search notification history.

### Notification Types

| Type | Examples |
|------|----------|
| **Inventory** | Low stock alerts, restock reminders |
| **Orders** | New order received, order shipped, delivery confirmed |
| **Returns** | New return request, return approved/denied |
| **Supplier** | Settlement generated, payment received |
| **Intake Queue** | High-confidence items ready for review |
| **Integrations** | Sync errors, connection issues, token expiration |
| **System** | Platform updates, scheduled maintenance |

### Delivery Channels

| Channel | Description |
|---------|-------------|
| **In-app** | Bell icon dropdown and notification center page |
| **Email** | Immediate delivery or batched digest |
| **SMS** | Via Twilio, typically reserved for critical alerts |
| **Slack** | Team channel notifications |
| **Browser** | Desktop push notifications |
| **Mobile Push** | Push notifications on the mobile app |

### Preferences

Navigate to **Notifications > Preferences** to customize your notification settings.

- Enable or disable each delivery channel per notification type.
- Set frequency: real-time, hourly digest, daily digest, or weekly digest.
- Configure quiet hours (e.g., no notifications between 10 PM and 7 AM).
- Enable Do Not Disturb mode to silence all non-critical notifications.

---

## 12. Admin Features

### AI Prompt Management

Navigate to **Admin > AI Prompts** to manage the prompts used by Gemini AI for product research.

**Prompt types:**
- Product description generation
- Title optimization
- SEO keyword extraction
- Category detection
- Specification extraction
- Image analysis

**Managing prompts:**
- **Create** a new prompt with a name, template text, and variable placeholders (e.g., `{product_name}`, `{category}`, `{brand}`).
- **Edit** existing prompts to improve output quality.
- **Preview** a prompt's output against sample data before saving.
- **Test** a prompt against a real product to see results.
- **Duplicate** a prompt to create variations for A/B testing.
- **Version History**: View all past versions of a prompt and revert if needed.
- **Import/Export**: Share prompt sets between organizations via JSON export.
- **Bulk Update**: Apply changes to multiple prompts at once.

### Role & Permission Management

Owners can customize the permissions associated with each role:

1. Go to **Admin > Roles**.
2. Select a role to view its current permissions.
3. Toggle individual permissions on or off.
4. Save changes. They take effect immediately for all users with that role.

---

## 13. Storefront

The storefront is a public-facing product catalog that requires no authentication.

### Accessing the Storefront

Navigate to `/shop` or your organization's subdomain to view the catalog.

### Features

- **Product Grid**: Browse all active products with images, names, and prices.
- **Product Detail**: Click a product to see its full description, images, specifications, and pricing.
- **Categories**: Filter products by category using the sidebar or dropdown.
- **Search**: Full-text search across product names, descriptions, and identifiers.
- **Responsive**: The storefront is mobile-friendly and adapts to all screen sizes.
- **SEO**: Pages include meta tags and structured data for search engine visibility.

### Use Cases

- Direct-to-consumer sales channel
- B2B wholesale catalog for partners
- Product showcase for suppliers
- Price transparency reference

---

## 14. Mobile App

The mobile app (Flutter) is designed for on-the-go inventory operations, with barcode scanning as the primary use case.

### Installation

The mobile app is available for iOS and Android. Contact your organization admin for the installation link.

### Authentication

- Log in with your platform email and password.
- Two-factor authentication is supported.
- Biometric login (Touch ID / Face ID) is available after initial login.
- Sessions are secured with Sanctum API tokens stored in encrypted device storage.

### Key Features

**Barcode Scanning & Product Intake (primary use case):**

1. Tap **Add to Intake Queue** on the home screen.
2. Point your camera at the product barcode to scan.
3. The app looks up the barcode and pre-fills available product data.
4. Take photos of the product (front, back, detail shots).
5. Add text or voice notes.
6. Enter quantity and select a storage location.
7. Save. The item is added to the intake queue and AI research begins automatically.
8. Review and complete the verification workflow on the desktop app.

**Offline mode**: Items scanned while offline are stored locally and synced when connectivity is restored.

**Inventory browsing:**
- View your full product catalog.
- Search and filter products.
- View product details and stock levels.
- Quick-edit quantities.

**Dashboard:**
- View your role-specific dashboard metrics on mobile.
- Quick actions for common tasks.
- Recent activity feed.

**Notifications:**
- Receive push notifications for critical alerts.
- View notification history.
- Tap a notification to deep-link to the relevant screen.

**Profile management:**
- View and edit your profile.
- Change your password.
- Manage active sessions.
