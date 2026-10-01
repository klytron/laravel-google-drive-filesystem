# How to Obtain Google Drive API Credentials and Tokens

This guide will help you set up Google Drive API credentials and obtain the required tokens for use with this package.

---

## 1. Create a Google Cloud Project & Enable Drive API

1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project (or select an existing one).
3. In the left sidebar, go to **APIs & Services > Library**.
4. Search for **Google Drive API** and click **Enable**.

---

## 2. Create OAuth 2.0 Credentials

1. In the left sidebar, go to **APIs & Services > Credentials**.
2. Click **Create Credentials > OAuth client ID**.
3. If prompted, configure the consent screen (set an app name, support email, etc.).
4. Choose **Desktop app** (recommended for server-side usage) or **Web application**.
5. Name your client and click **Create**.
6. Download the credentials JSON file. Your **Client ID** and **Client Secret** are in this file.

---

## 3. Get Refresh Token and Access Token (OAuth 2.0 Playground)

1. Go to the [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/).
2. Click the gear icon (top right) and check **Use your own OAuth credentials**. Enter your Client ID and Client Secret from the previous step.
3. In **Step 1**, enter the scope:

   ```
   https://www.googleapis.com/auth/drive
   ```

   and click **Authorize APIs**.

   > Use the same scope you configured in `GOOGLE_DRIVE_SCOPES` (see the
   > scopes table in the README): the refresh token only carries the scopes
   > selected here, so a token minted with `.../auth/drive.readonly` cannot
   > upload files even if the config asks for full access.
4. Sign in with your Google account and allow access.
5. In **Step 2**, click **Exchange authorization code for tokens**.
6. You will see your **Access token** and **Refresh token**. Copy these values for your `.env` file.

---

## 4. (Optional) Get Google Drive Folder ID

1. Open the folder in Google Drive.
2. Copy the last part of the URL after `folders/`:
   - Example: `https://drive.google.com/drive/folders/<your-folder-id>`

---

## 5. Add to Your .env File

```
GOOGLE_DRIVE_CLIENT_ID=your-client-id
GOOGLE_DRIVE_CLIENT_SECRET=your-client-secret
GOOGLE_DRIVE_REFRESH_TOKEN=your-refresh-token
GOOGLE_DRIVE_ACCESS_TOKEN=your-access-token
GOOGLE_DRIVE_FOLDER_ID=your-folder-id (optional)
```

---

## Troubleshooting

- If you get errors, double-check your credentials and scopes.
- The refresh token is only shown the first time you authorize a new client. If you lose it, repeat the process.
- For more help, see the [Google Drive API documentation](https://developers.google.com/drive/api/v3/about-auth).
