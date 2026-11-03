# Sokoni Wallet & settlement: plan (draft for approval)

Status: **plan only, no code.** Branch `feature/delivery`. Nothing here is built or deployed.

Sources: the client document *Sokoni Wallet & Settlement Procedure* (proposed payment procedure, November 2026), `docs/delivery/PLAN.md`, `docs/delivery/DECISIONS.md` and the current code.

## 0. In one page

- **What changes.** Today a dispatch order collects only the delivery fee online, and goods are paid to the seller in cash. The wallet plan collects goods and delivery together through a licensed payment partner after the seller confirms stock. The partner holds that money until the customer's PIN closes the delivery. Sokoni then splits it to the seller, the rider and Sokoni, and sellers and riders withdraw.
- **The rule everything follows.** A balance shown in Sokoni is a mirror of money the partner has confirmed. Sokoni never creates spendable money by editing a database field. Every movement is an append-only double-entry ledger posting with a permanent ID, tied to a verified partner event or to an admin decision with a reason.
- **What I need from you before section 1 can be finished:**
  1. The payment provider's name and a link to its API documentation (section 1).
  2. Your lawyer's answer on where the money may sit (section 2).
  3. Confirmation of the numbers and who pays fees (section 5).
- **What is safe to build first** (once you approve): the ledger, a fake gateway and the admin views, all behind `WALLET_ENABLED=false`. None of it depends on the provider choice.

---

## 1. Payment provider and what it can really do

**Waiting for you:** the provider's name and a link to its developer documentation. PLAN.md section 7 suggested ClickPesa as a candidate, and the original spec named ClickPesa USSD push. I am not assuming either. Once I have the documentation, I'll fill the "What the provider can do" column from it and rewrite anything below that depends on it.

