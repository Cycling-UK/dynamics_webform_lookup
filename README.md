# Dynamics Webform Lookup Module

## Overview
This module provides a "Find Organisation" feature for Drupal Webforms. It allows users to search for a business using a postcode or organisation number and automatically fills in the rest of the address details directly from Microsoft Dynamics.

## How it Works
Instead of manually typing an address, the user clicks a button to search a live database. The module securely communicates with a Power Automate workflow, retrieves the correct business information, and "pastes" it into the form fields instantly.

## Configuration & Environment Management
You can manage your API credentials and switch between development and production environments through the Drupal UI.

1.  **Access Settings**: Navigate to **Configuration > Web services > Dynamics Webform Lookup Settings**.
2.  **Active Environment**: Toggle between **Dev** and **Prod**. The module will automatically use the corresponding URL during the lookup process.
3.  **API URLs**: Enter your specific Power Automate trigger URLs for both environments.
4.  **Advanced Security**: If enabled, the API Key and Secret are located within the "Advanced Security" collapsed section. These are stored securely in Drupal's configuration system.



## Webform Requirements
For the "Dynamics Lookup" handler to work, your Webform **must** contain specific fields with the exact machine names listed below.

### 1. The Search Container
You must create a **Container** element to hold the search fields.
* **Machine Name**: `dynamics_lookup_container`
* **Purpose**: This acts as the "anchor" where the module injects the "Find Organisation" button.

### 2. Search Input Fields
Inside the container, you need these two fields for the user to type their search terms:
* **lookup_org_num**: A text field for searching by organisation/registration number.
* **lookup_postcode**: A text field for searching by postcode.

### 3. The Results Dropdown
* **Machine Name**: `lookup_results`
* **Type**: Select
* **Requirement**: You must add an option with the value `_none` (e.g., `- Select -`).
* **Purpose**: This dropdown is automatically populated with orgainisations found in Dynamics. Selecting an option triggers the autofill process.

### 4. Autofill Target Fields
The module will automatically fill these fields if they exist on your form:
* `business_name`, `business_registered_name`, `address_line_1`, `address_line_2`, `address_town`, `address_postcode`, and `county`.

## Adding the Handler
1.  Navigate to your webform and go to **Settings > Handlers**.
2.  Click **+ Add handler**.
3.  Select **Dynamics Lookup** (ID: `dynamics_lookup`).
4.  Click **Save** to attach the handler to the webform.

## Troubleshooting
If a search is performed but fields aren't filling correctly:
1.  Check the **Drupal Logs** (`Reports > Recent log messages`) for entries under the **dynamics_debug** channel.
2.  Ensure the **Machine Names** of your webform elements match the list above exactly.
3.  Verify that the **Dynamics Lookup** handler is active on the webform.
4.  Confirm the correct **Environment** (Dev/Prod) is selected in the settings and that the corresponding URL is correctly entered.

### TO DO
* Learn how to use the **Key module** to further secure the API key and secret.