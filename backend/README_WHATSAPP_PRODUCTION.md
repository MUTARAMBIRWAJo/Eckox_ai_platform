# 360Dialog WhatsApp Production Migration Runbook

This document describes the steps required to transition the WhatsApp Integration from the 360Dialog API Sandbox environment to a Live/Production account.

## Prerequisites

1. A verified Facebook Business Manager Account.
2. A clean phone number that is not currently registered with any WhatsApp personal/business app.
3. Access to the 360Dialog Developer Hub dashboard.

---

## Migration Steps

### Step 1: Obtain a Production API Key

1. Log into your **360Dialog Hub** account.
2. Register a new Channel using your production phone number.
3. Follow the Embedded Signup flow to link the number with your Facebook Business Manager.
4. Once verification completes, generate a new **API Key** for the channel.

---

### Step 2: Configure Environment Variables

Update the production `.env` configuration file to point to production endpoints and use the newly generated credentials:

```bash
# 1. Update the base URL to point to the production endpoint
WHATSAPP_BASE_URL=https://waba.360dialog.io/v1

# 2. Swap sandbox API Key with the production one
WHATSAPP_D360_API_KEY=your_production_d360_api_key_here

# 3. Configure the webhook platform secret for signature validation
WHATSAPP_PLATFORM_SECRET=your_production_platform_secret_here

# 4. Configure phone number ID and Verify Token
WHATSAPP_PHONE_NUMBER_ID=your_production_phone_number_id_here
WHATSAPP_VERIFY_TOKEN=your_custom_secure_verify_token_here
```

---

### Step 3: Register the Live Webhook

Set up the production webhook by executing a POST request to register your server's public URL:

```bash
curl --request POST \
  --url https://waba.360dialog.io/v1/configs/webhook \
  --header 'Content-Type: application/json' \
  --header 'D360-API-KEY: <your_production_d360_api_key_here>' \
  --data '{"url": "https://api.eckox.ai/api/webhooks/whatsapp"}'
```

---

### Step 4: Verify Outbound Templates

WhatsApp requires business-initiated messages (replies sent outside the 24-hour window) to use pre-approved templates. 
1. Create templates inside the 360Dialog Hub or Facebook Business Manager.
2. Wait for WhatsApp approval.
3. Update templates in the outbound application code as required.