| Capability | What Sokoni needs it for | What I'll check in the docs | If the provider can't do it: closest honest design |
|---|---|---|---|
| **Collection** (USSD push / checkout for M-Pesa, Mixx by Yas, Airtel Money, HaloPesa, cards) | Buyer pays the order total | Networks covered, request/response, timeouts, status values, how the buyer confirms | Not optional. Without it there is no wallet. |
| **Status query** | Never trust a webhook alone; resolve timeouts | Query by our reference and by theirs | Webhook only, plus manual reconciliation. Weaker; I'd flag it as a risk. |
| **Webhooks with signatures** | Learn results quickly and safely | Signing method (HMAC, keys), retries, event IDs, IP list | Poll the status API from the minute cron. Slower, still correct. |
| **Order hold / escrow** | Buyer money held until delivery; seller can't withdraw it | Native escrow or split-settlement product, hold duration limits, release and cancel calls | Money lands in Sokoni's collection account and the "hold" is a ledger state. Regulatory question in section 2. |
| **Wallets per user** (stored balance at the partner) | Real buyer/seller/rider wallets the partner issues and safeguards | Sub-accounts or wallets per customer, KYC tiers, transfer between wallets | No partner wallets: seller and rider "balances" are Sokoni ledger entries backed by money in Sokoni's collection account, paid out by disbursement. **No spendable buyer balance**; the buyer view shows payments, holds and refunds only. |
| **Top-ups** | Buyer loads money in advance | Needs partner wallets | Not offered. Buyers pay per order. |
| **Allocation** (credit seller, rider, Sokoni on delivery) | Split one collection three ways | Split payments, transfers between wallets, per-transfer fees | Ledger allocation inside Sokoni; the real money moves only at payout. |
| **Refunds / reversals** | Cancellations, disputes, failed deliveries | Refund to the original payer, partial refunds, time limits, fees | Disbursement (B2C) back to the buyer's number, recorded as a refund. |
| **Cash-out / payouts (B2C)** | Sellers and riders withdraw | Mobile money and bank, limits, fees, name check on the receiving number, bulk payouts | Not optional for sellers and riders. Without it: manual transfers with references typed in by an admin (today's `driver_payouts` way). Works, but doesn't scale. |
| **Idempotency** | A retried request never pays twice | Idempotency keys or unique merchant references honoured | Unique reference per attempt, and always query before retrying. |
| **Statements / settlement reports** | Daily reconciliation | API or downloadable CSV, cut-off time, fee lines | Admin uploads the daily CSV; the job parses it. |
| **Sandbox** | Build and test before live money | Test numbers, simulated webhooks | Fake gateway only until live, with very low live limits at first. |

---

## 2. Where the money sits (for your lawyer, not decided here)

The client document says a licensed partner "issues and safeguards real wallet value, executes transfers and provides confirmations," and that Sokoni "controls the order logic and displays the resulting balances." There are three ways to build that. Each has a different legal shape. **I'm not a lawyer and this section decides nothing.** It frames the questions.

| | **A. Partner-held wallets** | **B. Sokoni collection account** | **C. Pay-per-order, no stored balance** |
|---|---|---|---|
| Where buyer money sits before delivery | In a wallet or escrow the partner issues in the buyer's name, or the partner's own escrow product | In Sokoni's merchant/collection account at the partner, pooled | Same as B |
| Seller and rider "balances" | Real wallet balances at the partner | Sokoni's ledger, backed by the pooled account | Same as B, paid out on a schedule |
| Buyer balance and top-ups | Possible, issued by the partner | Would mean Sokoni holds customer stored value | None. Refunds go back to the paying number |
| Sokoni's role | Platform instructing a licensee | Holds and moves other people's money | Holds other people's money between collection and payout |
| Engineering effort | Depends on the partner's API (wallet calls) | Highest: Sokoni carries the full ledger risk | Same as B minus top-ups and buyer balance |

My general understanding, **to be confirmed by your lawyer**: in Tanzania the Bank of Tanzania regulates payment systems and electronic money under the National Payment Systems Act, 2015 and the Electronic Money Regulations, 2015. The client document also cites Electronic Money Amendment Regulations, 2026, which I have not read. Issuing stored value that people can spend or withdraw is the activity those rules license, with customer funds kept safeguarded. Model A keeps that activity with the licensed partner. Models B and C put Sokoni in the position of holding other people's money for hours to days.

**Questions for your lawyer:**

1. In model A, is Sokoni simply a merchant or platform using the partner's licence? Does Sokoni itself need a licence, approval or registration (for example as a payment service provider, agent or aggregator)?
2. In models B and C, does holding buyer money between collection and seller payout count as issuing e-money or as providing a payment service? Does the partner's escrow or split-settlement product, if it has one, move that responsibility to the partner?
3. Can rider and seller earnings sit in Sokoni's books until withdrawal, or must they be paid out within a fixed time?
4. Can a refund be kept as in-app credit, or must it go back to the original payment method?
5. What identity checks are required before paying out to sellers and riders? Is the NIDA number we already collect enough, or must it be verified?
6. How must collection and withdrawal fees be displayed, and when? (The client document requires fees to be shown clearly.)
7. What happens to unclaimed balances, for example from a seller who disappears?
8. Does holding NIDA numbers and payout phone numbers require registration under the Personal Data Protection Act, 2022?
9. For your accountant: how are commission, the delivery margin and rider payouts taxed (VAT, withholding)?

**My recommendation for engineering, not law:** build the ledger so it works for all three models. Only the gateway adapter and the "where real money is" account change between them. The legal answer then decides the adapter, not the whole design.

---

## 3. Ledger design

### 3.1 Rules (from the client document) and how each is enforced

| Rule | How it's enforced |
|---|---|
| Never create spendable money by changing a database field | Balances are never stored as editable numbers. They are sums of immutable ledger entries. Only `LedgerService::post()` writes entries. Any posting that brings money **in** must reference a partner event whose signature was verified **and** whose status was confirmed by a status query. |
| Keep pending, available, disputed and withdrawn distinct | Separate ledger accounts per state (3.2). "Withdrawn" is the sum of confirmed payouts. |
| Permanent transaction record and ID for every order and allocation | Every posting is a `ledger_transactions` row with a ULID, a type, a unique idempotency key and links to the order, payment or payout. No updates, no deletes; corrections are reversing postings. |
| Daily reconciliation against partner statements | 3.6 |
| No duplicate transfers; failed payouts visible and safely retryable | 3.5 and 3.7 |
| Confirm partner capabilities before promising them | Section 1, and a `capabilities()` method on the gateway that the apps read before showing a feature |

### 3.2 Accounts and balance states

Double-entry: every transaction's entries add up to zero. Amounts are whole shillings, signed integers.

| Account | Owner | Meaning |
|---|---|---|
| `cash_at_partner` | Sokoni | Real money at the partner in Sokoni's name (models B/C), or the mirror of partner wallets (model A). The only account that must equal a partner statement. |
| `buyer_held` | per order | Money collected for an order, reserved until delivery or cancellation |
| `buyer_available` | per buyer | **Model A only** (top-ups, refunds as credit). Absent otherwise. |
| `refund_in_flight` | per buyer | Refund sent to the partner, not yet confirmed |
| `seller_pending` / `seller_available` / `seller_disputed` | per seller | Earned but in the hold period / withdrawable / frozen by a dispute |
| `rider_pending` / `rider_available` | per rider | Same for delivery payouts |
| `payout_in_flight` | per seller or rider | Withdrawal sent, not yet confirmed |
| `sokoni_commission` / `sokoni_delivery_margin` | Sokoni | Revenue |
| `sokoni_partner_fees` | Sokoni | Fees the partner charged that Sokoni bears |
| `suspense` | Sokoni | Money that can't be matched yet (for example a payment arriving for a cancelled order). Must be cleared by an admin with a reason. |

Withdrawn to date = the sum of confirmed payouts for that seller or rider.

### 3.3 Tables (sketch)

```
ledger_accounts      id, owner_type (order|buyer|seller|rider|platform), owner_id, kind (see 3.2),
                     currency, created_at; unique(owner_type, owner_id, kind)
ledger_transactions  id (ULID), type, idempotency_key (unique), order_id?, payment_id?, payout_id?,
                     dispute_id?, partner_reference?, actor_user_id?, reason?, posted_at, meta json
ledger_entries       id, transaction_id, account_id, amount (signed bigint), created_at
                     -- sum(amount) per transaction = 0; never updated or deleted
account_balances     account_id (pk), balance, last_entry_id, version
                     -- a cache updated in the same DB transaction as the entries, checked nightly
payments             (exists) the collection: order_id, purpose, amount, provider, channel, msisdn
                     (encrypted), status, idempotency_key, provider_reference, timestamps
payment_webhook_events (exists) provider, event_id (unique per provider), payload, signature_valid,
                     processed_at, result
payouts              id, payee_type (seller|rider), payee_id, amount, fee, destination (encrypted),
                     status (requested|submitted|succeeded|failed|unknown|cancelled), attempt,
                     idempotency_key, provider_reference, failure_reason, requested_by, approved_by,
                     submitted_at, settled_at
payout_accounts      payee_type, payee_id, method (mobile_money|bank), msisdn/account (encrypted),
                     account_name, verified_at, verified_by
refunds              id, order_id, payment_id, amount, reason, status, provider_reference, approved_by
disputes             id, order_id, opened_by, reason, status (open|refund_buyer|release_seller|split),
                     amount, resolved_by, resolution_note, timestamps
reconciliation_runs  id, business_date, source (api|csv), status, totals json
reconciliation_items run_id, partner_reference, partner_amount, ledger_transaction_id?,
                     outcome (matched|missing_in_ledger|missing_at_partner|amount_mismatch|fee_only),
                     resolved_by, resolution
```

The database is MariaDB on shared hosting. I won't rely on triggers (a cPanel database user may not be allowed to create them). Append-only is enforced by the models and service, by tests, and by the nightly integrity check in 3.6.

### 3.4 Postings for the client document's example

Buyer pays TSh 23,000 (20,000 goods + 3,000 delivery). Commission is 5% of goods; the rider gets 2,500.

| Step | Event | Entries (debit +, credit −) |
|---|---|---|
| 1 | Collection confirmed (signature verified and status query says paid) | `cash_at_partner` +23,000 / `buyer_held` −23,000 |
| 2 | Customer PIN accepted: allocate | `buyer_held` +23,000 / `seller_pending` −19,000 / `rider_pending` −2,500 / `sokoni_commission` −1,000 / `sokoni_delivery_margin` −500 |
| 3 | Hold period ends with no dispute | `seller_pending` +19,000 / `seller_available` −19,000 (rider the same, with its own hold period) |
| 4 | Seller withdraws 19,000 | `seller_available` +19,000 / `payout_in_flight` −19,000 |
| 5 | Partner confirms the payout | `payout_in_flight` +19,000 / `cash_at_partner` −19,000 |
| 5b | Payout fails | `payout_in_flight` +19,000 / `seller_available` −19,000 (money back, failure shown) |
| F | Partner statement shows a fee Sokoni bears | `sokoni_partner_fees` +fee / `cash_at_partner` −fee |

Check: after step 2 the 23,000 is fully owned: 19,000 + 2,500 + 1,000 + 500 = 23,000.

### 3.5 Webhooks: idempotent and safe

1. `POST /api/payments/webhook/{provider}`, unauthenticated, throttled, outside the API's auth.
2. Verify the signature with `PAYMENT_WEBHOOK_SECRET` (constant-time compare), and the timestamp within a tolerance to stop replays. Invalid: store with `signature_valid = false`, answer 401, never process.
3. Store the raw event with `unique(provider, event_id)`. A duplicate answers 200 and does nothing.
4. Answer 200 once stored. Processing happens inline when quick, and the minute cron retries anything unprocessed. There is no queue worker on shared hosting.
5. Processing locks the payment row (`lockForUpdate`), checks the state change is allowed (pending → succeeded is; succeeded → pending never is), then **queries the partner's status API** before posting. A webhook alone never credits money.
6. Every posting carries an idempotency key such as `payment:{id}:collected` or `payout:{id}:settled`. A second attempt with the same key is a no-op, so a duplicate webhook can't post twice.
7. Payloads are stored with phone numbers masked, and secrets are never logged.

### 3.6 Daily reconciliation (cron, after the partner's cut-off)

1. Fetch yesterday's partner transactions (API, or the CSV an admin uploads if there's no API).
2. Match each line to a ledger transaction by partner reference, then by amount and time.
3. Outcomes: matched; in the ledger but not at the partner; at the partner but not in the ledger; amount mismatch; fee line.
4. Fee lines post automatically to `sokoni_partner_fees` (they reflect real money already gone). Everything else goes to an admin queue. **Nothing creates balance automatically.** A "missing in ledger" collection is resolved by an admin, who links it to an order or moves it to `suspense` with a reason.
5. Integrity checks every night: every transaction sums to zero, cached balances equal recomputed sums, and `cash_at_partner` equals the partner's reported balance. Any difference alerts admins by email and in the panel, and pauses withdrawals until cleared.

