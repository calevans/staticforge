---
title: 'Forms'
description: 'Documentation for the Forms feature: processing form submissions in a static site environment.'
template: docs
menu: '3.1.5'
tags:
  - forms
  - feature
---
# Forms Feature

The Forms feature allows you to easily embed contact forms and other types of forms into your static pages using a simple shortcode. It handles form rendering, configuration, and even includes spam protection via Altcha.

## Recommended Backend

While StaticForge can work with any form backend, we highly recommend [SendPoint](https://github.com/calevans/sendpoint). It is a lightweight, self-hosted form processor that handles email notifications, webhooks, and integrates seamlessly with StaticForge's built-in Altcha spam protection.

## Configuration

Forms are configured in your `siteconfig.yaml` file. You can define multiple forms, each with its own fields and submission endpoint.

```yaml
forms:
  contact:
    provider_url: "https://eicc.com/f/"
    form_id: "YOUR_FORM_ID"
    challenge_url: "https://sendpoint.lndo.site/?action=challenge" # Optional: For Altcha spam protection
    submit_text: "Send Message"
    success_message: "Thanks! We've received your message."
    error_message: "Oops! Something went wrong. Please try again."
    fields:
      - name: "name"
        label: "Your Name"
        type: "text"
        required: true
        placeholder: "John Doe"
      - name: "email"
        label: "Email Address"
        type: "email"
        required: true
        placeholder: "john@example.com"
      - name: "message"
        label: "Message"
        type: "textarea"
        rows: 7
        required: true
        placeholder: "How can we help you?"
```

### Configuration Options

| Option | Description |
|Str|---|
| `provider_url` | The base URL of your form processing service. |
| `form_id` | The unique ID for this specific form. Appended to `provider_url`. |
| `challenge_url` | (Optional) The URL for the Altcha challenge service. If provided, an Altcha widget will be added to the form. |
| `template` | (Optional) The name of a custom template to use for this form (e.g., `contact_us` for `templates/active_theme/contact_us.html.twig`). |
| `submit_text` | The text to display on the submit button. Default: "Submit". |
| `success_message` | The message to display when the form is successfully submitted. |
| `error_message` | The message to display if submission fails. |
| `fields` | A list of fields to include in the form. |

### Field Options

| Option | Description |
|---|---|
| `name` | The `name` attribute of the input field. |
| `label` | The label text for the field. Defaults to capitalized name. |
| `type` | The input type (e.g., `text`, `email`, `textarea`). Default: `text`. |
| `required` | Boolean. Whether the field is required. |
| `placeholder` | Placeholder text for the input. |
| `rows` | (Textarea only) Number of rows. Default: 5. |

## Usage

To insert a form into your content (Markdown or HTML), use the `form()` shortcode with the name of the form defined in `siteconfig.yaml`.

```markdown
# Contact Us

Have questions? Fill out the form below!

{{ form('contact') }}
```

## Custom Templates

You can customize the look and feel of your forms by creating a custom Twig template.

1.  Create a new template file in your active theme directory (e.g., `templates/staticforce/contact_us.html.twig`).
2.  In your `siteconfig.yaml`, add the `template` option to your form configuration:

    ```yaml
    forms:
      contact:
        template: contact_us
        # ... other options
    ```

The system will look for `templates/YOUR_THEME/contact_us.html.twig`. If it doesn't exist, it will fall back to the default form template.

Your custom template will receive the following variables:
- `endpoint`: The submission URL.
- `fields`: The array of field definitions.
- `submit_text`: The text for the submit button.
- `success_message`: The success message.
- `error_message`: The error message.
- `challenge_url`: The Altcha challenge URL (if configured).

## Spam Protection (Altcha)

The Forms feature supports [Altcha](https://altcha.org/) for privacy-friendly spam protection. This feature is **completely optional**.

### Enabling Altcha
To enable spam protection:
1.  Ensure you have an Altcha challenge server running or use a hosted service.
2.  Add the `challenge_url` key to your form configuration in `siteconfig.yaml`.

```yaml
forms:
  contact:
    # ... other config ...
    challenge_url: "https://your-altcha-server.com/challenge"
```

When `challenge_url` is present, the system will automatically:
- Include the Altcha widget in the form.
- Load the necessary Altcha JavaScript.

### Disabling Altcha
To disable spam protection, simply **remove or comment out** the `challenge_url` line in your `siteconfig.yaml`. If this key is missing, no Altcha code or widgets will be generated.

## Styling

The form comes with default styling that is injected automatically. You can override these styles in your site's CSS. The form uses the following classes:

- `.sf-form-wrapper`: Container for the form and messages.
- `.sf-form`: The form element itself.
- `.sf-form-group`: Wrapper for each label/input pair.
- `.sf-label`: The field label.
- `.sf-input`: The input or textarea element.
- `.sf-button`: The submit button.
- `.sf-form-message`: The container for success/error messages.
- `.sf-message-success`: Applied to the message container on success.
- `.sf-message-error`: Applied to the message container on error.
