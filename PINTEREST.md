## Connecting to Pinterest Platform

To integrate the plugin with your Pinterest account, you need to create an application on the Pinterest Developer Portal. This process will provide you with the necessary **App ID** and **App Secret** and allow you to configure the permissions (scopes) required by the plugin.

### Step-by-Step Guide

1. **Visit Pinterest Developers Site:**
   - Go to the [Pinterest Developers](https://developers.pinterest.com/) website.

2. **Log In or Create a Business Account:**
   - Click on **'Log in'** at the top-right corner.
   - Use your Pinterest credentials to log in.
   - If you don't have a Pinterest account, click on **'Sign up'** to create one.
   - **Note:** You must have a [Pinterest Business Account](https://business.pinterest.com/). You can convert your personal account to a business account in your account settings.

3. **Create a New App:**
   - After logging in, hover over your profile picture at the top-right corner and select **'Developers Site'**.
   - Click on **'My apps'** in the top navigation bar.
   - Click on the **'Create app'** button.
   - Fill in the required details:
     - **App Name**: Choose a descriptive name for your app.
     - **Description**: Provide a brief description of what your app does.
     - **Website URL**: Enter the URL of your website.
   - Agree to the Pinterest API terms by checking the box.
   - Click **'Create'** to proceed.

4. **Obtain App ID and App Secret:**
   - Once your app is created, you'll be redirected to the app's dashboard.
   - Navigate to the **'App Details'** section.
   - Here, you'll find your **App ID** and **App Secret**.
     - Click on **'Show'** next to the App Secret to view it.
     - Click **'Copy'** to copy both the App ID and App Secret.

5. **Configure Redirect URI:**
   - In the app dashboard, scroll down to the **'Redirect URIs'** section.
   - Click on **'Add Redirect URI'**.
   - Enter the redirect URI specific to your plugin:
     - `https://<Your-Craft-CMS-Site-URL>/admin/convergine-socialbuddy/pinterest_auth`
   - Replace `<Your-Craft-CMS-Site-URL>` with your actual Craft CMS site URL.
   - Click **'Save'** to confirm the changes.

6. **Assign Permissions (Scopes):**
   - Go to the **'Permissions'** or **'Scopes'** section in your app settings.
   - Select the following permissions required by the plugin:
     - `boards:read` - Access to read the user's boards.
     - `boards:write` - Access to create and edit the user's boards.
     - `pins:read` - Access to read the user's pins.
     - `pins:write` - Access to create and edit the user's pins.
     - `user_accounts:read` - Access to read the user's account information.
   - After selecting these permissions, click **'Save'** to apply the changes.
   - **Note:** Some permissions may require you to provide additional information or undergo an app review process.

7. **Submit for Review (If Necessary):**
   - If your app requires extended permissions (such as `boards:write` and `pins:write`), you may need to submit it for review.
   - In the app dashboard, navigate to the **'App Review'** section.
   - Click on **'Submit for Review'**.
   - Provide detailed information about how your app will use the requested scopes.
   - Include any necessary screencasts or documentation as requested.
   - Submit the application and wait for Pinterest to review and approve your app. This process can take several days.

8. **Provide Credentials to Plugin:**
   - In your Craft CMS admin panel, navigate to the plugin's settings.
   - Enter the **App ID** and **App Secret** you obtained earlier into the corresponding fields.
   - Make sure to save the changes to complete the connection.

### Important Notes

- **Compliance:** Ensure your app complies with Pinterest's [Developer Guidelines](https://policy.pinterest.com/en/developer-guidelines) and [Acceptable Use Policy](https://policy.pinterest.com/en/acceptable-use-policy).
- **Approval Process:** Extended permissions require your app to undergo a review process by Pinterest. Provide thorough and accurate information to facilitate approval.
- **Security:** Keep your **App Secret** confidential. Do not share it publicly or include it in any version-controlled code repositories.
- **Regular Updates:** Regularly review your app settings and permissions to maintain compliance and address any changes in Pinterest's policies.