### 3.7 Failed and uncertain payouts

- States: requested → submitted → succeeded | failed | **unknown** (timeout, no answer).
- **Never retry an unknown payout blindly.** Query the partner first. Retry only if the partner confirms the first attempt didn't happen, using a new attempt number in the reference.
- A failure returns the money to `available` with the reason shown to the payee and to admins. Retry is a button for the payee or an admin.
- Admins see every failed or unknown payout older than an hour in a queue.

### 3.8 Refunds

- **Before allocation** (cancelled before delivery): `buyer_held` → `refund_in_flight` → confirmed by the partner → out of `cash_at_partner`. Partial refunds follow the existing cancellation rules (for example the TSh 1,000 cancellation fee to the rider after assignment).
- **After allocation** (dispute won by the buyer): taken back from `seller_disputed`, then refunded the same way.
- A refund that fails stays visible and retryable, like a payout.
- Model A with in-app credit allowed (lawyer question 4): the refund goes to `buyer_available` instead and nothing leaves the partner.

### 3.9 Disputes

- The buyer opens a dispute within the dispute window after delivery (setting; for example 48 hours).
- The seller's share for that order moves from `seller_pending` (or `seller_available`, if already released) to `seller_disputed`, so it can't be withdrawn.
- An admin resolves it: release to the seller, refund the buyer, or split. Each outcome is a posting with the admin and the reason.
- If the seller has already withdrawn the money, their balance can go negative. It's recovered from future sales and flagged to admins. Sokoni covering it is a policy choice for you.

