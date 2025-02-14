## Connecting to LinkedIn Platform

To integrate the plugin with your LinkedIn account, you need to create an application on the LinkedIn Developer Portal. Follow these steps to obtain your Client ID and Client Secret:

### Step-by-Step Guide

1. **Visit LinkedIn Developer Portal:**
   - Go to the [LinkedIn Developer Portal](https://developer.linkedin.com/).

2. **Create a New App:**
   - Click on **'My Apps'** at the top of the page.
   - Select **'Create App'**.
   - Fill in the necessary details such as App Name, LinkedIn Page, and other required information.
   - Click **'Create App'**.

3. **Get Client ID and Client Secret:**
   - Once your app is created, navigate to your app's **Authentication** tab.
   - Here, you'll find your **Client ID** and **Client Secret**. Click **'Copy'** to easily obtain them.

4. **Assign Permissions:**
   - Go to the **'Products'** tab in your app settings.
   - Add the following products (permissions) by clicking **'Select'**:
     - `Sign In with LinkedIn`
     - `Share on LinkedIn`
     - `Marketing Developer Platform` for accessing company pages
   - Make sure to save the changes and follow any LinkedIn review processes that may be necessary for certain permissions.

5. **Configure OAuth Redirect URI:**
   - Navigate back to the **'Authentication'** tab.
   - In the **Authorized Redirect URLs for your app** section, add the redirect URI:
     - `https://<Your-Craft-CMS-Site-URL>/admin/convergine-socialbuddy/li_auth`
   - **Copy the Redirect URI** from the LinkedIn Redirect URI read-only text field in your plugin settings to ensure it's configured correctly.

6. **Provide Credentials to Plugin:**
   - Enter your **Client ID** and **Client Secret** in the corresponding fields in the plugin settings to complete the connection.

### Important Notes

- Make sure your app settings comply with LinkedIn's [API Terms of Use](https://developer.linkedin.com/legal/api-terms-of-use).
- Regularly review your app settings and usage to maintain security and compliance.

By following these steps, you'll be able to connect the plugin to your LinkedIn account and start using its social media features.