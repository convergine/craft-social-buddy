## Connecting to Twitter Platform

To integrate the plugin with your Twitter account, you need to create an application on the Twitter Developer Portal. Follow these steps to obtain your Consumer Key and Consumer Secret:

### Step-by-Step Guide

1. **Visit Twitter Developer Portal:**
   - Go to the [Twitter Developer Portal](https://developer.twitter.com/).

2. **Create a New App:**
   - Click on **'Projects & Apps'** in the menu.
   - Select **'Overview'** and then click on **'Add App'** under a project or create a new project.
   - Fill in the necessary details such as App Name and save your changes.

3. **Get Consumer Key and Consumer Secret:**
   - Navigate to your app details.
   - Go to the **'Keys and Tokens'** tab.
   - Here, you'll find your **API Key (Consumer Key)** and **API Secret Key (Consumer Secret)**. Click on **'Regenerate'** and **'Copy'** to securely store them.

4. **Assign Permissions:**
   - In the **'App Permissions'** section, set the necessary permissions for your application:
     - **Read and Write** for posting tweets.
     - Enable **Direct Messages** if needed for additional functionality.
   - Save your changes.

5. **Configure OAuth Settings:**
   - Although redirect URIs are not used directly with the 3-legged OAuth for Twitter, ensure your callback URLs are configured properly if needed in specific use cases.
   - Review the **'Authentication Settings'** for any additional configurations required by your use case.

6. **Provide Credentials to Plugin:**
   - Enter your **Consumer Key** and **Consumer Secret** into the corresponding fields in the plugin settings to complete the connection.

### Important Notes

- Ensure you follow Twitter's [Developer Agreement and Policy](https://developer.twitter.com/en/developer-terms/agreement-and-policy).
- Regularly review your app settings, usage, and any rate limits to maintain security and performance.

By following these steps, you'll be able to connect the plugin to your Twitter account and begin utilizing its social media features.