### 3.10 Withdrawals

- To a verified payout account only (`payout_accounts`): the payee's own registered mobile money number, or a bank account confirmed by an admin.
- On demand, above a minimum, or on a schedule (for example weekly). Fees are shown before confirming.
- Limits per day and per transaction; first withdrawal after changing the payout number held for admin review (a common fraud pattern).
- Withdrawals pause automatically when the nightly integrity check fails.

### 3.11 Concurrency

Every posting locks the affected `account_balances` rows in a fixed order (by ID) and checks the balance before debiting an `available` account. The PIN check, which closes a delivery, and its allocation run in one database transaction.

---

## 4. What changes from the v1 delivery decisions

**None of the payment tables are in production yet.** `payments`, `payment_webhook_events` and `driver_payouts` exist only on this branch and on staging (test data). They can be reshaped with forward migrations, and staging can be reset. No live data needs migrating.

| v1 decision (DECISIONS.md) | Wallet plan | |
|---|---|---|
| Buyer pays only the delivery fee online; goods are paid to the seller directly | Buyer pays goods + delivery in one collection; the seller is paid from the wallet after delivery | **Replaced** for wallet orders |
| Fee never added to `orders.total` for dispatch orders | Keep `orders.total` = goods, the number sellers already see. The amount charged lives on the payment, and the split lives in the ledger. | **Kept** |
| Quote frozen when the seller accepts; buyer then pays (`pending_payment`) | Same moment: the collection for the full amount happens after the seller confirms stock, as the client document describes | **Kept** |
| Unpaid jobs cancel after 20 minutes | Same, for the full collection | **Kept** |
| Delivery commission 15%, rider gets the rest of the fee | Rider payout rule from settings (section 5); the 2,500-of-3,000 example implies a different rule | **Replaced** (needs your choice) |
| Cancellation money rules (buyer and seller cancel, unreachable customer, driver failure) | Same rules, executed as ledger postings. **New decision needed:** goods money is now prepaid, so an unreachable customer must get the goods refunded, possibly minus costs. | **Kept**, one new rule |
| Cash delivery fee mode (staging only) | Off when the wallet is on. COD stays only where you decide (question 9). | **Kept as a fallback** |
| `payments` table (delivery fee) | Same table, wider purpose: order total. Add a breakdown (goods, delivery) and link to ledger transactions. | **Extended** |
| `driver_payouts` (manual, admin types the reference) | Replaced by `payouts` for sellers and riders, with partner payouts and states. `delivery_jobs.payout_id` is dropped. | **Replaced** |
| `delivery_jobs.earned_amount`, `refund_amount`, `refund_status`, `payment_status` | Kept as a read-model written in the same transaction as the ledger posting (the driver app reads them), checked nightly against the ledger | **Kept as cache** |
| `DriverEarnings` reads delivery jobs | Reads the ledger | **Replaced** |
| No new values in existing ENUM columns | `orders.payment_method` and `orders.payment_status` are ENUMs in production. **Sokoni 1.0 shows any payment method other than "pay on pickup" as "Cash on delivery"**, so a wallet order seen in 1.0 would tell the seller to collect cash again. Wallet orders must therefore go only to shops on Sokoni 1.1+ that have set up a payout account, and buyers pay by wallet only from 1.1. Then a new appended ENUM value `wallet` is safe. | **Kept**, with a gate |

