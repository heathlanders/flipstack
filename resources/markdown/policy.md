# Privacy Policy

**Last updated: May 8, 2026**

FlipStack ("we", "us", "our") operates the FlipStack ecommerce SaaS platform at flipstack.app and develop.flipstack.app (the "Service"). This policy explains what data we collect, how we use it, and the choices you have.

By using the Service you agree to this policy. If you do not agree, please do not use the Service.

## 1. Data we collect

### 1.1 Account data
When you sign up we collect your name, email address, organization name, and password (stored hashed). If you accept an invitation, we also collect the email address of the inviting user to link the accounts.

### 1.2 Marketplace integration data
When you connect a marketplace (eBay, Shopify, Amazon, etc.) we receive the following from that marketplace on your behalf, scoped to your seller account:

- **Account identifiers** — seller ID, store URL, marketplace participation list.
- **Listings** — SKUs, titles, descriptions, prices, quantities, images, condition, category mappings, ASIN/UPC/EAN identifiers.
- **Orders** — order ID, line items, totals, fees, payment status, fulfillment status, ship dates, tracking numbers.
- **Buyer data** — buyer name, ship-to and bill-to addresses, anonymized buyer email (when the marketplace anonymizes it), order-level messages you exchange through the marketplace.
- **Inventory levels** — per-SKU quantities, FBA inventory summaries (Amazon), location stock (Shopify).
- **Pricing signals** — buy-box prices, competitor offer counts (Amazon), where exposed by the marketplace API.
- **Webhook / notification events** — order changes, listing status changes, return requests, message-received events.

We retrieve this data via the marketplace's official API (eBay Trading/Sell API, Shopify Admin API, Amazon Selling Partner API) using OAuth tokens you authorize. We do not scrape, screen-scrape, or otherwise access marketplace data outside of documented APIs.

### 1.3 Operational data
- Audit logs of actions taken in the Service (user, action, timestamp, target resource).
- Notification delivery logs (channel, recipient, status).
- API rate-limit telemetry from upstream marketplaces.
- Error logs and stack traces (PII redacted where feasible).

### 1.4 Cookies and analytics
We use first-party session cookies for authentication. We do not sell tracking data. We may use privacy-respecting analytics to measure feature usage.

## 2. How we use your data

We use the data above only to provide the Service:

- Display your listings, orders, customers, inventory, and analytics inside FlipStack.
- Push your inventory and price updates back to the connected marketplaces.
- Sync order status, tracking, and returns between FlipStack and the marketplaces.
- Run repricing rules and demand forecasting against your own data.
- Send transactional notifications (email, SMS, push) about your account, orders, and integration health.
- Respond to support requests.
- Detect abuse, fraud, and security incidents.

**We do not** use your marketplace data to train machine-learning models that benefit other customers, sell your data to third parties, or use buyer PII for marketing.

## 3. Amazon Selling Partner API (SP-API) data

Where Amazon SP-API data is involved, the following additional terms apply, consistent with Amazon's Acceptable Use Policy and Data Protection Policy:

- Amazon Information (orders, buyer info, listings, inventory) is used **solely to fulfill your orders and operate your store inside FlipStack**.
- Personally Identifiable Information (PII) from Amazon orders is encrypted at rest and in transit, access-controlled, and retained only for the period required to fulfill the order plus the legally required tax / accounting window (typically 7 years), or sooner if you delete your account or disconnect Amazon.
- We do not share Amazon Information with any third party except sub-processors strictly required to operate the Service (hosting, email/SMS delivery, error monitoring), each bound by equivalent confidentiality and data-protection obligations.
- We do not use Amazon Information to compete with Amazon or with other Amazon sellers.
- On request, or upon disconnection, we will delete Amazon-derived PII within 30 days, except where retention is required by law.

## 4. Sharing and sub-processors

We share data only with sub-processors that help us run the Service:

- Cloud hosting and database providers (e.g. Coolify-managed servers, AWS for SNS notifications).
- Email delivery (transactional email provider).
- SMS delivery (Twilio).
- Real-time delivery (Pusher).
- Error monitoring and logging.

Each sub-processor is bound by a data-processing agreement and processes data only on our instructions.

We do not sell personal data.

## 5. Data retention

- **Account data:** retained while your account is active. Deleted within 30 days after account closure, except where retention is required by law.
- **Marketplace tokens:** revoked and deleted on disconnect or account closure.
- **Order and buyer data:** retained for the legally required window (typically 7 years for tax / accounting). On request we will delete buyer PII earlier where the law allows.
- **Audit logs:** retained for 1 year.

## 6. Security

We use TLS 1.2+ for all network traffic, encrypt OAuth tokens at rest using application-level encryption, run on hardened Linux containers, and limit administrative access to a small number of authorized employees. Despite these measures, no system is perfectly secure; you use the Service at your own risk.

## 7. Your rights

Depending on your jurisdiction (GDPR, CCPA, etc.) you may have the right to:

- Access the personal data we hold about you.
- Correct inaccurate data.
- Delete your data ("right to be forgotten"), subject to legal retention requirements.
- Object to or restrict certain processing.
- Receive your data in a portable format.
- Lodge a complaint with a supervisory authority.

To exercise any of these rights, email **privacy@flipstack.app**. We respond within 30 days.

## 8. International transfers

The Service is hosted in the United States. If you access it from outside the United States your data will be transferred to the United States. By using the Service you consent to this transfer.

## 9. Children

The Service is not directed at children under 16. We do not knowingly collect data from children.

## 10. Changes to this policy

We may update this policy from time to time. The "Last updated" date at the top reflects the most recent revision. Material changes will be communicated via email or in-app notice.

## 11. Contact

Questions or requests about this policy:

**FlipStack**
Email: privacy@flipstack.app
