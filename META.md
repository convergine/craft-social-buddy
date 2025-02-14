## Connecting to Meta (Facebook) Platform

To integrate the plugin with your Facebook account, you need to create an application on the Meta platform. Follow these steps to obtain your App ID and App Secret:

### Step-by-Step Guide

1. **Visit Meta for Developers:**
   - Go to the [Meta for Developers](https://developers.facebook.com/) website.

2. **Create a New App:**
   - Click on **'My Apps'** in the upper-right corner.
   - Select **'Create App'**.
   - Choose the **'Consumer'** app type (or another type that suits your needs), then click **'Next'**.
   - Enter your app name and contact email. You may need to complete a security check.
   - Click **'Create App ID'**.

3. **Get App ID and App Secret:**
   - Once your app is created, go to your app dashboard.
   - Navigate to **'Settings'** > **'Basic'**.
   - Here, you'll find your **App ID** and **App Secret**. Click **'Show'** beside App Secret and enter your password to reveal it.

4. **Assign Permissions:**
   - Navigate to the **'App Review'** section in the dashboard.
   - Click on **'Permissions and Features'**.
   - Search for and request the following permissions: 
     - `business_management`
     - `pages_manage_posts`
     - `pages_show_list`
     - `instagram_basic`
     - `pages_read_engagement`
   - Click **'Request'** and provide detailed information and justification for each permission. You may need to provide usage scenarios and demonstration videos.
   - Complete any necessary steps for app review based on Facebook guidelines.

5. **Configure OAuth Redirect URI:**
   - Navigate to **'Facebook Login'** > **'Settings'**.
   - Add the following **Valid OAuth Redirect URI**:
     - `https://<Your-Craft-CMS-Site-URL>/admin/convergine-socialbuddy/fb_auth`
   - **Copy the Redirect URI** displayed in the Facebook Redirect URI read-only text field in your plugin settings to ensure it's configured correctly.

6. **Provide Credentials to Plugin:**
   - Enter your **App ID** and **App Secret** in the corresponding fields in the plugin settings to complete the connection.

### Important Notes

- Ensure you follow the [Meta App Review Guidelines](https://developers.facebook.com/docs/apps/review) if you're requesting permissions beyond basic functionality.
- Regularly review your app settings and logs to maintain security.

By following these steps, you'll be able to connect the plugin to your Facebook account and begin leveraging its social media features.