---

## 5. Settings and the worked example

All in the admin Settings page (`App\Support\Settings`: a database value wins over the config default), so no redeploy is needed to change them.

| Setting | Example (client doc) | Default I'd propose | Notes |
|---|---|---|---|
| `wallet_seller_commission_pct` | 5 | 5 | On goods only, not on delivery. Rounded to the shilling; the seller gets the remainder, so the parts always add up. |
| `wallet_rider_payout_mode` | rider gets 2,500 of a 3,000 fee | *your choice* | Today the fee depends on distance (1,500 + 700/km, minimum 2,000), not a flat 3,000. "2,500 of 3,000" needs a rule: (a) Sokoni keeps a fixed TSh 500 per delivery, (b) rider gets 83.33% of the fee, or (c) rider gets a fixed TSh 2,500. |
| `wallet_seller_release_hours` | - | 24 | Hold before seller money becomes withdrawable (dispute window) |
| `wallet_rider_release_hours` | - | 0 | Riders paid as soon as the PIN is accepted |
| `wallet_dispute_window_hours` | - | 48 | |
| `wallet_min_withdrawal` | - | 5,000 | Placeholder; set from the partner's fee tariff so fees don't eat small withdrawals |
| `wallet_payout_schedule` | - | weekly, Monday | Plus on-demand above the minimum |
| `wallet_collection_fee_bearer` | "before provider fees" | sokoni | Or charge the buyer a visible service fee (disclosure rules apply) |
| `wallet_payout_fee_bearer` | - | payee | Shown before the payee confirms a withdrawal |
| Partner fees (collection %, payout fee, refund fee) | - | from the provider's tariff | Filled in once the provider is known |

