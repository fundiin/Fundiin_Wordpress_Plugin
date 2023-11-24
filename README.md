

## Compatibilities and Dependencies

- **Wordpress**: 5.0.0+
- **WooCommerce**: v3.0+
- **PHP**: 5.6+ __(short_open_tag must be enabled)__

## How to install Fundiin WooCommerce plugin

### Option 1: Download from GitHub

1. Download the latest release code from our repo on GitHub at:
   
   [GitHub Repo](https://github.com/fundiin/Fundiin_Wordpress_Plugin)

2. Go to your WordPress Dashboard Admin.

3. Click on **Plugins** → **Add new**.

4. Select **Upload Plugin**, then choose the zip file that you downloaded from GitHub.

5. Click **Install now**.
    ![How to Install from Zip in WordPress](/img/plugins/woocommerce/how_to_install_from_zip_wordpress.png)
6. After installation, click **Activate** to enable the plugin.

### Option 2: Install from Official WordPress Plugin Store

1. Navigate to your WordPress Dashboard Admin.

2. Click on **Plugins** → **Add New**.

3. In the search box, type "Fundiin."

4. Click **Install now** next to the "Fundiin" plugin.

5. After installation, click **Activate** to enable the plugin.
    ![How to Install from store](/img/plugins/woocommerce/install_from_store.png)

    **Activating the Plugin (if needed)**

    If you forget to activate the plugin, follow these steps:

    1. Go to **Plugins** → **Installed Plugins**.

    2. Find **Fundiin for WooCommerce** in the list.

    3. Click **Activate** next to the "Fundiin for WooCommerce" plugin.


## Configuration


:::warning
⚠️ **Warning:** Before proceeding with this step, you must complete all the paperwork with our Business Development Team and wait for them to activate your business accounts. If not, none of the following steps will work.
:::


**To Activate and Configure the Plugin:**

![Fundiin WooCommerce Settings](/img/plugins/woocommerce/fundiin_woocommerce_settings.png)


1. Go to **WooCommerce** → **Settings**.

2. Click on **Payments**.

3. Locate and select "Fundiin" in the list of payment methods.

4. Fill in all the required credentials on this page.

    ![Fundiin WooCommerce Credentials Settings](/img/plugins/woocommerce/fundiin_woocommerce_settings_attr.png)

    - **Enable/Disable:** Toggle to "On" to enable the Fundiin Payment Method or "Off" to disable it.
    - **Environment:** Set to "Production Environment (Live)" if you have finished the setup and want to go live.
    - **Client ID:** Provided by Fundiin.
    - **Merchant ID:** Provided by Fundiin.
    - **Secret Key:** Provided by Fundiin.
    - **Notify URL (IPN):** This is the URL to receive the webhook when a payment is completed or cancelled. It will override the default webhook for WooCommerce. If you don't use this feature, you can leave it empty.

   For more information on webhook setup, [Payment Notification](https://docs.fundiin.vn/v2/payments/api/notification).

:::warning
⚠️ **Warning: THE SANDBOX ENVIRONMENT IS RESERVED FOR TESTING, AND ALL TRANSACTIONS IN THE SANDBOX HAVE NO VALUE.**
:::

After you finish all the setup, you already to go.