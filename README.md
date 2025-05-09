# 📬 GraphMailer for Drupal

## ⚠️ Disclaimer

> This module was developed for internal use. You are free to use or adapt it under the license below, but:
>
> - It is **not actively maintained**
> - **No support** is provided
> - Use at your own risk
>
> Feel free to submit pull requests if you find ways to improve it — but please understand there is no guarantee of response.

---

GraphMailer replaces Drupal’s default mail system with Microsoft Graph API, enabling email delivery through your Microsoft 365 account.

---

## ⚙️ Features

- Sends emails via Microsoft Graph API
- Supports multiple recipients (To, CC, BCC)
- Sends HTML-formatted emails
- Integrates with Webform email handlers
- Supports optional attachments

---

## 📦 Installation

### 1. Place or install the module

Place the module in:

```
modules/custom/graphmailer
```


### 2. Enable the module

```bash
drush en graphmailer
```

Or enable it via the UI at `/admin/modules`.

---

## 🔐 Microsoft Azure App Setup

1. Register an app in Azure Active Directory (https://portal.azure.com)
2. Note the following credentials:
   - **Tenant ID**
   - **Client ID**
   - **Client Secret**
3. Assign the following **Application permission**:
   - `Mail.Send`

---

## ⚙️ Module Configuration

Go to  
**Manage > Configuration > GraphMailer Settings**  
or visit:  
`/admin/config/system/graphmailer`

Fill in the following:

- **Tenant ID**
- **Client ID**
- **Client Secret** *(not shown after saving)*
- **From email address** (must be a valid, authorized sender in your tenant)

Save the form to apply settings.

---

## 📫 Set as the default mail system

If you're using the Mail System module, configure as follows:

Go to **Manage > Configuration > Mail System** (`/admin/config/system/mailsystem`) and configure:
   - **Formatter**: `GraphMailer` (optional)
   - **Sender**: `GraphMailer`

---

## 🧪 Testing the system

Visit `/graphmailer/test` to:

- Send test emails with customizable fields
- Test multiple recipients and HTML content
- Choose a sender address

---

## 📂 Technical Overview

| Component      | Location                                       |
|----------------|------------------------------------------------|
| Plugin         | `src/Plugin/Mail/GraphMailerMail.php`         |
| Service class  | `src/GraphMailer.php`                         |
| Config form    | `src/Form/GraphMailerSettingsForm.php`        |
| Default config | `config/install/graphmailer.settings.yml`     |

---

## 🧑‍💻 Support

This module was developed for a specific client use case. No official support is provided.

---

## 👤 Author

Lambert

[`Adyta.nl`](https://adyta.nl)
---

## 📄 License

MIT License