**The example reconciles:**

```
Buyer pays            20,000 goods + 3,000 delivery           = 23,000
Seller                20,000 - 5% commission (1,000)          = 19,000
Rider                 delivery payout                         =  2,500
Sokoni                1,000 commission + 500 delivery margin  =  1,500
                                                        total = 23,000  (nothing left over)
```

**After partner fees** (the client document says the 1,500 is "before provider fees"). The rates below are **made up** to show the arithmetic, not real tariffs:

```
Sokoni gross                         1,500
Collection fee, if Sokoni bears 2%   - 460   (2% of 23,000; illustrative rate)
Sokoni net per order               = 1,040
Seller withdraws 19,000 with a 500 payout fee borne by the seller -> receives 18,500
```

If Sokoni also paid every payout fee, and sellers withdrew after every order, two payouts per order at TSh 500 would leave about TSh 40. That is why the minimum withdrawal and the schedule matter.

**Please confirm:** the 5% (goods only), the rider rule (a, b or c), who bears collection and payout fees, and the hold and dispute times.

---

## 6. Credentials and configuration

- Credentials are read **only** from `.env`, through `config/payments.php`. They are never committed: `.env` is gitignored and only `.env.example` gets blank placeholders.
- Never logged: the HTTP client redacts `Authorization` headers and key fields; webhook payloads are stored with phone numbers masked.
- `PAYMENT_ENV=live` refuses to start unless every key is set and `PAYMENT_PROVIDER` isn't `fake`. A sandbox key used with `live`, or the reverse, fails at boot.
- **Fake gateway first.** The unused `PaymentGateway::charge(Order)` interface is replaced by:

```php
interface PaymentGateway
{
    public function capabilities(): GatewayCapabilities;            // what section 1 confirmed
    public function initiateCollection(CollectionRequest $r): CollectionResult;
    public function queryCollection(string $reference): CollectionStatus;
    public function refund(RefundRequest $r): RefundResult;
    public function initiatePayout(PayoutRequest $r): PayoutResult;
    public function queryPayout(string $reference): PayoutStatus;
    public function parseWebhook(Request $request): WebhookEvent;   // verifies the signature or throws
    public function statement(CarbonImmutable $day): iterable;      // reconciliation lines, if supported
}
```

`FakePaymentGateway` gives predictable outcomes from the test phone number (for example a number ending 0001 fails, 0002 times out) and sends correctly signed fake webhooks through an artisan command that only works when `PAYMENT_PROVIDER=fake`. The real adapter comes after section 1.

**Lines to add to the staging `.env`** (staging-api.sokoni.co.tz) for the first phases, with the fake gateway:

```
# ---- Wallet & payments (docs/delivery/WALLET_PLAN.md) ----
WALLET_ENABLED=false
PAYMENT_PROVIDER=fake
PAYMENT_ENV=sandbox
PAYMENT_BASE_URL=
PAYMENT_API_KEY=
PAYMENT_SECRET_KEY=
PAYMENT_WEBHOOK_SECRET=
```

When the sandbox keys arrive, only these change: `PAYMENT_PROVIDER=<provider>`, `PAYMENT_BASE_URL=<sandbox URL from the docs>`, and the three keys from the partner's sandbox dashboard. `WALLET_ENABLED=true` comes only after phase W2 passes on staging. The webhook URL to enter in the partner's dashboard will be `https://staging-api.sokoni.co.tz/api/payments/webhook/<provider>`. The production `.env` is not touched until you decide to go live.

---

## 7. Phases

| Phase | What | Depends on |
|---|---|---|
| **W0** | You: provider name and docs (section 1), lawyer's answers (section 2), numbers (section 5). Me: fill in section 1 and adjust this plan. | You |
| **W1** | Ledger core (accounts, transactions, entries, balance cache, integrity check), new `PaymentGateway` interface, `FakePaymentGateway`, settings, admin read-only ledger view. `WALLET_ENABLED=false`, no app changes. | Plan approval |
| **W2** | Collections and holds for dispatch orders: pay the full amount after the seller accepts, signed webhooks and status queries, the 20-minute expiry, cancellation refunds. | W1; Sokoni 1.1 pay screen (phase 4) |
| **W3** | Allocation when the PIN is accepted; hold periods and release; balances API for seller, rider and buyer views; driver earnings read from the ledger; seller wallet screen in Sokoni 1.1; basic disputes. | W2 |
| **W4** | Withdrawals: payout accounts and their verification, on-demand and scheduled payouts, failed and unknown handling, admin payouts queue, notifications. Replaces `driver_payouts`. | W3; partner payout API |
| **W5** | Daily reconciliation (API or CSV), mismatch queue, alerts, finance export. | W4 |
| **W6** | Real partner sandbox end to end on staging; then a small live pilot (a few shops, low daily limits) on production with your approval; then go-live. | W5, lawyer sign-off |

Each phase ends with tests and your approval, the same as the delivery phases.

---

## 8. Open questions for you

1. **Provider:** which payment partner, and the link to its API documentation?
2. **Legal model:** A (partner wallets), B (Sokoni collection account) or C (pay per order, no stored balance)? This is your lawyer's call; section 2 has the questions.
3. **Buyer wallet:** do you want stored buyer balances and top-ups at all? They are only possible in model A, and only if the lawyer agrees.
4. **Commission:** 5% on goods only, not on the delivery fee?
5. **Rider payout rule:** (a) Sokoni keeps a fixed TSh 500, (b) the rider gets 83.33% of the fee, or (c) the rider gets a fixed TSh 2,500?
6. **Fees:** who bears the collection fee (Sokoni, or a visible buyer service fee) and the payout fee (payee or Sokoni)?
7. **Timing:** seller hold period (I propose 24 h), dispute window (48 h), rider money available immediately?
8. **Withdrawals:** minimum amount, a weekly schedule as well as on demand, and daily limits?
9. **Scope:** wallet for dispatch orders only at first? Do cash on delivery and pay-on-pickup stay for other orders?
10. **Unreachable customer:** goods are now prepaid. Refund the goods in full, or minus a return cost?
11. **Seller negative balance** after a lost dispute when the money was already withdrawn: recover from future sales only, or does Sokoni cover it?
12. **Who may approve refunds, adjustments and large payouts:** any admin, or two admins for amounts above a threshold?
